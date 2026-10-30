<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Device Identification
    |--------------------------------------------------------------------------
    |
    | Choose how the current request is matched to a stored device.
    | - "cookie": A random token stored in a long-lived cookie (recommended).
    |   Requests without a session (e.g. stateless APIs) fall back to ['user_agent'].
    | - An array of attributes that must all match: location, ip_address, user_agent.
    |   Examples: ['user_agent'], ['location', 'user_agent'], ['ip_address', 'user_agent']
    |   The location attribute requires DeviceCreator::resolveLocationUsing().
    |
    */

    'identify_by' => 'cookie',

    /*
    |--------------------------------------------------------------------------
    | Credential Key
    |--------------------------------------------------------------------------
    |
    | The credential key used to identify the user (for attempting/failed events).
    | Typically 'email' for Laravel's default authentication.
    |
    */

    'credential_key' => 'email',

    /*
    |--------------------------------------------------------------------------
    | Device Cookie
    |--------------------------------------------------------------------------
    |
    | The cookie used by the "cookie" identification strategy. The lifetime is
    | in minutes and is renewed on every request. Browsers cap cookies at 400 days.
    |
    */

    'cookie' => [
        'name' => 'user_device',

        'lifetime' => 576000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    |
    | Choose which authentication events should trigger the package listeners.
    | - authenticated: Saves/updates the device and sends new login notification
    | - attempting: Track attempts, notify when new device
    | - failed: Track failures, notify when new device
    |
    */

    'events' => [
        'failed' => true,

        'attempting' => false,

        'authenticated' => true,
    ],

];
