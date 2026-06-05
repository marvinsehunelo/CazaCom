<?php
// config/env.php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Settings
    |--------------------------------------------------------------------------
    */
    'APP_ENV'      => getenv('APP_ENV') ?: 'production',
    'APP_DEBUG'    => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) ?: false,
    'APP_URL'      => getenv('APP_URL') ?: 'https://cazacom-production.up.railway.app',

    /*
    |--------------------------------------------------------------------------
    | API Keys for incoming requests (validating VouchMorph)
    |--------------------------------------------------------------------------
    */
    'API_KEYS' => [
        'vouchmorph' => getenv('VOUCHMORPH_API_KEY'),
        'system' => getenv('SYSTEM_API_KEY')
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth settings
    |--------------------------------------------------------------------------
    */
    'OAUTH_ISSUER' => getenv('OAUTH_ISSUER'),
    'OAUTH_AUDIENCE' => getenv('OAUTH_AUDIENCE'),

    /*
    |--------------------------------------------------------------------------
    | Database Settings - PostgreSQL
    |--------------------------------------------------------------------------
    */
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST'       => getenv('PGHOST') ?: '127.0.0.1',
    'DB_PORT'       => getenv('PGPORT') ?: 5432,
    'DB_DATABASE'   => getenv('PGDATABASE') ?: 'railway',
    'DB_USERNAME'   => getenv('PGUSER') ?: 'postgres',
    'DB_PASSWORD'   => getenv('PGPASSWORD') ?: '',
    
    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Specific Settings
    |--------------------------------------------------------------------------
    */
    'DB_SCHEMA'     => 'public',
    'DB_SSL_MODE'   => getenv('PGSSLMODE') ?: 'require',  // Railway requires SSL
    'DB_SSL_VERIFY' => filter_var(getenv('PGSSLVERIFY'), FILTER_VALIDATE_BOOLEAN) ?: true,

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway Settings
    |--------------------------------------------------------------------------
    */
    'SMS_API_KEY'     => getenv('SMS_API_KEY') ?: 'your-sms-api-key-here',
    'SMS_API_SECRET'  => getenv('SMS_API_SECRET') ?: 'your-sms-secret-here',
    'SMS_SENDER_ID'   => getenv('SMS_SENDER_ID') ?: 'ZURU',

    /*
    |--------------------------------------------------------------------------
    | VOIP / Call Settings
    |--------------------------------------------------------------------------
    */
    'VOIP_PROVIDER'   => getenv('VOIP_PROVIDER') ?: 'twilio',
    'VOIP_ACCOUNT_SID'=> getenv('VOIP_ACCOUNT_SID') ?: 'your-voip-sid',
    'VOIP_AUTH_TOKEN' => getenv('VOIP_AUTH_TOKEN') ?: 'your-voip-token',

    /*
    |--------------------------------------------------------------------------
    | BankBridge / External Banking API
    |--------------------------------------------------------------------------
    */
    'BANKBRIDGE_URL'      => getenv('BANKBRIDGE_URL') ?: 'https://api.centralbank.com',
    'BANKBRIDGE_CLIENT_ID'=> getenv('BANKBRIDGE_CLIENT_ID') ?: 'your-client-id',
    'BANKBRIDGE_SECRET'   => getenv('BANKBRIDGE_SECRET') ?: 'your-client-secret',
    'BANKBRIDGE_PARTNER'  => getenv('BANKBRIDGE_PARTNER') ?: 'zuru',

    /*
    |--------------------------------------------------------------------------
    | Router / Satellite Service
    |--------------------------------------------------------------------------
    */
    'ROUTER_API_URL'   => getenv('ROUTER_API_URL') ?: 'https://router-api.example.com',
    'ROUTER_API_TOKEN' => getenv('ROUTER_API_TOKEN') ?: 'router-secret-token',
];
