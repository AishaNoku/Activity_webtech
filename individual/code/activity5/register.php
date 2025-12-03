<?php
include 'db.php';
require_once 'config.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect_to_dashboard();
}

$errors = [];
$success = '';
$form_data = ['full_name' => '', 'email' => '', 'role' => 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();

    $form_data['full_name'] = sanitize_input($_POST['full_name'] ?? '');
    $form_data['email'] = sanitize_input($_POST['email'] ?? '');
    $form_data['role'] = sanitize_input($_POST['role'] ?? 'student');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($form_data['full_name'])) {
        $errors[] = "Full name is required";
    } elseif (!preg_match('/^[a-zA-Z\s\-]{2,100}$/', $form_data['full_name'])) {
        $errors[] = "Full name must contain only letters, spaces, and hyphens (2-100 characters)";
    }

    if (empty($form_data['email'])) {
        $errors[] = "Email is required";
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $form_data['email'])) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $form_data['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = "Email already registered";
        }
        $stmt->close();
    }
  
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password)) {
        $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number, and special character";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }

    if (!in_array($form_data['role'], ['student', 'faculty'])) {
        $errors[] = "Invalid role selected";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $form_data['full_name'], $form_data['email'], $hashed_password, $form_data['role']);
        
        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
            $form_data = ['full_name' => '', 'email' => '', 'role' => 'student'];
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="register.css">
    <title>Register - Ashesi Attendance System</title>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Ashesi Attendance System</h1>
            <p>Create your account</p>
        </div>
        <form class="register-form" method="POST" action="" id="registerForm">
            <?php if (!empty($errors)): ?>
                <div class="error-box">
                    <?php echo implode("<br>", $errors); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-box">
                    <?php echo $success; ?>
                    <br><a href="login.php">Click here to login</a>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo htmlspecialchars($form_data['full_name']); ?>" 
                       placeholder="John Doe" required>
                <div class="client-error" id="nameError"></div>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($form_data['email']); ?>" 
                       placeholder="your.email@ashesi.edu.gh" required>
                <div class="client-error" id="emailError"></div>
            </div>
            
            <div class="form-group">
                <label for="role">I am a:</label>
                <select id="role" name="role" required>
                    <option value="student" <?php echo $form_data['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="faculty" <?php echo $form_data['role'] === 'faculty' ? 'selected' : ''; ?>>Faculty Member</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" 
                       placeholder="Create a strong password" required>
                <div class="password-requirements">
                    Min 8 characters, uppercase, lowercase, number, special character (@$!%*?&)
                </div>
                <div class="client-error" id="passwordError"></div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Confirm your password" required>
                <div class="client-error" id="confirmError"></div>
            </div>
            
            <button type="submit" class="btn">Create Account</button>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </form>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            let isValid = true;
            
          
            const name = document.getElementById('full_name').value.trim();
            const nameRegex = /^[a-zA-Z\s\-]{2,100}$/;
            const nameError = document.getElementById('nameError');
            
            if (!name) {
                nameError.textContent = 'Full name is required';
                nameError.style.display = 'block';
                isValid = false;
            } else if (!nameRegex.test(name)) {
                nameError.textContent = 'Name must contain only letters, spaces, and hyphens';
                nameError.style.display = 'block';
                isValid = false;
            } else {
                nameError.style.display = 'none';
            }
            const email = document.getElementById('email').value.trim();
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            const emailError = document.getElementById('emailError');
            
            if (!email) {
                emailError.textContent = 'Email is required';
                emailError.style.display = 'block';
                isValid = false;
            } else if (!emailRegex.test(email)) {
                emailError.textContent = 'Please enter a valid email address';
                emailError.style.display = 'block';
                isValid = false;
            } else {
                emailError.style.display = 'none';
            }
            
            const password = document.getElementById('password').value;
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            const passwordError = document.getElementById('passwordError');
            
            if (!password) {
                passwordError.textContent = 'Password is required';
                passwordError.style.display = 'block';
                isValid = false;
            } else if (!passwordRegex.test(password)) {
                passwordError.textContent = 'Password does not meet requirements';
                passwordError.style.display = 'block';
                isValid = false;
            } else {
                passwordError.style.display = 'none';
            }
            const confirmPassword = document.getElementById('confirm_password').value;
            const confirmError = document.getElementById('confirmError');
            
            if (password !== confirmPassword) {
                confirmError.textContent = 'Passwords do not match';
                confirmError.style.display = 'block';
                isValid = false;
            } else {
                confirmError.style.display = 'none';
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>