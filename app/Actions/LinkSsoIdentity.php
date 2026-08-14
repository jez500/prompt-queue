<?php

namespace App\Actions;

use App\Enums\SsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Resolves the local user behind a single sign-on login.
 *
 * This deliberately never creates a user. Registration is closed (see
 * config/fortify.php and docs/authentication.md) — accounts are made with
 * `php artisan pq:create-user`, and single sign-on is a way to log into one
 * that already exists, not a way to open the instance to everyone the
 * identity provider will authenticate.
 */
class LinkSsoIdentity
{
    /**
     * The local user for this provider login, or null if nobody matches.
     */
    public function __invoke(SsoProvider $provider, SocialiteUser $socialiteUser): ?User
    {
        $subject = $socialiteUser->getId();

        if (blank($subject)) {
            return null;
        }

        $identity = UserIdentity::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $subject)
            ->first();

        /* The subject is checked before the email, so a known identity always
           wins. Without that ordering, an address reassigned at the provider
           could hand someone a different local user's account. */
        if ($identity !== null) {
            $identity->update([
                'email' => $socialiteUser->getEmail(),
                'name' => $socialiteUser->getName(),
            ]);

            return $identity->user;
        }

        $user = $this->matchByEmail($socialiteUser);

        if ($user === null) {
            return null;
        }

        $user->identities()->create([
            'provider' => $provider,
            'provider_user_id' => $subject,
            'email' => $socialiteUser->getEmail(),
            'name' => $socialiteUser->getName(),
        ]);

        return $user;
    }

    /**
     * The existing user owning this email, if the provider vouches for it.
     */
    private function matchByEmail(SocialiteUser $socialiteUser): ?User
    {
        $email = $socialiteUser->getEmail();

        if (blank($email) || $this->emailIsDisputed($socialiteUser)) {
            return null;
        }

        /* Lowercased to match how the user was stored: Fortify lowercases at
           login and pq:create-user lowercases on the way in. */
        return User::query()
            ->where('email', Str::lower($email))
            ->first();
    }

    /**
     * Whether the provider explicitly said this email is unverified.
     *
     * Only an outright `false` blocks the link. An absent claim is allowed
     * through: not every provider sends one, and refusing those would make
     * first-time linking impossible with no way for an admin to fix it.
     */
    private function emailIsDisputed(SocialiteUser $socialiteUser): bool
    {
        if (! $socialiteUser instanceof AbstractUser) {
            return false;
        }

        return ($socialiteUser->getRaw()['email_verified'] ?? null) === false;
    }
}
