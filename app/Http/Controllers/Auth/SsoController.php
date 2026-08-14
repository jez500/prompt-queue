<?php

namespace App\Http\Controllers\Auth;

use App\Actions\LinkSsoIdentity;
use App\Enums\SsoProvider;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class SsoController extends Controller
{
    /**
     * Hand the browser off to the identity provider.
     */
    public function redirect(SsoProvider $provider): SymfonyRedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        $driver = Socialite::driver($provider->value);

        /* Only the concrete OAuth2 provider takes scopes; the contract the
           manager returns does not declare them. */
        if ($driver instanceof AbstractProvider) {
            $driver->scopes($provider->scopes());
        }

        return $driver->redirect();
    }

    /**
     * Log the user in from the identity provider's callback.
     */
    public function callback(Request $request, SsoProvider $provider, LinkSsoIdentity $linkIdentity): RedirectResponse
    {
        abort_unless($provider->isConfigured(), 404);

        /* Authelia sends the user back with ?error=access_denied when they
           decline consent. There is no code to exchange, so bail before
           Socialite tries. */
        if ($request->has('error')) {
            return $this->failed(__('Sign-in was cancelled.'));
        }

        try {
            $socialiteUser = Socialite::driver($provider->value)->user();
        } catch (Throwable) {
            /* A mismatched state, an expired code, or the provider being
               unreachable all land here. None of them are worth showing the
               user the exception text for. */
            return $this->failed(__('Could not complete sign-in with :provider.', [
                'provider' => $provider->label(),
            ]));
        }

        $user = $linkIdentity($provider, $socialiteUser);

        if (! $user instanceof User) {
            return $this->failed(__('No :app account is linked to that :provider login. Ask an administrator to create one first.', [
                'app' => config('app.name'),
                'provider' => $provider->label(),
            ]));
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('prompts.index'));
    }

    /**
     * Send the user back to the login screen with an explanation.
     */
    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->with('ssoError', $message);
    }
}
