<?php
require_once 'config/config.php';

$conn = getDBConnection();

// Check Request ID 14 specifically
$id = 14;
$sql = "SELECT * FROM volunteer_requests WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);

echo "<h2>Checking Request ID: {$id} - {$request['full_name']}</h2>";

// Check document paths
$doc_types = [
    'passport_photo' => 'passport',
    'aadhaar_card' => 'aadhaar',
    'school_certificate' => 'certificate'
];

foreach ($doc_types as $field => $folder) {
    echo "<h3>" . ucfirst($folder) . " Document</h3>";
    
    $path = $request[$field];
    echo "<p>Database Path: <code>" . htmlspecialchars($path) . "</code></p>";
    
    if (empty($path)) {
        echo "<p style='color: orange;'>⚠️ No path in database</p>";
        continue;
    }
    
    // Convert to actual file path
    $base_dir = 'C:/xampp/htdocs/sarathi_volunteer_system/';
    $file_path = $base_dir . $path;
    
    echo "<p>Full Path: <code>" . htmlspecialchars($file_path) . "</code></p>";
    
    if (file_exists($file_path)) {
        echo "<p style='color: green;'>✅ File exists!</p>";
        
        // Generate URL
        $url = 'http://localhost/sarathi_volunteer_system/' . $path;
        echo "<p>URL: <a href='$url' target='_blank'>$url</a></p>";
        echo "<img src='$url' style='max-width: 300px; max-height: 200px; border: 1px solid #ccc;'>";
    } else {
        echo "<p style='color: red;'>❌ File does NOT exist!</p>";
        
        // Try to find the file
        $upload_dir = $base_dir . 'assets/uploads/' . $folder . '/';
        echo "<p>Looking in: <code>" . htmlspecialchars($upload_dir) . "</code></p>";
        
        if (is_dir($upload_dir)) {
            $files = scandir($upload_dir);
            $actual_files = array_diff($files, ['.', '..']);
            
            if (count($actual_files) > 0) {
                echo "<p>Available files in this folder:</p>";
                echo "<ul>";
                foreach ($actual_files as $file) {
                    $file_url = 'http://localhost/sarathi_volunteer_system/assets/uploads/' . $folder . '/' . $file;
                    echo "<li>$file - <a href='$file_url' target='_blank'>View</a> (" . filesize($upload_dir . $file) . " bytes)</li>";
                }
                echo "</ul>";
            }
        }
    }
    echo "<hr>";
}

mysqli_close($conn);
?>