<?php
session_start();
require_once 'config/config.php';

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = getDBConnection();
    
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = $_POST['gender'] ?? '';
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $education = $_POST['education'] ?? '';
    $skills = trim($_POST['skills'] ?? '');
    $ngo_name = trim($_POST['ngo_name'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validate required fields
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill all required fields";
    }
    // Validate email
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address";
    } 
    // Validate password
    elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    }
    elseif ($age < 18 || $age > 80) {
        $error = "Age must be between 18 and 80 years";
    }
    elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error = "Please enter a valid 10-digit mobile number";
    }
    else {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if email already exists
        $check_sql = "SELECT id FROM volunteer_requests WHERE email = ?";
        $stmt = mysqli_prepare($conn, $check_sql);
        if (!$stmt) {
            $error = "Database error: " . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                $error = "Email already registered. Please use a different email.";
                mysqli_stmt_close($stmt);
            } else {
                mysqli_stmt_close($stmt);
                
                // Upload files
                $uploads = [];
                $types = ['passport_photo' => 'passport', 'aadhaar_card' => 'aadhaar', 'school_certificate' => 'certificate'];
                $upload_error = false;
                $upload_error_msg = '';
                
                foreach ($types as $field => $type) {
                    if (isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                            $result = uploadFile($_FILES[$field], $type);
                            if (isset($result['file_path'])) {
                                $uploads[$field] = $result['file_path'];
                            } else {
                                $upload_error_msg = $result['error'] ?? "Failed to upload $field";
                                $upload_error = true;
                                break;
                            }
                        } else {
                            $upload_error_msg = "Error uploading $field. Error code: " . $_FILES[$field]['error'];
                            $upload_error = true;
                            break;
                        }
                    } else {
                        $upload_error_msg = "$field is required";
                        $upload_error = true;
                        break;
                    }
                }
                
                if (!$upload_error && !$error) {
                    // Generate unique invite code
                    $invite_code = 'SAR' . strtoupper(substr(md5(uniqid()), 0, 8));
                    
                    // Insert into database - fix the binding parameters
                    $sql = "INSERT INTO volunteer_requests 
                            (full_name, age, gender, mobile_number, email, password, education, skills, 
                             passport_photo, aadhaar_card, school_certificate, ngo_name, role_position, request_message, invite_code, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                    
                    $stmt = mysqli_prepare($conn, $sql);
                    if (!$stmt) {
                        $error = "Database error: " . mysqli_error($conn);
                    } else {
                        // Make sure all variables are properly defined
                        $passport_photo_path = $uploads['passport_photo'] ?? '';
                        $aadhaar_card_path = $uploads['aadhaar_card'] ?? '';
                        $school_certificate_path = $uploads['school_certificate'] ?? '';
                        
                        // Bind parameters - fix the type string
                        // We have 15 parameters: 1 integer (age) and 14 strings
                        $bind_result = mysqli_stmt_bind_param($stmt, "sisssssssssssss", 
                            $full_name, 
                            $age, 
                            $gender, 
                            $mobile, 
                            $email, 
                            $hashed_password, 
                            $education, 
                            $skills,
                            $passport_photo_path, 
                            $aadhaar_card_path, 
                            $school_certificate_path, 
                            $ngo_name, 
                            $role, 
                            $message, 
                            $invite_code);
                        
                        if (!$bind_result) {
                            $error = "Failed to bind parameters: " . mysqli_stmt_error($stmt);
                            mysqli_stmt_close($stmt);
                            mysqli_close($conn);
                            // Show error and exit
                            displayError($error);
                            exit;
                        }
                        
                        if (mysqli_stmt_execute($stmt)) {
                            $request_id = mysqli_insert_id($conn);
                            
                            $success = "Application submitted successfully!<br><br>
                                      <strong>Request ID:</strong> VR-" . str_pad($request_id, 6, '0', STR_PAD_LEFT) . "<br>
                                      <strong>Invite Code:</strong> " . $invite_code . "<br><br>
                                      Please save your invite code for future reference.";
                            
                            // Clear form data after successful submission
                            $_POST = array();
                            $_FILES = array();
                        } else {
                            $error = "Failed to submit application. ";
                            if (mysqli_errno($conn) == 1062) { // Duplicate entry error
                                $error .= "Email already exists in our system.";
                            } else {
                                $error .= "Error: " . mysqli_error($conn);
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $error = $upload_error_msg ?: $error;
                }
            }
        }
    }
    mysqli_close($conn);
}

function displayError($error) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - Volunteer Application</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                padding: 20px;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                text-align: center;
                max-width: 500px;
                width: 100%;
            }
            .error-container h1 {
                color: #ef4444;
                margin-bottom: 20px;
            }
            .error-container p {
                margin-bottom: 30px;
                color: #666;
            }
            .btn {
                background: #4361ee;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 5px;
                text-decoration: none;
                display: inline-block;
                cursor: pointer;
            }
            .btn:hover {
                background: #3a56d4;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>Application Error</h1>
            <p>' . htmlspecialchars($error) . '</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Application - Sarathi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --primary-light: #eef2ff;
            --secondary: #06d6a0;
            --secondary-dark: #05c793;
            --accent: #8b5cf6;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --success: #10b981;
            --white: #ffffff;
            --light-bg: #f8fafc;
            --light-gray: #f1f5f9;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-medium: #475569;
            --text-light: #64748b;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --radius: 16px;
            --radius-sm: 8px;
            --shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 20px 60px rgba(0, 0, 0, 0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: white;
            animation: fadeInDown 0.8s ease;
        }
        
        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            letter-spacing: -0.5px;
        }
        
        .header h1 i {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 50%;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
            font-weight: 300;
            line-height: 1.8;
        }
        
        /* Application Card */
        .application-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 50px;
            margin-bottom: 40px;
            animation: slideUp 0.8s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }
        
        /* Alert Messages */
        .alert {
            padding: 20px 24px;
            border-radius: var(--radius-sm);
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 16px;
            border: 1px solid transparent;
            animation: slideIn 0.5s ease;
            font-weight: 500;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #b91c1c;
            border-color: #fecaca;
            border-left: 4px solid var(--danger);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-color: #a7f3d0;
            border-left: 4px solid var(--success);
        }
        
        .alert i {
            font-size: 1.5rem;
        }
        
        /* Success box styling */
        .success-box {
            background: linear-gradient(135deg, var(--secondary), var(--success));
            color: white;
            padding: 25px;
            border-radius: var(--radius-sm);
            margin: 20px 0;
            text-align: center;
        }
        
        .success-box h3 {
            margin-bottom: 15px;
            font-family: 'Poppins', sans-serif;
        }
        
        .success-details {
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: var(--radius-sm);
            margin: 15px 0;
            text-align: left;
        }
        
        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
            letter-spacing: 0.2px;
        }
        
        .required::after {
            content: " *";
            color: var(--danger);
        }
        
        /* Input Fields */
        .input-with-icon {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 16px 20px;
            padding-left: 52px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--white);
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.15);
            transform: translateY(-2px);
        }
        
        .form-control:hover {
            border-color: #cbd5e1;
        }
        
        .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            transition: var(--transition);
        }
        
        .form-control:focus + .input-icon {
            color: var(--primary);
        }
        
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' fill='%2364748b' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
            padding-right: 48px;
            cursor: pointer;
        }
        
        textarea.form-control {
            min-height: 140px;
            resize: vertical;
            padding: 16px 20px;
            line-height: 1.6;
        }
        
        /* File Upload */
        .documents-section {
            background: var(--light-bg);
            padding: 32px;
            border-radius: var(--radius);
            margin: 40px 0;
            border: 1px solid var(--border-light);
        }
        
        .documents-section h3 {
            color: var(--text-dark);
            margin-bottom: 24px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .file-upload-group {
            background: var(--white);
            padding: 24px;
            border-radius: var(--radius-sm);
            border: 2px dashed var(--border);
            margin-bottom: 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .file-upload-group:hover {
            border-color: var(--primary);
            background: var(--primary-light);
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }
        
        .file-upload-group i.fa-file-upload {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        .file-label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
        }
        
        .file-input {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--white);
            font-family: 'Inter', sans-serif;
            transition: var(--transition);
        }
        
        .file-input:hover {
            border-color: var(--primary);
        }
        
        .file-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }
        
        .file-hint {
            font-size: 0.85rem;
            color: var(--text-light);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Password Strength */
        .password-container {
            position: relative;
        }
        
        .eye-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 4px;
            transition: var(--transition);
            z-index: 2;
        }
        
        .eye-toggle:hover {
            color: var(--primary);
        }
        
        .password-strength {
            margin-top: 12px;
            height: 6px;
            border-radius: 3px;
            background: var(--border);
            overflow: hidden;
            position: relative;
        }
        
        .strength-meter {
            height: 100%;
            width: 0%;
            transition: var(--transition);
            border-radius: 3px;
        }
        
        .strength-meter.weak { 
            background: linear-gradient(90deg, var(--danger), #f87171); 
            width: 33%; 
        }
        .strength-meter.medium { 
            background: linear-gradient(90deg, var(--warning), #fbbf24); 
            width: 66%; 
        }
        .strength-meter.strong { 
            background: linear-gradient(90deg, var(--secondary), #34d399); 
            width: 100%; 
        }
        
        .password-requirements {
            margin-top: 16px;
            font-size: 0.9rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
            color: var(--text-light);
            transition: var(--transition);
        }
        
        .requirement.met {
            color: var(--success);
        }
        
        .requirement i.fa-check {
            color: var(--success);
            display: none;
        }
        
        .requirement.met i.fa-check {
            display: inline;
        }
        
        .requirement.met i.fa-times {
            display: none;
        }
        
        /* Button */
        .btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px 48px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 20px;
            font-family: 'Poppins', sans-serif;
            letter-spacing: 0.3px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, var(--primary-light), #e0e7ff);
            padding: 28px;
            border-radius: var(--radius-sm);
            margin: 32px 0;
            border-left: 4px solid var(--primary);
        }
        
        .info-box h4 {
            color: var(--text-dark);
            margin-bottom: 16px;
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .info-box ul {
            color: var(--text-medium);
            padding-left: 24px;
            font-size: 0.95rem;
        }
        
        .info-box li {
            margin-bottom: 10px;
            line-height: 1.7;
        }
        
        .info-box li:last-child {
            margin-bottom: 0;
        }
        
        /* Back Link */
        .back-link {
            text-align: center;
            margin-top: 32px;
        }
        
        .back-link a {
            color: var(--white);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }
        
        .back-link a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }
        
        /* Animations */
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .container {
                max-width: 95%;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 2.2rem;
                flex-direction: column;
                gap: 15px;
            }
            
            .application-card {
                padding: 30px 25px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 25px;
            }
            
            .documents-section {
                padding: 25px;
            }
            
            .header p {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.8rem;
            }
            
            .application-card {
                padding: 25px 20px;
            }
            
            .btn {
                padding: 18px 30px;
                font-size: 1rem;
            }
        }
        
        /* Form row for age and gender */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Custom checkbox for file upload */
        .file-preview {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background: var(--light-gray);
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
        }
        
        /* Loading animation */
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loading i {
            font-size: 2rem;
            color: var(--primary);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-hands-helping"></i> Volunteer Application</h1>
            <p>Join our mission to make a difference. Fill out the form below to apply as a volunteer and start your journey with us.</p>
        </div>
        
        <div class="application-card">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if(!$success): ?>
            <form method="POST" enctype="multipart/form-data" id="volunteerForm">
                <div class="form-grid">
                    <!-- Personal Information -->
                    <div class="form-group">
                        <label class="form-label required">Full Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   name="full_name" 
                                   class="form-control" 
                                   placeholder="Enter your full name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Age</label>
                            <div class="input-with-icon">
                                <i class="fas fa-birthday-cake input-icon"></i>
                                <input type="number" 
                                       name="age" 
                                       class="form-control" 
                                       placeholder="Age" 
                                       min="18" 
                                       max="80" 
                                       value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male" <?php echo ($_POST['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($_POST['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($_POST['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                <option value="Prefer not to say" <?php echo ($_POST['gender'] ?? '') == 'Prefer not to say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="form-group">
                        <label class="form-label required">Mobile Number</label>
                        <div class="input-with-icon">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" 
                                   name="mobile" 
                                   class="form-control" 
                                   placeholder="10-digit mobile number" 
                                   pattern="[0-9]{10}" 
                                   value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Email Address</label>
                        <div class="input-with-icon">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   name="email" 
                                   class="form-control" 
                                   placeholder="your.email@example.com" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <!-- Password Fields -->
                    <div class="form-group">
                        <label class="form-label required">Password</label>
                        <div class="input-with-icon password-container">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   name="password" 
                                   class="form-control" 
                                   id="password" 
                                   placeholder="Create a password" 
                                   required
                                   minlength="6">
                            <button type="button" class="eye-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter" id="strengthMeter"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="reqLength">
                                <i class="fas fa-times"></i>
                                <i class="fas fa-check"></i>
                                At least 6 characters
                            </div>
                            <div class="requirement" id="reqUppercase">
                                <i class="fas fa-times"></i>
                                <i class="fas fa-check"></i>
                                At least one uppercase letter
                            </div>
                            <div class="requirement" id="reqNumber">
                                <i class="fas fa-times"></i>
                                <i class="fas fa-check"></i>
                                At least one number
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Confirm Password</label>
                        <div class="input-with-icon password-container">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   placeholder="Confirm your password" 
                                   required>
                            <button type="button" class="eye-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div id="passwordMatch" style="margin-top: 12px; font-size: 14px; min-height: 24px;">
                            <!-- Password match indicator will appear here -->
                        </div>
                    </div>
                    
                    <!-- Education & Skills -->
                    <div class="form-group">
                        <label class="form-label required">Education Level</label>
                        <select name="education" class="form-control" required>
                            <option value="">Select Education Level</option>
                            <option value="High School" <?php echo ($_POST['education'] ?? '') == 'High School' ? 'selected' : ''; ?>>High School</option>
                            <option value="Diploma" <?php echo ($_POST['education'] ?? '') == 'Diploma' ? 'selected' : ''; ?>>Diploma</option>
                            <option value="Bachelor's Degree" <?php echo ($_POST['education'] ?? '') == "Bachelor's Degree" ? 'selected' : ''; ?>>Bachelor's Degree</option>
                            <option value="Master's Degree" <?php echo ($_POST['education'] ?? '') == "Master's Degree" ? 'selected' : ''; ?>>Master's Degree</option>
                            <option value="PhD" <?php echo ($_POST['education'] ?? '') == 'PhD' ? 'selected' : ''; ?>>PhD</option>
                            <option value="Other" <?php echo ($_POST['education'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Skills</label>
                        <div class="input-with-icon">
                            <i class="fas fa-tools input-icon"></i>
                            <input type="text" 
                                   name="skills" 
                                   class="form-control" 
                                   placeholder="e.g., Teaching, Coding, Healthcare, etc." 
                                   value="<?php echo htmlspecialchars($_POST['skills'] ?? ''); ?>"
                                   required>
                        </div>
                        <div class="file-hint">
                            <i class="fas fa-info-circle"></i> Separate multiple skills with commas
                        </div>
                    </div>
                    
                    <!-- NGO Details -->
                    <div class="form-group">
                        <label class="form-label required">NGO Name</label>
                        <div class="input-with-icon">
                            <i class="fas fa-building input-icon"></i>
                            <input type="text" 
                                   name="ngo_name" 
                                   class="form-control" 
                                   placeholder="Name of the NGO you want to volunteer for" 
                                   value="<?php echo htmlspecialchars($_POST['ngo_name'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Role / Position</label>
                        <select name="role" class="form-control" required>
                            <option value="">Select Preferred Role</option>
                            <option value="Teaching Volunteer" <?php echo ($_POST['role'] ?? '') == 'Teaching Volunteer' ? 'selected' : ''; ?>>Teaching Volunteer</option>
                            <option value="Medical Volunteer" <?php echo ($_POST['role'] ?? '') == 'Medical Volunteer' ? 'selected' : ''; ?>>Medical Volunteer</option>
                            <option value="Fundraising Volunteer" <?php echo ($_POST['role'] ?? '') == 'Fundraising Volunteer' ? 'selected' : ''; ?>>Fundraising Volunteer</option>
                            <option value="Event Coordinator" <?php echo ($_POST['role'] ?? '') == 'Event Coordinator' ? 'selected' : ''; ?>>Event Coordinator</option>
                            <option value="Social Media Manager" <?php echo ($_POST['role'] ?? '') == 'Social Media Manager' ? 'selected' : ''; ?>>Social Media Manager</option>
                            <option value="Counselor" <?php echo ($_POST['role'] ?? '') == 'Counselor' ? 'selected' : ''; ?>>Counselor</option>
                            <option value="Field Worker" <?php echo ($_POST['role'] ?? '') == 'Field Worker' ? 'selected' : ''; ?>>Field Worker</option>
                            <option value="Other" <?php echo ($_POST['role'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <!-- Motivation -->
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label required">Why do you want to volunteer?</label>
                        <textarea name="message" 
                                  class="form-control" 
                                  placeholder="Tell us about your motivation, what you hope to achieve, and why you're passionate about volunteering..." 
                                  required
                                  rows="4"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Documents Section -->
                <div class="documents-section">
                    <h3><i class="fas fa-file-upload"></i> Required Documents</h3>
                    <div class="form-grid">
                        <div class="file-upload-group">
                            <i class="fas fa-user-circle fa-file-upload"></i>
                            <label class="file-label required">Passport Size Photo</label>
                            <input type="file" 
                                   name="passport_photo" 
                                   class="file-input" 
                                   accept="image/*" 
                                   required
                                   onchange="previewFileName(this)">
                            <div class="file-hint">
                                <i class="fas fa-info-circle"></i> Max 5MB, JPG/PNG format
                            </div>
                        </div>
                        
                        <div class="file-upload-group">
                            <i class="fas fa-id-card fa-file-upload"></i>
                            <label class="file-label required">Aadhaar Card</label>
                            <input type="file" 
                                   name="aadhaar_card" 
                                   class="file-input" 
                                   accept="image/*,.pdf" 
                                   required
                                   onchange="previewFileName(this)">
                            <div class="file-hint">
                                <i class="fas fa-info-circle"></i> Max 5MB, PDF or Image
                            </div>
                        </div>
                        
                        <div class="file-upload-group">
                            <i class="fas fa-graduation-cap fa-file-upload"></i>
                            <label class="file-label required">Educational Certificate</label>
                            <input type="file" 
                                   name="school_certificate" 
                                   class="file-input" 
                                   accept="image/*,.pdf" 
                                   required
                                   onchange="previewFileName(this)">
                            <div class="file-hint">
                                <i class="fas fa-info-circle"></i> Max 5MB, PDF or Image
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="info-box">
                    <h4><i class="fas fa-info-circle"></i> Important Notes</h4>
                    <ul>
                        <li>Your application will be reviewed by our admin team within 3-5 business days</li>
                        <li>You'll receive an email notification when your application is approved</li>
                        <li>Use the same email and password to login once approved</li>
                        <li>Keep your invite code safe - you'll need it for future reference</li>
                        <li>All documents must be clear and legible for faster processing</li>
                    </ul>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
                
                <div class="loading" id="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p style="margin-top: 10px;">Submitting your application...</p>
                </div>
            </form>
            <?php else: ?>
                <div class="success-box">
                    <h3><i class="fas fa-check-circle"></i> Application Submitted Successfully!</h3>
                    <div class="success-details">
                        <p><strong>Request ID:</strong> <?php echo explode("Request ID:", $success)[1] ?? $success; ?></p>
                        <p><i class="fas fa-info-circle"></i> Your application is now under review. You'll receive an email notification once it's approved.</p>
                    </div>
                    <a href="index.html" class="btn" style="width: auto; padding: 12px 30px; margin-top: 15px;">
                        <i class="fas fa-home"></i> Return to Homepage
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if(!$success): ?>
        <div class="back-link">
            <a href="index.html">
                <i class="fas fa-arrow-left"></i> Back to Homepage
            </a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Form validation and enhancement
        document.getElementById('volunteerForm')?.addEventListener('submit', function(e) {
            const age = document.querySelector('input[name="age"]').value;
            const mobile = document.querySelector('input[name="mobile"]').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const files = document.querySelectorAll('input[type="file"]');
            
            // Age validation
            if (age < 18 || age > 80) {
                e.preventDefault();
                showAlert('Age must be between 18 and 80 years.', 'error');
                return false;
            }
            
            // Mobile validation
            if (!/^\d{10}$/.test(mobile)) {
                e.preventDefault();
                showAlert('Please enter a valid 10-digit mobile number.', 'error');
                return false;
            }
            
            // Password validation
            if (password.length < 6) {
                e.preventDefault();
                showAlert('Password must be at least 6 characters long.', 'error');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showAlert('Passwords do not match.', 'error');
                return false;
            }
            
            // File size validation (client-side)
            let validFiles = true;
            files.forEach(file => {
                if (file.files[0] && file.files[0].size > 5 * 1024 * 1024) {
                    validFiles = false;
                    showAlert(`File "${file.name}" exceeds 5MB limit. Please compress or choose a smaller file.`, 'error');
                }
            });
            
            if (!validFiles) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn');
            const loadingDiv = document.getElementById('loading');
            submitBtn.style.display = 'none';
            loadingDiv.style.display = 'block';
            
            return true;
        });
        
        // Real-time mobile number formatting
        const mobileInput = document.querySelector('input[name="mobile"]');
        if (mobileInput) {
            mobileInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').slice(0, 10);
            });
        }
        
        // Password strength checker
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthMeter = document.getElementById('strengthMeter');
                const reqLength = document.getElementById('reqLength');
                const reqUppercase = document.getElementById('reqUppercase');
                const reqNumber = document.getElementById('reqNumber');
                
                // Check requirements
                const hasLength = password.length >= 6;
                const hasUppercase = /[A-Z]/.test(password);
                const hasNumber = /\d/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                // Update requirement indicators
                reqLength.classList.toggle('met', hasLength);
                reqUppercase.classList.toggle('met', hasUppercase);
                reqNumber.classList.toggle('met', hasNumber);
                
                // Calculate strength
                let strength = 0;
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasNumber) strength++;
                if (hasSpecial) strength++;
                
                // Update strength meter
                strengthMeter.className = 'strength-meter';
                if (password.length === 0) {
                    // Empty
                } else if (strength === 1) {
                    strengthMeter.classList.add('weak');
                } else if (strength === 2 || strength === 3) {
                    strengthMeter.classList.add('medium');
                } else if (strength >= 4) {
                    strengthMeter.classList.add('strong');
                }
                
                // Check password match
                checkPasswordMatch();
            });
        }
        
        // Password match checker
        const confirmPasswordInput = document.getElementById('confirm_password');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('password')?.value || '';
            const confirmPassword = document.getElementById('confirm_password')?.value || '';
            const matchIndicator = document.getElementById('passwordMatch');
            
            if (!matchIndicator) return;
            
            if (!confirmPassword) {
                matchIndicator.innerHTML = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchIndicator.innerHTML = '<span style="color: var(--success);"><i class="fas fa-check-circle"></i> Passwords match</span>';
            } else {
                matchIndicator.innerHTML = '<span style="color: var(--danger);"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
            }
        }
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            if (!passwordInput) return;
            
            const eyeIcon = passwordInput.parentNode.querySelector('.eye-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
        
        // File name preview
        function previewFileName(input) {
            const fileUploadGroup = input.closest('.file-upload-group');
            const existingPreview = fileUploadGroup.querySelector('.file-preview');
            
            if (existingPreview) {
                existingPreview.remove();
            }
            
            if (input.files && input.files[0]) {
                const preview = document.createElement('div');
                preview.className = 'file-preview';
                preview.innerHTML = `
                    <strong>Selected file:</strong> ${input.files[0].name}<br>
                    <small>Size: ${formatFileSize(input.files[0].size)}</small>
                `;
                preview.style.display = 'block';
                input.parentNode.appendChild(preview);
            }
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Show alert message
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
                <span>${message}</span>
            `;
            
            // Insert before the form
            const form = document.getElementById('volunteerForm');
            if (form) {
                form.parentNode.insertBefore(alertDiv, form);
                
                // Remove alert after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }
        }
        
        // Add smooth scrolling for better UX
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    </script>
</body>
</html>