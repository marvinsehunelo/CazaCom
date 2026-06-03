<?php
// CazaCom/backend/public/test.php
// This file is accessible at: https://cazacom-production.up.railway.app/test.php

header('Content-Type: text/html');

echo "<!DOCTYPE html>
<html>
<head>
    <title>CazaCom - System Check</title>
    <style>
        body {
            background: #000;
            color: #fff;
            font-family: 'Space Grotesk', monospace;
            padding: 30px;
        }
        h1 { color: #00ff00; border-left: 3px solid #00ff00; padding-left: 15px; }
        .section { 
            background: #0a0a0a; 
            border: 1px solid #222; 
            margin: 20px 0; 
            padding: 20px;
        }
        .success { color: #00ff00; }
        .error { color: #ff4444; }
        .info { color: #00aaff; }
        pre { background: #111; padding: 15px; overflow: auto; font-size: 12px; }
    </style>
</head>
<body>
    <h1>🚀 CazaCom System Check</h1>
    <div class='section'>
        <h2>PHP Information</h2>
        <pre>";

echo "PHP Version: " . phpversion() . "\n";
echo "Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n";
echo "Current File: " . __FILE__ . "\n";
echo "Working Directory: " . getcwd() . "\n";

echo "</pre></div>";

// Check directory structure
echo "<div class='section'><h2>Directory Structure</h2><pre>";

function listDirSimple($dir, $prefix = '', $depth = 0) {
    if ($depth > 2) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            echo $prefix . "📁 {$file}/\n";
            listDirSimple($path, $prefix . '  ', $depth + 1);
        } else {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                echo $prefix . "📄 {$file}\n";
            }
        }
    }
}

$rootPath = __DIR__;
echo "Current directory: {$rootPath}\n\n";
listDirSimple($rootPath);

echo "</pre></div>";

// Check required files
echo "<div class='section'><h2>Required Files Check</h2><pre>";

$requiredFiles = [
    'login.php',
    'logout.php',
    'index.php',
    'user/user_dashboard.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "<span class='success'>✓</span> {$file}\n";
    } else {
        echo "<span class='error'>✗</span> {$file} (missing)\n";
    }
}

echo "</pre></div>";

// Check config directory
echo "<div class='section'><h2>Configuration Files</h2><pre>";

$configPath = dirname(__DIR__) . '/config';
if (is_dir($configPath)) {
    echo "Config directory found at: {$configPath}\n";
    $configFiles = scandir($configPath);
    foreach ($configFiles as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            echo "  ✓ {$file}\n";
        }
    }
} else {
    echo "<span class='error'>✗ Config directory not found at: {$configPath}</span>\n";
}

echo "</pre></div>";

// Session test
echo "<div class='section'><h2>Session Test</h2><pre>";
session_start();
$_SESSION['test'] = 'working';
echo "Session ID: " . session_id() . "\n";
echo "Test value set: " . $_SESSION['test'] . "\n";
echo "<span class='success'>✓ Sessions are working</span>\n";
echo "</pre></div>";

// Links to important pages
echo "<div class='section'><h2>Application Links</h2>
<ul>
    <li><a href='login.php' style='color:#00ff00'>→ Login Page</a></li>
    <li><a href='user/user_dashboard.php' style='color:#00ff00'>→ User Dashboard</a></li>
</ul>
</div>";

echo "</body></html>";
?>
