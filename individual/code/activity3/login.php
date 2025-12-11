<?php

require_once 'student_db.php';

if (is_logged_in()) {
    header("Location: attendance.php");
    exit();
}

$page_title = "Student Login - Ashesi Attendance System";
$page_header = "Student Login";
$page_description = "Login to access the attendance system";

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id, full_name, email, student_id, password FROM students WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $student = $result->fetch_assoc();
            
            if (password_verify($password, $student['password'])) {
                
                $_SESSION['student_id'] = $student['id'];
                $_SESSION['full_name'] = $student['full_name'];
                $_SESSION['email'] = $student['email'];
                $_SESSION['student_number'] = $student['student_id'];
                
                header("Location: attendance.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background: linear-gradient(135deg, #8B1538 0%, #5c0f26 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }
        .login-header {
            background: #8B1538;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .login-header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .login-form {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #8B1538;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #8B1538;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #6d1029;
        }
        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        .register-link a {
            color: #8B1538;
            text-decoration: none;
            font-weight: 600;
        }
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <!-- Using PHP variables for page header and description -->
            <h1><?php echo $page_header; ?></h1>
            <p><?php echo $page_description; ?></p>
        </div>
        <form class="login-form" method="POST" action="">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($email); ?>" 
                       placeholder="your.email@ashesi.edu.gh" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn">Sign In</button>
            
            <div class="register-link">
                Don't have an account? <a href="index.php">Register here</a>
            </div>
        </form>
    </div>
</body>
</html>