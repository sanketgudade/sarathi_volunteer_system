<?php
require_once 'config/config.php';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    // Sanitize inputs
    $full_name = sanitizeInput($_POST['full_name']);
    $age = intval($_POST['age']);
    $gender = sanitizeInput($_POST['gender']);
    $mobile = sanitizeInput($_POST['mobile']);
    $email = sanitizeInput($_POST['email']);
    $education = sanitizeInput($_POST['education']);
    $skills = sanitizeInput($_POST['skills']);
    $ngo_name = sanitizeInput($_POST['ngo_name']);
    $role = sanitizeInput($_POST['role']);
    $message = sanitizeInput($_POST['message']);
    
    // File uploads
    $passport_photo = '';
    $aadhaar_card = '';
    $school_certificate = '';
    
    // Upload passport photo
    if (!empty($_FILES['passport_photo']['name'])) {
        $upload = uploadFile($_FILES['passport_photo'], 'passport');
        if (isset($upload['file_path'])) {
            $passport_photo = $upload['file_path'];
        } else {
            $error = $upload['error'];
        }
    }
    
    // Upload Aadhaar card
    if (!$error && !empty($_FILES['aadhaar_card']['name'])) {
        $upload = uploadFile($_FILES['aadhaar_card'], 'aadhaar');
        if (isset($upload['file_path'])) {
            $aadhaar_card = $upload['file_path'];
        } else {
            $error = $upload['error'];
        }
    }
    
    // Upload school certificate
    if (!$error && !empty($_FILES['school_certificate']['name'])) {
        $upload = uploadFile($_FILES['school_certificate'], 'certificate');
        if (isset($upload['file_path'])) {
            $school_certificate = $upload['file_path'];
        } else {
            $error = $upload['error'];
        }
    }
    
    if (!$error) {
        // Check if email already exists
        $check_sql = "SELECT id FROM volunteer_requests WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error = "Email already registered! Please wait for admin approval.";
        } else {
            // Insert into database
            $sql = "INSERT INTO volunteer_requests 
                    (full_name, age, gender, mobile_number, email, education, skills, 
                     passport_photo, aadhaar_card, school_certificate, ngo_name, role_position, request_message) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sississssssss", 
                $full_name, $age, $gender, $mobile, $email, $education, $skills,
                $passport_photo, $aadhaar_card, $school_certificate, $ngo_name, $role, $message);
            
            if (mysqli_stmt_execute($stmt)) {
                $request_id = mysqli_insert_id($conn);
                $success = "Your request has been submitted successfully! Request ID: VR-" . str_pad($request_id, 6, '0', STR_PAD_LEFT);
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Failed to submit request. Please try again.";
            }
        }
    }
    
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Volunteer Access - Sarathi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Use CSS from your index.html with modifications */
        :root {
            --primary: #2563eb;
            --secondary: #06D6A0;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background: var(--light-bg);
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-bg);
        }
        
        .section-title {
            color: var(--primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .file-upload {
            border: 2px dashed var(--border);
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload:hover {
            border-color: var(--primary);
            background: rgba(37, 99, 235, 0.02);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 30px auto 0;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: var(--primary);">
                    <i class="fas fa-user-plus"></i> Volunteer Access Request
                </h1>
                <p style="color: var(--text-light);">
                    Complete this form to request volunteer access. All fields are mandatory.
                </p>
            </div>
            
            <?php if($success): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #a7f3d0;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <p style="margin-top: 10px;">Your NGO admin will review your request within 24-48 hours.</p>
                </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div style="background: #fee2e2; color: #dc2626; padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid #fca5a5;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Personal Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i> Personal Information
                    </h2>
                    
                    <div class="form-row">
                        <div>
                            <label>Full Name *</label>
                            <input type="text" name="full_name" class="form-control" 
                                   value="<?php echo isset($_POST['full_name']) ? $_POST['full_name'] : ''; ?>" 
                                   required>
                        </div>
                        
                        <div>
                            <label>Age *</label>
                            <input type="number" name="age" min="18" max="80" class="form-control" 
                                   value="<?php echo isset($_POST['age']) ? $_POST['age'] : ''; ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div>
                            <label>Gender *</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label>Mobile Number *</label>
                            <input type="tel" name="mobile" pattern="[0-9]{10}" class="form-control" 
                                   value="<?php echo isset($_POST['mobile']) ? $_POST['mobile'] : ''; ?>" 
                                   placeholder="10-digit mobile number" required>
                        </div>
                    </div>
                    
                    <div>
                        <label>Email Address *</label>
                        <input type="email" name="email" class="form-control" 
                               value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <!-- Education & Skills Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap"></i> Education & Skills
                    </h2>
                    
                    <div>
                        <label>Highest Education *</label>
                        <select name="education" class="form-control" required>
                            <option value="">Select Education</option>
                            <option value="High School">High School</option>
                            <option value="Diploma">Diploma</option>
                            <option value="Bachelor's Degree">Bachelor's Degree</option>
                            <option value="Master's Degree">Master's Degree</option>
                            <option value="PhD">PhD</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div style="margin-top: 20px;">
                        <label>Skills & Expertise</label>
                        <textarea name="skills" class="form-control" rows="3" 
                                  placeholder="List your skills (e.g., Communication, Teaching, Technical Skills, etc.)"><?php echo isset($_POST['skills']) ? $_POST['skills'] : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- NGO Information Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-building"></i> NGO Information
                    </h2>
                    
                    <div class="form-row">
                        <div>
                            <label>NGO/Organization Name *</label>
                            <input type="text" name="ngo_name" class="form-control" 
                                   value="<?php echo isset($_POST['ngo_name']) ? $_POST['ngo_name'] : ''; ?>" 
                                   required>
                        </div>
                        
                        <div>
                            <label>Role/Position *</label>
                            <input type="text" name="role" class="form-control" 
                                   value="<?php echo isset($_POST['role']) ? $_POST['role'] : ''; ?>" 
                                   placeholder="Field Worker, Coordinator, etc." required>
                        </div>
                    </div>
                    
                    <div>
                        <label>Why do you want to volunteer? *</label>
                        <textarea name="message" class="form-control" rows="4" required><?php echo isset($_POST['message']) ? $_POST['message'] : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Document Upload Section -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-file-upload"></i> Document Upload
                    </h2>
                    
                    <p style="color: var(--text-light); margin-bottom: 20px;">
                        Upload clear scans/photos. Maximum file size: 5MB each. Allowed formats: JPG, PNG, PDF
                    </p>
                    
                    <div class="form-row">
                        <div>
                            <label>Passport Size Photo *</label>
                            <div class="file-upload" onclick="document.getElementById('passport').click()">
                                <i class="fas fa-camera" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                                <p>Click to upload photo</p>
                                <input type="file" id="passport" name="passport_photo" 
                                       accept="image/*" style="display: none;" required>
                            </div>
                        </div>
                        
                        <div>
                            <label>Aadhaar Card *</label>
                            <div class="file-upload" onclick="document.getElementById('aadhaar').click()">
                                <i class="fas fa-id-card" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                                <p>Click to upload Aadhaar</p>
                                <input type="file" id="aadhaar" name="aadhaar_card" 
                                       accept="image/*,.pdf" style="display: none;" required>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label>School Leaving Certificate *</label>
                        <div class="file-upload" onclick="document.getElementById('certificate').click()" style="width: 100%;">
                            <i class="fas fa-file-certificate" style="font-size: 2rem; color: var(--primary); margin-bottom: 10px;"></i>
                            <p>Click to upload certificate</p>
                            <input type="file" id="certificate" name="school_certificate" 
                                   accept="image/*,.pdf" style="display: none;" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
            
            <div style="text-align: center; margin-top: 30px; color: var(--text-light);">
                <p>Already have access? <a href="volunteerlogin.php" style="color: var(--primary);">Login here</a></p>
                <p><a href="index.html" style="color: var(--text-light);"><i class="fas fa-arrow-left"></i> Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // File upload preview
        document.getElementById('passport').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                alert('Selected: ' + file.name);
            }
        });
        
        document.getElementById('aadhaar').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                alert('Selected: ' + file.name);
            }
        });
        
        document.getElementById('certificate').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                alert('Selected: ' + file.name);
            }
        });
    </script>
</body>
</html>