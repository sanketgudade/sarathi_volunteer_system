<?php
echo "<h2>File Accessibility Test</h2>";

$files = [
    'passport/passport_1765892931_ecc19875176b273f.png',
    'aadhaar/aadhaar_1765892931_6fd3323521c07094.jpeg',
    'certificate/certificate_1765892931_ca228d63786fbbfb.jpg',
    'passport/passport_1765903717_ca4b440b.png',
    'aadhaar/aadhaar_1765903717_ae846df1.png',
    'certificate/certificate_1765903717_39dec848.png'
];

foreach ($files as $file) {
    $path = "assets/uploads/" . $file;
    echo "<div style='margin: 10px; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>" . $file . "</strong><br>";
    
    if (file_exists($path)) {
        echo "✅ File exists<br>";
        echo "Size: " . filesize($path) . " bytes<br>";
        echo "Permissions: " . substr(sprintf('%o', fileperms($path)), -4) . "<br>";
        
        // Try to display image
        echo "<img src='" . $path . "' style='max-width: 200px; max-height: 150px; margin-top: 5px;'><br>";
    } else {
        echo "❌ File NOT FOUND at: " . $path . "<br>";
        
        // Check parent directory
        $dir = dirname($path);
        if (is_dir($dir)) {
            echo "Directory exists, contents: ";
            $scan = scandir($dir);
            echo implode(", ", array_slice($scan, 2, 5)) . "...<br>";
        } else {
            echo "Directory doesn't exist!<br>";
        }
    }
    echo "</div>";
}
?>