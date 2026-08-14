<?php

namespace App\Enums;

/**
 * The single sign-on providers this instance knows how to talk to.
 *
 * The case value doubles as the Socialite driver name, the `config/services`
 * key and the `{provider}` route segment, so all three stay spelled the same.
 */
enum SsoProvider: string
{
    case Authelia = 'authelia';

    /**
     * The name shown on the login button.
     */
    public function label(): string
    {
        return match ($this) {
            self::Authelia => 'Authelia',
        };
    }

    /**
     * The scopes requested at the authorization endpoint.
     *
     * These match the package defaults. They are repeated here so the set the
     * Authelia client is configured with is stated in this codebase rather
     * than inferred from a vendor file that could change under us.
     *
     * @return array<int, string>
     */
    public function scopes(): array
    {
        return match ($this) {
            self::Authelia => ['openid', 'profile', 'email', 'groups'],
        };
    }

    /**
     * Whether this instance has credentials for the provider.
     *
     * Both halves are required: a client id without a secret cannot complete
     * the token exchange, so offering the button would only dead-end.
     */
    public function isConfigured(): bool
    {
        $config = config('services.'.$this->value);

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null);
    }

    /**
     * The providers this instance can actually offer, in display order.
     *
     * Built as a list rather than filtered, so the keys stay sequential — a
     * gap would make this serialise to the login page as a JSON object
     * instead of an array.
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        $enabled = [];

        foreach (self::cases() as $provider) {
            if ($provider->isConfigured()) {
                $enabled[] = $provider;
            }
        }

        return $enabled;
    }

    /**
     * Whether the email and password form should still be offered.
     *
     * Lives here because the interlock is the whole point: `HIDE_LOGIN_FORM`
     * only bites when a provider is actually available, so setting it on an
     * instance with no working identity provider cannot lock anyone out.
     */
    public static function passwordLoginEnabled(): bool
    {
        if (self::enabled() === []) {
            return true;
        }

        return ! config('sso.hide_login_form');
    }
}
