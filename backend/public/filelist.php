<?php
// Simple text-based file listing - no styling, just raw output
header('Content-Type: text/plain');

// Find CazaCom root
$cazacomRoot = dirname(__DIR__, 2);
echo "CazaCom Root: " . $cazacomRoot . "\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 60) . "\n\n";

function listAllFiles($dir, $prefix = '') {
    $items = scandir($dir);
    $items = array_diff($items, ['.', '..']);
    sort($items);
    
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            echo $prefix . "📁 {$item}/\n";
            listAllFiles($path, $prefix . '  ');
        } else {
            $size = filesize($path);
            echo $prefix . "📄 {$item} (" . number_format($size) . " bytes)\n";
        }
    }
}

listAllFiles($cazacomRoot);
echo "\n" . str_repeat('=', 60) . "\n";
echo "Listing complete.\n";
?>
