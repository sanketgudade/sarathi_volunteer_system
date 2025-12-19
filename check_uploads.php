<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Checking Uploaded Files</h2>";

$base_path = __DIR__ . '/../../';
$uploads_path = $base_path . 'assets/uploads/';

echo "<p>Uploads path: " . realpath($uploads_path) . "</p>";

// Check all folders
$folders = ['aadhaar', 'passport', 'certificate', 'documents'];

foreach ($folders as $folder) {
    $folder_path = $uploads_path . $folder . '/';
    echo "<h3>Folder: $folder</h3>";
    
    if (is_dir($folder_path)) {
        $files = scandir($folder_path);
        echo "<ul>";
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $full_path = $folder_path . $file;
                $web_path = "assets/uploads/$folder/$file";
                echo "<li>";
                echo "File: $file<br>";
                echo "Size: " . filesize($full_path) . " bytes<br>";
                echo "Path: $full_path<br>";
                echo "Web Path: <a href='$web_path' target='_blank'>$web_path</a><br>";
                
                // Check if it's an image
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    echo "Preview: <br><img src='$web_path' style='max-width: 200px; max-height: 150px;'><br>";
                } else {
                    echo "Type: $ext (Not an image)<br>";
                }
                echo "</li><hr>";
            }
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Folder not found: $folder_path</p>";
    }
}
?>