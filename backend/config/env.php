<?php
// config/env.php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    */
    'APP_ENV'      => 'local',   // local | staging | production
    'APP_DEBUG'    => true,
    'APP_URL'      => 'http://localhost',

    /*
    |--------------------------------------------------------------------------
    | Database Settings
    |--------------------------------------------------------------------------
    */
    'DB_CONNECTION' => 'mysql',
    'DB_HOST'       => '127.0.0.1',
    'DB_PORT'       => 3306,
    'DB_DATABASE'   => 'zuru_bank',
    'DB_USERNAME'   => 'root',
    'DB_PASSWORD'   => '',

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Settings
    |--------------------------------------------------------------------------
    */
    'SMS_API_KEY'     => 'your-sms-api-key-here',
    'SMS_API_SECRET'  => 'your-sms-secret-here',
    'SMS_SENDER_ID'   => 'ZURU',

    /*
    |--------------------------------------------------------------------------
    | VOIP / Call Settings
    |--------------------------------------------------------------------------
    */
    'VOIP_PROVIDER'   => 'twilio',   // or any other provider
    'VOIP_ACCOUNT_SID'=> 'your-voip-sid',
    'VOIP_AUTH_TOKEN' => 'your-voip-token',

    /*
    |--------------------------------------------------------------------------
    | BankBridge / External Banking API
    |--------------------------------------------------------------------------
    */
    'BANKBRIDGE_URL'      => 'https://api.centralbank.com',
    'BANKBRIDGE_CLIENT_ID'=> 'your-client-id',
    'BANKBRIDGE_SECRET'   => 'your-client-secret',
    'BANKBRIDGE_PARTNER'  => 'zuru',

    /*
    |--------------------------------------------------------------------------
    | Router / Satellite Service
    |--------------------------------------------------------------------------
    */
    'ROUTER_API_URL'   => 'https://router-api.example.com',
    'ROUTER_API_TOKEN' => 'router-secret-token',
];
