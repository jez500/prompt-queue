<?php

namespace App\Http\Middleware;

use App\Enums\SsoProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses password sign-in while the login form is hidden.
 *
 * Hiding the form in the UI does not stop anyone posting to Fortify's login
 * route, so without this the setting would only look like it enforced single
 * sign-on. Fortify registers that route itself, which is why this matches on
 * the route name from inside the `web` group rather than being attached to a
 * route definition of ours.
 */
class EnsurePasswordLoginIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('login.store') && ! SsoProvider::passwordLoginEnabled()) {
            return redirect()->route('login')->with('ssoError', __('Password sign-in is disabled on this instance.'));
        }

        return $next($request);
    }
}
