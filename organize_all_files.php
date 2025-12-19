<?php
// check_paths.php - Debug tool to check file paths
$paths = [
    '/sarathi_volunteer_system/assets/uploads/passport/passport_1765892931_ecc19875176b273f.png',
    'assets/uploads/passport/passport_1765892931_ecc19875176b273f.png',
    'C:/xampp/htdocs/sarathi_volunteer_system/assets/uploads/passport/passport_1765892931_ecc19875176b273f.png',
];

echo "<h2>Checking File Paths</h2>";

foreach ($paths as $path) {
    echo "<h3>Testing: " . htmlspecialchars($path) . "</h3>";
    
    // Check if file exists
    if (file_exists($path)) {
        echo "<p style='color: green;'>✓ File exists</p>";
        
        // Get file size
        $size = filesize($path);
        echo "<p>File size: " . $size . " bytes</p>";
        
        // Check if readable
        if (is_readable($path)) {
            echo "<p style='color: green;'>✓ File is readable</p>";
        } else {
            echo "<p style='color: red;'>✗ File is NOT readable (permission issue)</p>";
        }
        
        // Generate URL
        if (strpos($path, 'C:/xampp/htdocs/') === 0) {
            $url = str_replace('C:/xampp/htdocs/', 'http://localhost/', $path);
        } elseif (strpos($path, '/sarathi_volunteer_system/') === 0) {
            $url = 'http://localhost' . $path;
        } else {
            $url = 'http://localhost/sarathi_volunteer_system/' . $path;
        }
        
        echo "<p>URL: <a href='$url' target='_blank'>$url</a></p>";
        
        // Try to display image
        echo "<p>Preview: <img src='$url' style='max-width: 300px; max-height: 200px; border: 1px solid #ccc;' onerror='this.style.display=\"none\";'></p>";
    } else {
        echo "<p style='color: red;'>✗ File does NOT exist</p>";
    }
    
    echo "<hr>";
}

// Check uploads directory permissions
echo "<h2>Checking Uploads Directory</h2>";
$upload_dir = 'C:/xampp/htdocs/sarathi_volunteer_system/assets/uploads/';
echo "<p>Directory: " . $upload_dir . "</p>";

if (is_dir($upload_dir)) {
    echo "<p style='color: green;'>✓ Directory exists</p>";
    
    // Check subdirectories
    $subdirs = ['passport', 'aadhaar', 'certificate'];
    foreach ($subdirs as $subdir) {
        $full_path = $upload_dir . $subdir . '/';
        if (is_dir($full_path)) {
            echo "<p style='color: green;'>✓ Subdirectory '$subdir' exists</p>";
            
            // List files in subdirectory
            $files = scandir($full_path);
            $file_count = count($files) - 2; // subtract . and ..
            echo "<p>Files in '$subdir': $file_count</p>";
            
            if ($file_count > 0) {
                echo "<ul>";
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') {
                        $file_url = 'http://localhost/sarathi_volunteer_system/assets/uploads/' . $subdir . '/' . $file;
                        echo "<li>$file - <a href='$file_url' target='_blank'>View</a></li>";
                    }
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: red;'>✗ Subdirectory '$subdir' does NOT exist</p>";
        }
    }
} else {
    echo "<p style='color: red;'>✗ Uploads directory does NOT exist</p>";
    echo "<p>Create it with: mkdir -p assets/uploads/{passport,aadhaar,certificate}</p>";
}
?>