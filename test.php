<?php
// Root directory
$rootDir = __DIR__;

// No IP restriction - allow all access for testing
// Remove or comment out the IP check

function listDir(string $dir, int $level = 0): void
{
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        echo str_repeat('  ', $level);

        if (is_dir($path)) {
            echo "[DIR]  $item\n";
            listDir($path, $level + 1);
        } else {
            echo "[FILE] $item\n";
        }
    }
}

header('Content-Type: text/plain');
listDir($rootDir);
?>
