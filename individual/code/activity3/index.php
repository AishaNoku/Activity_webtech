<?php

require_once 'student_db.php';

$page_title = "Student Registration - Ashesi Attendance System";
$page_header = "Student Registration";
$page_description = "Register for the Ashesi attendance system";

$success = false;
$error = "";
$show_data = false;
$form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
   
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $student_id = sanitize_input($_POST['student_id'] ?? '');
    $program = sanitize_input($_POST['program'] ?? '');
    $year_of_study = intval($_POST['year_of_study'] ?? 0);
    $password = $_POST['password'] ?? '';
    
    $form_data = [
        'Full Name' => $full_name,
        'Email Address' => $email,
        'Student ID' => $student_id,
        'Program' => $program,
        'Year of Study' => "Year $year_of_study"
    ];
    
    $errors = [];
    
    if (!preg_match('/^[a-zA-Z\s\-]{2,100}$/', $full_name)) {
        $errors[] = "Invalid name format. Use only letters, spaces, and hyphens";
    }
    if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
        $errors[] = "Invalid email format";
    }
    
    if (!preg_match('/^\d{8}$/', $student_id)) {
        $errors[] = "Student ID must be exactly 8 digits";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if (empty($errors)) {
        $show_data = true;
        
        $conn = getDBConnection();
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO students (full_name, email, student_id, program, year_of_study, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssis", $full_name, $email, $student_id, $program, $year_of_study, $hashed_password);
        
        if ($stmt->execute()) {
            $success = true;
        } else {
            $error = "Registration failed. Email or Student ID may already exist.";
            $show_data = false;
        }
        
        $stmt->close();
        $conn->close();
    } else {
        $error = implode("<br>", $errors);
    }
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
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        .header {
            background: #8B1538;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #8B1538;
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
            margin-top: 10px;
        }
        .btn:hover {
            background: #6d1029;
        }
        .btn-secondary {
            background: #666;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: #444;
        }
        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .data-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border: 1px solid #ddd;
            width: 40%;
        }
        .data-table td {
            padding: 12px;
            border: 1px solid #ddd;
            color: #555;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #8B1538;
            text-decoration: none;
            font-weight: 600;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $page_header; ?></h1>
            <p><?php echo $page_description; ?></p>
        </div>
        
        <div class="content">
            <?php if ($success && $show_data): ?>
                <div class="success-message">
                    Registration successful! You can now login.
                </div>
                
                <h3 style="margin-bottom: 15px;">Your Submitted Information:</h3>
                
                <!-- Display form data in table with field names and values -->
                <table class="data-table">
                    <?php foreach ($form_data as $field => $value): ?>
                        <tr>
                            <th><?php echo $field; ?></th>
                            <td><?php echo htmlspecialchars($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <a href="login.php"><button class="btn">Go to Login</button></a>
                <a href="attendance.php"><button class="btn btn-secondary">View All Students</button></a>
                
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" 
                               placeholder="John Doe" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               placeholder="john.doe@ashesi.edu.gh" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_id">Student ID (8 digits)</label>
                        <input type="text" id="student_id" name="student_id" 
                               placeholder="12345678" required 
                               pattern="[0-9]{8}" 
                               title="Must be exactly 8 digits">
                    </div>
                    
                    <div class="form-group">
                        <label for="program">Program</label>
                        <select id="program" name="program" required>
                            <option value="">Select Program</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Management Information Systems">Management Information Systems</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Business Administration">Business Administration</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="year_of_study">Year of Study</label>
                        <select id="year_of_study" name="year_of_study" required>
                            <option value="">Select Year</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">Year 4</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password (min 6 characters)</label>
                        <input type="password" id="password" name="password" 
                               placeholder="Create a password" required minlength="6">
                    </div>
                    
                    <button type="submit" name="register" class="btn">Register</button>
                    
                    <div class="links">
                        <a href="login.php">Already registered? Login</a> | 
                        <a href="attendance.php">View Students</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>