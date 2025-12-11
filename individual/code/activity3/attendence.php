<?php

require_once 'student_db.php';

$page_title = "Student Attendance List - Ashesi Attendance System";
$page_header = "Registered Students";
$page_description = "Complete list of all registered students in the attendance system";

$conn = getDBConnection();
$sql = "SELECT * FROM students ORDER BY created_at DESC";
$result = $conn->query($sql);


$students = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

$conn->close();

// Calculate statistics
$total_students = count($students);
$programs = array_unique(array_column($students, 'program'));
$total_programs = count($programs);
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
            background: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
        }
        .navbar {
            background: #8B1538;
            color: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar-content h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        .navbar-content p {
            opacity: 0.9;
            font-size: 14px;
        }
        .navbar-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            margin-left: 10px;
            transition: background 0.3s;
        }
        .navbar-links a:hover {
            background: rgba(255,255,255,0.3);
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: linear-gradient(135deg, #8B1538 0%, #6d1029 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(139, 21, 56, 0.3);
        }
        .stat-box h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-box p {
            font-size: 42px;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #8B1538;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: background 0.3s;
            margin-bottom: 20px;
        }
        .btn:hover {
            background: #6d1029;
        }
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .students-table thead {
            background: #8B1538;
            color: white;
        }
        .students-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .students-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .students-table tbody tr:hover {
            background: #f9f9f9;
        }
        .badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2e7d32;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #333;
        }
        .logout-info {
            color: white;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="navbar">
            <div class="navbar-content">
                <!-- Using PHP variables for page header and description -->
                <h1><?php echo $page_header; ?></h1>
                <p><?php echo $page_description; ?></p>
            </div>
            <div class="navbar-links">
                <?php if (is_logged_in()): ?>
                    <span class="logout-info">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="?logout=1">Logout</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="index.php">Register</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="stats">
                <div class="stat-box">
                    <h3>Total Students</h3>
                    <p><?php echo $total_students; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Programs Offered</h3>
                    <p><?php echo $total_programs; ?></p>
                </div>
                <div class="stat-box">
                    <h3>Latest Registration</h3>
                    <p style="font-size: 18px;">
                        <?php 
                        if (!empty($students)) {
                            echo date('M d, Y', strtotime($students[0]['created_at']));
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </p>
                </div>
            </div>

            <a href="index.php" class="btn">+ Register New Student</a>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <h3>No Students Registered Yet</h3>
                    <p>Click the button above to register the first student.</p>
                </div>
            <?php else: ?>
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Registered Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($students as $student): 
                        ?>
                            <tr>
                                <td><strong><?php echo $counter++; ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($student['student_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['program']); ?></td>
                                <td><span class="badge">Year <?php echo $student['year_of_study']; ?></span></td>
                                <td><?php echo date('M d, Y - g:i A', strtotime($student['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    ?>
</body>
</html>