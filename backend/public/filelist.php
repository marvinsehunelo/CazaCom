<?php
// Folder you want to check
$folder = __DIR__ . "/../../../CazaCom"; // Adjust path if needed

// Check if directory exists
if (!is_dir($folder)) {
    die("Folder 'CazaCom' does not exist.");
}

// Recursive function to list all files and folders
function listFiles($dir, $prefix = '') {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === "." || $file === "..") continue;

        $filePath = $dir . "/" . $file;

        if (is_file($filePath)) {
            $size = filesize($filePath);
            $modified = date("Y-m-d H:i:s", filemtime($filePath));
            echo "<li>{$prefix}<strong>$file</strong> — File — Size: {$size} bytes — Modified: $modified</li>";
        } elseif (is_dir($filePath)) {
            echo "<li>{$prefix}<strong>$file</strong> — Directory</li>";
            // Recurse into subdirectory
            listFiles($filePath, $prefix . '&nbsp;&nbsp;&nbsp;'); // Indent subfolder contents
        }
    }
}

echo "<h2>All files in CazaCom folder:</h2>";
echo "<ul>";
listFiles($folder);
echo "</ul>";
?>

