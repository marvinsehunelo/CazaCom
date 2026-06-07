<?php
// test_debug.php - Temporary debug file

header('Content-Type: application/json');

echo json_encode([
    'status' => 'debug',
    'cazacom_api_key' => getenv('CAZACOM_API_KEY') ? 'set' : 'not set',
    'php_version' => phpversion(),
    'routes_file_exists' => file_exists(__DIR__ . '/routes/api.php'),
    'current_dir' => __DIR__
]);
