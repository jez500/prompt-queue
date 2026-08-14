<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hide the password login form
    |--------------------------------------------------------------------------
    |
    | With this on, the login screen offers single sign-on only: no password
    | form, and POST /login is refused. It has no effect unless a provider is
    | actually configured, so setting it on an instance with no working
    | identity provider cannot lock you out.
    |
    | Turning it back off is the escape hatch when the provider is down. There
    | is no in-app override — that is the point.
    |
    */

    'hide_login_form' => env('HIDE_LOGIN_FORM', false),

];
