<?php
// test_env.php - Check all environment variables

header('Content-Type: application/json');

// Try different methods to get the variable
$cazacom_key = getenv('CAZACOM_API_KEY');
$cazacom_key_alt = $_ENV['CAZACOM_API_KEY'] ?? null;
$cazacom_key_server = $_SERVER['CAZACOM_API_KEY'] ?? null;

echo json_encode([
    'getenv_result' => $cazacom_key ?: 'NOT_SET',
    '_ENV_result' => $cazacom_key_alt ?: 'NOT_SET',
    '_SERVER_result' => $cazacom_key_server ?: 'NOT_SET',
    'all_env_vars' => array_keys($_ENV),
    'all_server_vars' => array_keys(array_filter($_SERVER, function($key) {
        return strpos($key, 'RAILWAY') !== false || strpos($key, 'CAZACOM') !== false;
    }, ARRAY_FILTER_USE_KEY)),
    'railway_env' => getenv('RAILWAY_ENVIRONMENT') ?: 'NOT_SET'
], JSON_PRETTY_PRINT);
