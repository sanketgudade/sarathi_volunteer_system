<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Move Files to Correct Folders</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .success { color: green; background: #d4edda; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .error { color: red; background: #f8d7da; padding: 10px; margin: 5px 0; border-radius: 5px; }
        .info { color: blue; background: #d1ecf1; padding: 10px; margin: 5px 0; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        button { background: #2563eb; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>📁 Move Files to Correct Folders</h1>";

$base_path = __DIR__ . '/../../';
$uploads_path = $base_path . 'assets/uploads/';

// Scan what files exist
echo "<h3>Files in uploads folder:</h3>";
$all_files = scandir($uploads_path);
echo "<ul>";
foreach ($all_files as $file) {
    if ($file != '.' && $file != '..' && !is_dir($uploads_path . $file)) {
        $full_path = $uploads_path . $file;
        echo "<li>{$file} - " . filesize($full_path) . " bytes</li>";
    }
}
echo "</ul>";

// Move files if form submitted
if (isset($_POST['move'])) {
    echo "<div class='info'>Moving files...</div>";
    
    $moved = 0;
    $errors = 0;
    
    foreach ($all_files as $file) {
        if ($file == '.' || $file == '..') continue;
        
        $source = $uploads_path . $file;
        if (is_dir($source)) continue;
        
        // Determine destination folder based on filename
        if (strpos($file, 'passport') !== false) {
            $dest_folder = $uploads_path . 'passport/';
            $new_path = $dest_folder . $file;
        } elseif (strpos($file, 'aadhaar') !== false) {
            $dest_folder = $uploads_path . 'aadhaar/';
            $new_path = $dest_folder . $file;
        } elseif (strpos($file, 'certificate') !== false) {
            $dest_folder = $uploads_path . 'certificate/';
            $new_path = $dest_folder . $file;
        } else {
            $dest_folder = $uploads_path . 'documents/';
            $new_path = $dest_folder . $file;
        }
        
        // Create folder if it doesn't exist
        if (!is_dir($dest_folder)) {
            mkdir($dest_folder, 0777, true);
        }
        
        // Move file
        if (rename($source, $new_path)) {
            echo "<div class='success'>✓ Moved: {$file} → " . basename($dest_folder) . "/</div>";
            $moved++;
        } else {
            echo "<div class='error'>✗ Failed to move: {$file}</div>";
            $errors++;
        }
    }
    
    echo "<div class='info'>Total moved: {$moved}, Errors: {$errors}</div>";
}

// Update database paths
if (isset($_POST['update_db'])) {
    require_once 'config/config.php';
    $conn = getDBConnection();
    
    echo "<div class='info'>Updating database paths...</div>";
    
    // Update record ID 1
    $update1 = "UPDATE volunteer_requests SET 
                passport_photo = 'assets/uploads/passport/passport_1765892931_ecc19875176b273f.png',
                aadhaar_card = 'assets/uploads/aadhaar/aadhaar_1765892931_6fd3323521c07094.jpeg',
                school_certificate = 'assets/uploads/certificate/certificate_1765892931_ca228d63786fbbfb.jpg'
                WHERE id = 1";
    
    if (mysqli_query($conn, $update1)) {
        echo "<div class='success'>✅ Updated record ID 1</div>";
    } else {
        echo "<div class='error'>❌ Failed to update ID 1: " . mysqli_error($conn) . "</div>";
    }
    
    // Check if files exist for record ID 12
    $file12_passport = $uploads_path . 'passport/passport_1765903717_ca4b440b.png';
    $file12_aadhaar = $uploads_path . 'aadhaar/aadhaar_1765903717_ae846df1.png';
    $file12_cert = $uploads_path . 'certificate/certificate_1765903717_39dec848.png';
    
    if (!file_exists($file12_passport)) {
        echo "<div class='error'>❌ File missing: passport_1765903717_ca4b440b.png</div>";
    }
    if (!file_exists($file12_aadhaar)) {
        echo "<div class='error'>❌ File missing: aadhaar_1765903717_ae846df1.png</div>";
    }
    if (!file_exists($file12_cert)) {
        echo "<div class='error'>❌ File missing: certificate_1765903717_39dec848.png</div>";
    }
    
    mysqli_close($conn);
}

echo "<form method='POST' style='margin: 20px 0;'>
        <button type='submit' name='move'>📂 Move Files to Correct Folders</button>
        <button type='submit' name='update_db' style='margin-left: 10px; background: #10b981;'>🔄 Update Database Paths</button>
      </form>";

// Show current folder structure
echo "<h3>Current Folder Structure:</h3>";
function showFolder($path, $prefix = '') {
    $items = scandir($path);
    $output = '';
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        
        $item_path = $path . '/' . $item;
        $is_dir = is_dir($item_path);
        
        $output .= $prefix . ($is_dir ? '📁 ' : '📄 ') . $item . "\n";
        
        if ($is_dir && in_array($item, ['passport', 'aadhaar', 'certificate', 'documents'])) {
            $output .= showFolder($item_path, $prefix . '    ');
        }
    }
    
    return $output;
}

echo "<pre style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>" . 
     showFolder($uploads_path) . 
     "</pre>";

echo "</body></html>";
?>