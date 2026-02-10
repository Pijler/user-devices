<?php

return [

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

];
