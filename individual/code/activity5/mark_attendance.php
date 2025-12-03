<?php
require_once 'config.php';

require_login();
if ($_SESSION['role'] !== 'student') {
    header("Location: faculty_dashboard.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_code = sanitize_input($_POST['attendance_code']);
    
    if (!preg_match('/^\d{6}$/', $attendance_code)) {
        $error = "Invalid code format. Code must be 6 digits.";
    } else {
        // Find the session with this code
        $stmt = $conn->prepare("
            SELECT cs.id, cs.course_id, c.course_code, c.course_name, cs.session_date
            FROM class_sessions cs
            JOIN courses c ON cs.course_id = c.id
            WHERE cs.attendance_code = ?
        ");
        $stmt->bind_param("s", $attendance_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = "Invalid attendance code.";
        } else {
            $session = $result->fetch_assoc();
            $stmt->close();
            
            // Check if student is enrolled in this course
            $stmt = $conn->prepare("
                SELECT id FROM enrollment_requests 
                WHERE student_id = ? AND course_id = ? AND status = 'approved'
            ");
            $stmt->bind_param("ii", $user_id, $session['course_id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                $error = "You are not enrolled in this course.";
            } else {
                $stmt->close();
                
                // Check if already marked attendance
                $stmt = $conn->prepare("
                    SELECT id FROM attendance 
                    WHERE session_id = ? AND student_id = ?
                ");
                $stmt->bind_param("ii", $session['id'], $user_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $error = "You have already marked attendance for this session.";
                } else {
                    $stmt->close();
                    
                    // Mark attendance
                    $stmt = $conn->prepare("
                        INSERT INTO attendance (session_id, student_id, status, marked_at) 
                        VALUES (?, ?, 'present', NOW())
                    ");
                    $stmt->bind_param("ii", $session['id'], $user_id);
                    
                    if ($stmt->execute()) {
                        $message = "Attendance marked successfully for " . htmlspecialchars($session['course_code']) . " - " . date('M d, Y', strtotime($session['session_date']));
                    } else {
                        $error = "Failed to mark attendance. Please try again.";
                    }
                }
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare("
    SELECT a.*, cs.session_date, cs.start_time, c.course_code, c.course_name
    FROM attendance a
    JOIN class_sessions cs ON a.session_id = cs.id
    JOIN courses c ON cs.course_id = c.id
    WHERE a.student_id = ?
    ORDER BY a.marked_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_attendance = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Ashesi Attendance System</title>
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
        }
        .navbar {
            background: #8B1538;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar h1 {
            font-size: 22px;
        }
        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #1a1a2e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }
        .message {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .message.error {
            background: #ffebee;
            color: #c62828;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h3 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }
        .code-input-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }
        .code-input {
            width: 100%;
            max-width: 300px;
            padding: 20px;
            border: 3px solid #8B1538;
            border-radius: 10px;
            font-size: 32px;
            text-align: center;
            letter-spacing: 8px;
            font-weight: bold;
        }
        .code-input:focus {
            outline: none;
            border-color: #6d1029;
            box-shadow: 0 0 10px rgba(139, 21, 56, 0.3);
        }
        .btn {
            padding: 15px 40px;
            background: #8B1538;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        .btn:hover {
            background: #6d1029;
        }
        .info-text {
            text-align: center;
            color: #666;
            margin-top: 10px;
        }
        .recent-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .recent-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .recent-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2e7d32;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Ashesi Attendance System</h1>
        <div class="nav-links">
            <a href="student_dashboard.php">Dashboard</a>
            <a href="mark_attendance.php">Mark Attendance</a>
            <a href="student_reports.php">My Reports</a>
        </div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <div class="user-avatar"><?php echo get_initials($_SESSION['full_name']); ?></div>
            <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h3>Enter Attendance Code</h3>
            <form method="POST" class="code-input-container">
                <input type="text" 
                       id="attendance_code" 
                       name="attendance_code" 
                       class="code-input" 
                       placeholder="000000"
                       maxlength="6"
                       pattern="\d{6}"
                       required
                       autofocus>
                <button type="submit" name="mark_attendance" class="btn">Mark Attendance</button>
                <p class="info-text">Enter the 6-digit code provided by your instructor</p>
            </form>
        </div>

        <div class="card">
            <h3>Recent Attendance</h3>
            <?php if (empty($recent_attendance)): ?>
                <div class="empty-state">
                    <p>No attendance records yet.</p>
                </div>
            <?php else: ?>
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time Marked</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attendance as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['course_code']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($record['course_name']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($record['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($record['marked_at'])); ?></td>
                                <td><span class="badge">Present</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('attendance_code').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '');
        });
    </script>
</body>
</html>