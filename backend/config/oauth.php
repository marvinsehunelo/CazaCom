<?php
// cazacom/config/oauth.php

return [
    'issuer' => getenv('OAUTH_ISSUER') ?: 'https://cazacom-production.up.railway.app',
    'audience' => getenv('OAUTH_AUDIENCE') ?: 'https://api.cazacom.co.bw',
    'token_lifetime' => 300, // 5 minutes
    'refresh_token_lifetime' => 86400, // 24 hours
    'allowed_scopes' => [
        'read_balance',
        'initiate_payment',
        'airtime_purchase',
        'read_transactions'
    ]
];
