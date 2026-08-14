<?php

/*
|--------------------------------------------------------------------------
| Single sign-on
|--------------------------------------------------------------------------
|
| The load-bearing rule here is that signing in through Authelia never creates
| a user. Registration is closed, and an SSO callback that provisioned on
| demand would quietly reopen it to everyone the identity provider will
| authenticate. The "does not create" tests are the ones to keep.
|
*/

use App\Enums\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Give the instance Authelia credentials, as a configured deployment has.
 */
function configureAuthelia(): void
{
    config()->set('services.authelia', [
        'base_url' => 'https://auth.app.jez.me',
        'client_id' => 'prompt-queue',
        'client_secret' => 'secret',
        'redirect' => 'https://queue.test/auth/authelia/callback',
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function autheliaUser(array $attributes = []): SocialiteUser
{
    return SocialiteUser::fake(array_merge([
        'id' => 'authelia-sub-1',
        'name' => 'Jez',
        'email' => 'jez@example.com',
        'email_verified' => true,
    ], $attributes));
}

test('the redirect route sends the browser to authelia', function () {
    configureAuthelia();

    $response = $this->get(route('sso.redirect', SsoProvider::Authelia));

    $response->assertRedirectContains('https://auth.app.jez.me/api/oidc/authorization');

    $target = $response->headers->get('Location');

    expect($target)
        ->toContain('client_id=prompt-queue')
        ->toContain(urlencode('openid profile email groups'));
});

test('the sso routes are hidden when no credentials are configured', function (string $routeName) {
    config()->set('services.authelia.client_id', null);
    config()->set('services.authelia.client_secret', null);

    $this->get(route($routeName, SsoProvider::Authelia))->assertNotFound();
})->with(['sso.redirect', 'sso.callback']);

test('an unknown provider is not routable', function () {
    $this->get('/auth/okta/redirect')->assertNotFound();
});

test('a known identity logs its user straight in', function () {
    configureAuthelia();

    $user = User::factory()->create(['email' => 'filed-under-something-else@example.com']);
    UserIdentity::factory()->forUser($user)->withSubject('authelia-sub-1')->create();

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser());

    $response = $this->get(route('sso.callback', SsoProvider::Authelia));

    $response->assertRedirect(route('prompts.index'));
    $this->assertAuthenticatedAs($user);

    /* The email moved at the provider; the link is on the subject, so it holds
       and the stored copy is refreshed rather than duplicated. */
    expect(UserIdentity::count())->toBe(1);
    $this->assertDatabaseHas('user_identities', [
        'user_id' => $user->id,
        'provider_user_id' => 'authelia-sub-1',
        'email' => 'jez@example.com',
    ]);
});

test('a first login links to the existing user with that email', function () {
    configureAuthelia();

    $user = User::factory()->create(['email' => 'jez@example.com']);

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser());

    $response = $this->get(route('sso.callback', SsoProvider::Authelia));

    $response->assertRedirect(route('prompts.index'));
    $this->assertAuthenticatedAs($user);

    $this->assertDatabaseHas('user_identities', [
        'user_id' => $user->id,
        'provider' => SsoProvider::Authelia->value,
        'provider_user_id' => 'authelia-sub-1',
    ]);
});

test('the email match ignores case, as the login form does', function () {
    configureAuthelia();

    $user = User::factory()->create(['email' => 'jez@example.com']);

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser(['email' => 'JEZ@Example.com']));

    $this->get(route('sso.callback', SsoProvider::Authelia));

    $this->assertAuthenticatedAs($user);
});

test('an unknown authelia account is refused rather than provisioned', function () {
    configureAuthelia();

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser(['email' => 'stranger@example.com']));

    $response = $this->get(route('sso.callback', SsoProvider::Authelia));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('ssoError');

    $this->assertGuest();
    expect(User::count())->toBe(0)
        ->and(UserIdentity::count())->toBe(0);
});

test('an unverified email at the provider does not link', function () {
    configureAuthelia();

    User::factory()->create(['email' => 'jez@example.com']);

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser(['email_verified' => false]));

    $this->get(route('sso.callback', SsoProvider::Authelia))
        ->assertRedirect(route('login'));

    $this->assertGuest();
    expect(UserIdentity::count())->toBe(0);
});

test('a stored subject beats a matching email on another account', function () {
    configureAuthelia();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    UserIdentity::factory()->forUser($owner)->withSubject('authelia-sub-1')->create();

    $impostor = User::factory()->create(['email' => 'jez@example.com']);

    Socialite::fake(SsoProvider::Authelia->value, autheliaUser());

    $this->get(route('sso.callback', SsoProvider::Authelia));

    $this->assertAuthenticatedAs($owner);
    expect(auth()->id())->not->toBe($impostor->id);
});

test('a declined consent comes back cleanly', function () {
    configureAuthelia();

    User::factory()->create(['email' => 'jez@example.com']);

    $response = $this->get(route('sso.callback', SsoProvider::Authelia).'?error=access_denied');

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('ssoError');
    $this->assertGuest();
});

test('a callback with no matching state does not leak the exception', function () {
    configureAuthelia();

    /* No fake and no session state, so Socialite rejects the callback before
       it can reach the network. The user should still land on the login
       screen rather than an exception page. */
    $response = $this->get(route('sso.callback', SsoProvider::Authelia));

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('ssoError');
    $this->assertGuest();
});

test('the login screen offers authelia once it is configured', function () {
    configureAuthelia();

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Login')
            ->has('ssoProviders', 1)
            ->where('ssoProviders.0.name', 'authelia')
            ->where('ssoProviders.0.label', 'Authelia')
        );
});

test('the login screen offers nothing when authelia is not configured', function () {
    config()->set('services.authelia.client_id', null);
    config()->set('services.authelia.client_secret', null);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/Login')
            ->has('ssoProviders', 0)
        );
});

test('half-configured credentials do not offer a button that cannot work', function () {
    configureAuthelia();
    config()->set('services.authelia.client_secret', null);

    expect(SsoProvider::Authelia->isConfigured())->toBeFalse()
        ->and(SsoProvider::enabled())->toBe([]);
});

test('hiding the login form takes the password form off the page', function () {
    configureAuthelia();
    config()->set('sso.hide_login_form', true);

    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('showPasswordLogin', false)
            ->has('ssoProviders', 1)
        );
});

test('hiding the login form also refuses the password endpoint', function () {
    configureAuthelia();
    config()->set('sso.hide_login_form', true);

    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('ssoError');

    /* The point of the setting: hiding the form without this would only look
       like it enforced single sign-on. */
    $this->assertGuest();
});

test('hiding the login form does nothing without a provider to fall back on', function () {
    config()->set('services.authelia.client_id', null);
    config()->set('services.authelia.client_secret', null);
    config()->set('sso.hide_login_form', true);

    $user = User::factory()->create();

    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('showPasswordLogin', true));

    /* No identity provider means no other way in, so the setting must not be
       able to lock the instance. */
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('prompts.index'));

    $this->assertAuthenticatedAs($user);
});

test('password login is untouched while the form is shown', function () {
    configureAuthelia();
    config()->set('sso.hide_login_form', false);

    $user = User::factory()->create();

    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('showPasswordLogin', true));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('prompts.index'));

    $this->assertAuthenticatedAs($user);
});

test('an authenticated user is bounced off the sso routes', function () {
    configureAuthelia();

    $this->actingAs(User::factory()->create())
        ->get(route('sso.redirect', SsoProvider::Authelia))
        ->assertRedirect();
});
