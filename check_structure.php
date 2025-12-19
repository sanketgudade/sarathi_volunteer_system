<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Check Directory Structure</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .directory { font-family: monospace; background: #f0f0f0; padding: 2px 5px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>📁 Directory Structure Check</h2>";

// Expected structure
$expected_folders = [
    'assets/uploads/passport',
    'assets/uploads/aadhaar', 
    'assets/uploads/certificate',
    'assets/uploads/documents',
    'assets/uploads/temp'
];

$base_path = __DIR__;

echo "<h3>Checking uploads structure:</h3>";
echo "<ul>";

foreach ($expected_folders as $folder) {
    $full_path = $base_path . '/' . $folder;
    
    if (is_dir($full_path)) {
        echo "<li><span class='success'>✓</span> <span class='directory'>/$folder</span> - Exists</li>";
        
        // Check writable
        $test_file = $full_path . '/test.txt';
        if (file_put_contents($test_file, 'test')) {
            unlink($test_file);
            echo "<li style='margin-left: 20px;'><span class='success'>✓</span> Writable</li>";
        } else {
            echo "<li style='margin-left: 20px;'><span class='error'>✗</span> Not writable</li>";
        }
        
        // Check security files
        if (file_exists($full_path . '/.htaccess')) {
            echo "<li style='margin-left: 20px;'><span class='success'>✓</span> .htaccess exists</li>";
        }
        
    } else {
        echo "<li><span class='error'>✗</span> <span class='directory'>/$folder</span> - Missing</li>";
    }
}

echo "</ul>";

// Show actual files in uploads
echo "<h3>Current files in uploads:</h3>";
function showFiles($dir, $prefix = '') {
    $files = scandir($dir);
    $output = '';
    
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $path = $dir . '/' . $file;
        $is_dir = is_dir($path);
        
        $output .= $prefix . ($is_dir ? '📁 ' : '📄 ') . $file . "\n";
        
        if ($is_dir && in_array($file, ['passport', 'aadhaar', 'certificate', 'documents', 'temp'])) {
            $output .= showFiles($path, $prefix . '    ');
        }
    }
    
    return $output;
}

$uploads_dir = $base_path . '/assets/uploads';
if (is_dir($uploads_dir)) {
    echo "<pre>" . showFiles($uploads_dir) . "</pre>";
} else {
    echo "<p class='error'>Uploads directory not found!</p>";
}

echo "</body></html>";
?>