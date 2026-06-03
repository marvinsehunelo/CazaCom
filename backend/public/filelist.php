<?php
// CazaCom/backend/public/filelist.php
// Complete file listing for CazaCom repository

header('Content-Type: text/html');

// Set time limit to unlimited for large directories
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>CazaCom - Complete File Listing</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #000000;
            font-family: 'Space Grotesk', monospace;
            color: #FFFFFF;
            padding: 20px;
        }
        h1 { 
            font-size: 24px; 
            letter-spacing: 2px; 
            border-left: 3px solid #00ff00; 
            padding-left: 15px; 
            margin-bottom: 20px;
        }
        h2 {
            font-size: 18px;
            letter-spacing: 1px;
            margin: 20px 0 10px 0;
            padding: 10px;
            background: rgba(0,255,0,0.1);
            border-left: 2px solid #00ff00;
        }
        .stats {
            background: #0a0a0a;
            border: 1px solid #222;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stat-item {
            display: inline-block;
            margin-right: 30px;
            font-size: 12px;
        }
        .stat-label { color: #888; }
        .stat-value { color: #00ff00; font-weight: bold; font-size: 18px; margin-left: 8px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }
        th {
            text-align: left;
            padding: 10px;
            background: #0a0a0a;
            border-bottom: 1px solid #333;
            color: #00ff00;
        }
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #1a1a1a;
            vertical-align: top;
        }
        tr:hover { background: #0a0a0a; }
        .dir { color: #ffaa00; font-weight: bold; }
        .file { color: #ffffff; }
        .php { color: #66ccff; }
        .js { color: #ffcc66; }
        .css { color: #ff66cc; }
        .json { color: #66ff66; }
        .sql { color: #ff9966; }
        .size { color: #888; font-family: monospace; }
        .date { color: #555; font-size: 11px; }
        .tree { font-family: monospace; font-size: 12px; line-height: 1.6; }
        .tree-item { margin: 2px 0; }
        .tree-folder { color: #ffaa00; }
        .tree-file { color: #66ccff; }
        .folder-icon { color: #ffaa00; margin-right: 5px; }
        .file-icon { color: #888; margin-right: 5px; }
        .summary {
            background: #0a0a0a;
            border-top: 1px solid #333;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <h1>📁 CazaCom - Complete File Listing</h1>";

// Find the CazaCom root directory
$possibleRoots = [
    __DIR__,                           // Current directory
    dirname(__DIR__, 1),               // One level up
    dirname(__DIR__, 2),               // Two levels up (CazaCom root from backend/public)
    dirname(__DIR__, 3),               // Three levels up
    '/app',                            // Railway app directory
    getcwd(),                          // Current working directory
    $_SERVER['DOCUMENT_ROOT'] ?? '',   // Document root
];

$cazacomRoot = null;
foreach ($possibleRoots as $root) {
    if ($root && is_dir($root) && (file_exists($root . '/backend') || basename($root) === 'CazaCom')) {
        $cazacomRoot = $root;
        break;
    }
}

// If not found, use the parent of backend
if (!$cazacomRoot && is_dir(dirname(__DIR__, 2))) {
    $cazacomRoot = dirname(__DIR__, 2);
}

if (!$cazacomRoot) {
    $cazacomRoot = __DIR__;
}

echo "<div class='stats'>
    <div class='stat-item'>📂 Root: <span class='stat-value'>" . htmlspecialchars($cazacomRoot) . "</span></div>
    <div class='stat-item'>📅 Generated: <span class='stat-value'>" . date('Y-m-d H:i:s') . "</span></div>
</div>";

// Function to get file extension class
function getFileClass($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'php': return 'php';
        case 'js': return 'js';
        case 'css': return 'css';
        case 'json': return 'json';
        case 'sql': return 'sql';
        default: return 'file';
    }
}

// Function to format file size
function formatSize($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

// Function to recursively list files with tree view
function listTree($dir, $prefix = '', $isLast = true, &$counters = null) {
    if (!$counters) {
        $counters = ['files' => 0, 'dirs' => 0, 'total_size' => 0];
    }
    
    $items = scandir($dir);
    $items = array_diff($items, ['.', '..']);
    sort($items);
    
    $total = count($items);
    $i = 0;
    $output = '';
    
    foreach ($items as $item) {
        $i++;
        $isLastItem = ($i === $total);
        $path = $dir . '/' . $item;
        $connector = $isLastItem ? '└── ' : '├── ';
        
        if (is_dir($path)) {
            $counters['dirs']++;
            $output .= "<div class='tree-item'>";
            $output .= $prefix . $connector;
            $output .= "<span class='tree-folder'>📁 {$item}/</span>";
            $output .= "</div>";
            
            $newPrefix = $prefix . ($isLastItem ? '    ' : '│   ');
            $output .= listTree($path, $newPrefix, $isLastItem, $counters);
        } else {
            $counters['files']++;
            $size = filesize($path);
            $counters['total_size'] += $size;
            $fileClass = getFileClass($item);
            $output .= "<div class='tree-item'>";
            $output .= $prefix . $connector;
            $output .= "<span class='{$fileClass}'>📄 {$item}</span>";
            $output .= " <span class='size'>(" . formatSize($size) . ")</span>";
            $output .= "</div>";
        }
    }
    
    return $output;
}

// Function to list files in table format
function listTable($dir, $depth = 0, &$counters = null) {
    if (!$counters) {
        $counters = ['files' => 0, 'dirs' => 0, 'total_size' => 0];
    }
    
    $output = '';
    $items = scandir($dir);
    $items = array_diff($items, ['.', '..']);
    sort($items);
    
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
        
        if (is_dir($path)) {
            $counters['dirs']++;
            $output .= "<tr style='background: rgba(255,170,0,0.05);'>
                <td>{$indent}<span class='dir'>📁 {$item}/</span></td>
                <td>DIR</td>
                <td>-</td>
                <td class='date'>" . date('Y-m-d H:i:s', filemtime($path)) . "</td>
            </tr>";
            $output .= listTable($path, $depth + 1, $counters);
        } else {
            $counters['files']++;
            $size = filesize($path);
            $counters['total_size'] += $size;
            $fileClass = getFileClass($item);
            $output .= "<tr>
                <td>{$indent}<span class='{$fileClass}'>📄 {$item}</span></td>
                <td>" . strtoupper(pathinfo($item, PATHINFO_EXTENSION) ?: 'TXT') . "</td>
                <td class='size'>" . formatSize($size) . "</td>
                <td class='date'>" . date('Y-m-d H:i:s', filemtime($path)) . "</td>
            </tr>";
        }
    }
    
    return $output;
}

// Display Tree View
echo "<h2>🌳 Directory Tree View</h2>";
echo "<div class='tree'>";
$counters = ['files' => 0, 'dirs' => 0, 'total_size' => 0];
echo listTree($cazacomRoot, '', true, $counters);
echo "</div>";

// Display Summary
echo "<div class='summary'>
    <strong>📊 Summary:</strong><br>
    📁 Directories: {$counters['dirs']}<br>
    📄 Files: {$counters['files']}<br>
    💾 Total Size: " . formatSize($counters['total_size']) . "
</div>";

// Display Table View (Detailed)
echo "<h2>📋 Detailed File List (Table View)</h2>";
echo "<table>
    <thead>
        <tr><th>File/Directory</th><th>Type</th><th>Size</th><th>Modified</th></tr>
    </thead>
    <tbody>";

$tableCounters = ['files' => 0, 'dirs' => 0, 'total_size' => 0];
echo listTable($cazacomRoot, 0, $tableCounters);

echo "</tbody></table>";

// Display Second Summary
echo "<div class='summary'>
    <strong>📊 Final Summary:</strong><br>
    📁 Total Directories: {$tableCounters['dirs']}<br>
    📄 Total Files: {$tableCounters['files']}<br>
    💾 Total Size: " . formatSize($tableCounters['total_size']) . "<br>
    📂 Root Path: " . htmlspecialchars($cazacomRoot) . "
</div>";

// Show disk usage
echo "<div class='summary'>
    <strong>💿 Disk Usage:</strong><br>
    Free Space: " . formatSize(disk_free_space($cazacomRoot)) . "<br>
    Total Space: " . formatSize(disk_total_space($cazacomRoot)) . "
</div>";

echo "</body></html>";
?>
