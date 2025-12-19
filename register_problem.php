<?php
session_start();
require_once 'config/config.php';

$conn = getDBConnection();
$error = $success = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reporter_name = trim($_POST['reporter_name'] ?? '');
    $reporter_email = trim($_POST['reporter_email'] ?? '');
    $reporter_phone = trim($_POST['reporter_phone'] ?? '');
    $problem_title = trim($_POST['problem_title'] ?? '');
    $problem_description = trim($_POST['problem_description'] ?? '');
    $area_location = trim($_POST['area_location'] ?? '');
    $exact_location = trim($_POST['exact_location'] ?? '');
    $google_map_link = trim($_POST['google_map_link'] ?? '');
    $problem_category = $_POST['problem_category'] ?? 'other';
    $urgency_level = $_POST['urgency_level'] ?? 'medium';
    $additional_info = trim($_POST['additional_info'] ?? '');
    
    // Extract latitude and longitude from Google Maps link if provided
    $latitude = null;
    $longitude = null;
    
    if (!empty($google_map_link)) {
        // Try to extract coordinates from Google Maps URL
        if (preg_match('/@([-0-9.]+),([-0-9.]+)/', $google_map_link, $matches)) {
            $latitude = $matches[1];
            $longitude = $matches[2];
        }
    }
    
    // Handle image uploads
    $uploaded_images = [];
    if (!empty($_FILES['problem_images']['name'][0])) {
        $image_dir = 'uploads/society_problems/';
        if (!is_dir($image_dir)) {
            mkdir($image_dir, 0777, true);
        }
        
        foreach ($_FILES['problem_images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['problem_images']['error'][$key] === UPLOAD_ERR_OK) {
                $filename = uniqid() . '_' . basename($_FILES['problem_images']['name'][$key]);
                $target_file = $image_dir . $filename;
                
                // Check file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $file_ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                
                if (in_array($file_ext, $allowed_types) && 
                    $_FILES['problem_images']['size'][$key] < 5 * 1024 * 1024) { // 5MB limit
                    
                    if (move_uploaded_file($tmp_name, $target_file)) {
                        $uploaded_images[] = $target_file;
                    }
                }
            }
        }
    }
    
    $images_json = !empty($uploaded_images) ? json_encode($uploaded_images) : null;
    
    // Validate required fields
    if (empty($reporter_name) || empty($problem_title) || empty($problem_description) || empty($area_location)) {
        $error = "Please fill in all required fields (Name, Problem Title, Description, and Area Location)";
    } elseif (!empty($reporter_email) && !filter_var($reporter_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        // Insert problem into database
        $sql = "INSERT INTO society_problems (reporter_name, reporter_email, reporter_phone, problem_title, 
                problem_description, area_location, exact_location, google_map_link, latitude, longitude, 
                problem_category, urgency_level, images, additional_info, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported')";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssddssss", 
            $reporter_name, $reporter_email, $reporter_phone, $problem_title, 
            $problem_description, $area_location, $exact_location, $google_map_link, 
            $latitude, $longitude, $problem_category, $urgency_level, $images_json, $additional_info);
        
        if (mysqli_stmt_execute($stmt)) {
            $problem_id = mysqli_insert_id($conn);
            $success = "✅ Problem reported successfully! Your reference ID: SP-" . str_pad($problem_id, 6, '0', STR_PAD_LEFT) . 
                      "<br>Our team will review it and assign to volunteers soon.";
            
            // Clear form
            $_POST = [];
        } else {
            $error = "Failed to report problem: " . mysqli_error($conn);
        }
        
        mysqli_stmt_close($stmt);
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Community Problem - Sarathi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #06D6A0;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background: var(--card-bg);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header-section h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .header-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .form-container {
            padding: 40px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
        
        input, select, textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            background: white;
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .image-upload {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }
        
        .image-upload:hover {
            border-color: var(--primary);
            background: #f0f9ff;
        }
        
        .image-upload i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .preview-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            height: 100px;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--danger);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            border: none;
        }
        
        .urgency-badges {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        
        .urgency-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .urgency-low {
            background: #d1fae5;
            color: #065f46;
        }
        
        .urgency-medium {
            background: #fef3c7;
            color: #92400e;
        }
        
        .urgency-high {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .urgency-emergency {
            background: #fecaca;
            color: #7f1d1d;
            border: 2px solid var(--danger);
        }
        
        .urgency-badge.selected {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .category-icons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .category-option {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--light-bg);
        }
        
        .category-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .category-option.selected {
            border-color: var(--primary);
            background: #f0f9ff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }
        
        .category-option i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 8px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            grid-column: span 2;
        }
        
        .btn {
            padding: 16px 32px;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.2);
        }
        
        .btn-secondary {
            background: var(--light-bg);
            color: var(--text-dark);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: white;
            border-color: var(--primary);
        }
        
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            grid-column: span 2;
        }
        
        .info-box h3 {
            color: var(--primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box ul {
            list-style: none;
            padding-left: 0;
        }
        
        .info-box li {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .info-box li i {
            color: var(--secondary);
            margin-top: 3px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .form-actions {
                grid-column: span 1;
                flex-direction: column;
            }
            
            .header-section {
                padding: 30px 20px;
            }
            
            .form-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <h1><i class="fas fa-exclamation-triangle"></i> Report Community Problem</h1>
            <p>Help us make our community better by reporting issues that need attention. We'll coordinate with volunteers to resolve them.</p>
        </div>
        
        <div class="form-container">
            <?php if(!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> Important Information</h3>
                <ul>
                    <li><i class="fas fa-check"></i> Please provide accurate location details for faster resolution</li>
                    <li><i class="fas fa-check"></i> Upload clear photos to help volunteers understand the problem</li>
                    <li><i class="fas fa-check"></i> Your contact information will be kept confidential</li>
                    <li><i class="fas fa-check"></i> Problems are typically addressed within 2-7 days</li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="problemForm">
                <div class="form-grid">
                    <!-- Reporter Information -->
                    <div class="form-group">
                        <label class="required">Your Name</label>
                        <input type="text" name="reporter_name" required 
                               placeholder="Enter your full name"
                               value="<?php echo htmlspecialchars($_POST['reporter_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="reporter_email" 
                               placeholder="your@email.com"
                               value="<?php echo htmlspecialchars($_POST['reporter_email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="reporter_phone" 
                               placeholder="XXXXXXXXXX"
                               value="<?php echo htmlspecialchars($_POST['reporter_phone'] ?? ''); ?>">
                    </div>
                    
                    <!-- Problem Details -->
                    <div class="form-group full-width">
                        <label class="required">Problem Title</label>
                        <input type="text" name="problem_title" required 
                               placeholder="Brief description of the problem (e.g., Garbage accumulation in park)"
                               value="<?php echo htmlspecialchars($_POST['problem_title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="required">Detailed Description</label>
                        <textarea name="problem_description" required 
                                  placeholder="Describe the problem in detail. Include when you noticed it, how it's affecting the community, etc."><?php echo htmlspecialchars($_POST['problem_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Location Details -->
                    <div class="form-group">
                        <label class="required">Area/Location</label>
                        <input type="text" name="area_location" required 
                               placeholder="e.g., Central Park, Sector 5"
                               value="<?php echo htmlspecialchars($_POST['area_location'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Exact Location (Landmark)</label>
                        <input type="text" name="exact_location" 
                               placeholder="e.g., Near the main gate, behind community hall"
                               value="<?php echo htmlspecialchars($_POST['exact_location'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Google Maps Link</label>
                        <input type="url" name="google_map_link" 
                               placeholder="https://maps.google.com/?q=latitude,longitude"
                               value="<?php echo htmlspecialchars($_POST['google_map_link'] ?? ''); ?>">
                        <small style="color: var(--text-light); margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> You can share location from Google Maps app
                        </small>
                    </div>
                    
                    <!-- Problem Category -->
                    <div class="form-group full-width">
                        <label class="required">Problem Category</label>
                        <div class="category-icons" id="categorySelector">
                            <div class="category-option" data-value="garbage">
                                <i class="fas fa-trash"></i>
                                <div>Garbage/Cleanup</div>
                            </div>
                            <div class="category-option" data-value="infrastructure">
                                <i class="fas fa-road"></i>
                                <div>Infrastructure</div>
                            </div>
                            <div class="category-option" data-value="safety">
                                <i class="fas fa-shield-alt"></i>
                                <div>Safety Issue</div>
                            </div>
                            <div class="category-option" data-value="water">
                                <i class="fas fa-tint"></i>
                                <div>Water Problem</div>
                            </div>
                            <div class="category-option" data-value="electricity">
                                <i class="fas fa-bolt"></i>
                                <div>Electricity</div>
                            </div>
                            <div class="category-option" data-value="health">
                                <i class="fas fa-heartbeat"></i>
                                <div>Health Hazard</div>
                            </div>
                            <div class="category-option" data-value="education">
                                <i class="fas fa-school"></i>
                                <div>Education</div>
                            </div>
                            <div class="category-option" data-value="other">
                                <i class="fas fa-question-circle"></i>
                                <div>Other</div>
                            </div>
                        </div>
                        <input type="hidden" name="problem_category" id="problemCategory" value="<?php echo $_POST['problem_category'] ?? 'other'; ?>" required>
                    </div>
                    
                    <!-- Urgency Level -->
                    <div class="form-group full-width">
                        <label class="required">Urgency Level</label>
                        <div class="urgency-badges" id="urgencySelector">
                            <div class="urgency-badge urgency-low" data-value="low">Low Priority</div>
                            <div class="urgency-badge urgency-medium" data-value="medium">Medium Priority</div>
                            <div class="urgency-badge urgency-high" data-value="high">High Priority</div>
                            <div class="urgency-badge urgency-emergency" data-value="emergency">Emergency</div>
                        </div>
                        <input type="hidden" name="urgency_level" id="urgencyLevel" value="<?php echo $_POST['urgency_level'] ?? 'medium'; ?>" required>
                    </div>
                    
                    <!-- Image Upload -->
                    <div class="form-group full-width">
                        <label>Upload Photos (Optional but helpful)</label>
                        <div class="image-upload" onclick="document.getElementById('imageInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3>Click to upload photos</h3>
                            <p>Drag & drop or click to upload images (Max 5 images, 5MB each)</p>
                            <p style="color: var(--text-light); margin-top: 10px;">
                                <i class="fas fa-camera"></i> Clear photos help volunteers understand the issue better
                            </p>
                        </div>
                        <input type="file" id="imageInput" name="problem_images[]" multiple 
                               accept="image/*" style="display: none;" onchange="handleImageUpload(event)">
                        
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="form-group full-width">
                        <label>Additional Information</label>
                        <textarea name="additional_info" 
                                  placeholder="Any additional details, suggestions, or important information"><?php echo htmlspecialchars($_POST['additional_info'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Problem Report
                        </button>
                        <a href="index.html" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Category Selection
        const categoryOptions = document.querySelectorAll('.category-option');
        const categoryInput = document.getElementById('problemCategory');
        
        categoryOptions.forEach(option => {
            option.addEventListener('click', () => {
                categoryOptions.forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
                categoryInput.value = option.dataset.value;
            });
            
            // Set initial selection
            if (option.dataset.value === categoryInput.value) {
                option.classList.add('selected');
            }
        });
        
        // Urgency Selection
        const urgencyBadges = document.querySelectorAll('.urgency-badge');
        const urgencyInput = document.getElementById('urgencyLevel');
        
        urgencyBadges.forEach(badge => {
            badge.addEventListener('click', () => {
                urgencyBadges.forEach(b => b.classList.remove('selected'));
                badge.classList.add('selected');
                urgencyInput.value = badge.dataset.value;
            });
            
            // Set initial selection
            if (badge.dataset.value === urgencyInput.value) {
                badge.classList.add('selected');
            }
        });
        
        // Image Upload Preview
        const imagePreview = document.getElementById('imagePreview');
        let uploadedImages = [];
        
        function handleImageUpload(event) {
            const files = event.target.files;
            uploadedImages = [];
            
            // Clear previous preview
            imagePreview.innerHTML = '';
            
            // Limit to 5 images
            const maxImages = Math.min(files.length, 5);
            
            for (let i = 0; i < maxImages; i++) {
                const file = files[i];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item';
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage(${i})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    imagePreview.appendChild(previewItem);
                    
                    uploadedImages.push({
                        file: file,
                        preview: e.target.result
                    });
                };
                
                reader.readAsDataURL(file);
            }
        }
        
        function removeImage(index) {
            uploadedImages.splice(index, 1);
            updateImagePreview();
            
            // Create new FileList for the form
            const dataTransfer = new DataTransfer();
            uploadedImages.forEach(img => dataTransfer.items.add(img.file));
            document.getElementById('imageInput').files = dataTransfer.files;
        }
        
        function updateImagePreview() {
            imagePreview.innerHTML = '';
            uploadedImages.forEach((img, index) => {
                const previewItem = document.createElement('div');
                previewItem.className = 'preview-item';
                previewItem.innerHTML = `
                    <img src="${img.preview}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removeImage(${index})">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                imagePreview.appendChild(previewItem);
            });
        }
        
        // Form validation
        document.getElementById('problemForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = 'var(--danger)';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!valid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            submitBtn.disabled = true;
        });
        
        // Auto-detect location button
        function detectLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    position => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        document.querySelector('[name="google_map_link"]').value = 
                            `https://maps.google.com/?q=${lat},${lng}`;
                        
                        // Try to get address from coordinates
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.display_name) {
                                    document.querySelector('[name="area_location"]').value = 
                                        data.display_name.split(',').slice(0, 3).join(', ');
                                }
                            });
                    },
                    error => {
                        console.error('Error getting location:', error);
                    }
                );
            }
        }
        
        // Add location button
        const locationInput = document.querySelector('[name="area_location"]');
        const locationWrapper = document.createElement('div');
        locationWrapper.style.display = 'flex';
        locationWrapper.style.gap = '10px';
        locationWrapper.style.marginTop = '10px';
        
        const detectBtn = document.createElement('button');
        detectBtn.type = 'button';
        detectBtn.innerHTML = '<i class="fas fa-location-arrow"></i> Use My Location';
        detectBtn.style.padding = '10px 15px';
        detectBtn.style.background = 'var(--light-bg)';
        detectBtn.style.border = '1px solid var(--border)';
        detectBtn.style.borderRadius = '6px';
        detectBtn.style.cursor = 'pointer';
        detectBtn.onclick = detectLocation;
        
        locationInput.parentNode.insertBefore(locationWrapper, locationInput.nextSibling);
        locationWrapper.appendChild(locationInput.cloneNode(true));
        locationInput.parentNode.removeChild(locationInput);
        locationWrapper.appendChild(detectBtn);
        
        // Rename the cloned input
        locationWrapper.querySelector('input').name = 'area_location';
        locationWrapper.querySelector('input').id = 'area_location';
        
        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            // Set default category if not set
            if (!categoryInput.value) {
                categoryInput.value = 'other';
                document.querySelector('.category-option[data-value="other"]').classList.add('selected');
            }
            
            // Set default urgency if not set
            if (!urgencyInput.value) {
                urgencyInput.value = 'medium';
                document.querySelector('.urgency-badge[data-value="medium"]').classList.add('selected');
            }
        });
    </script>
</body>
</html>