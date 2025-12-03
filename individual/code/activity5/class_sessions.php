<?php
require_once 'config.php';

require_login();
if ($_SESSION['role'] !== 'faculty') {
    header("Location: student_dashboard.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_session'])) {
    $course_id = intval($_POST['course_id']);
    $session_date = sanitize_input($_POST['session_date']);
    $start_time = sanitize_input($_POST['start_time']);
    $end_time = sanitize_input($_POST['end_time']);
    
    $attendance_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $course_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        
        $stmt = $conn->prepare("INSERT INTO class_sessions (course_id, session_date, start_time, end_time, attendance_code) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $course_id, $session_date, $start_time, $end_time, $attendance_code);
        
        if ($stmt->execute()) {
            $message = "Class session created! Attendance Code: <strong>$attendance_code</strong>";
        } else {
            $error = "Failed to create session.";
        }
        $stmt->close();
    } else {
        $error = "Invalid course selected.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    $session_id = intval($_POST['session_id']);
    
    $stmt = $conn->prepare("DELETE cs FROM class_sessions cs JOIN courses c ON cs.course_id = c.id WHERE cs.id = ? AND c.faculty_id = ?");
    $stmt->bind_param("ii", $session_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $message = "Session deleted successfully.";
    }
    $stmt->close();
}

$stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$stmt = $conn->prepare("
    SELECT cs.*, c.course_code, c.course_name,
           COUNT(DISTINCT a.student_id) as present_count,
           (SELECT COUNT(*) FROM enrollment_requests WHERE course_id = c.id AND status = 'approved') as total_students
    FROM class_sessions cs
    JOIN courses c ON cs.course_id = c.id
    LEFT JOIN attendance a ON cs.id = a.session_id AND a.status = 'present'
    WHERE c.faculty_id = ?
    GROUP BY cs.id
    ORDER BY cs.session_date DESC, cs.start_time DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Sessions - Ashesi Attendance System</title>
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
            max-width: 1200px;
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
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .card h3 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #8B1538;
        }
        .btn {
            padding: 10px 20px;
            background: #8B1538;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #6d1029;
        }
        .btn-delete {
            background: #c62828;
            padding: 6px 12px;
            font-size: 12px;
        }
        .btn-delete:hover {
            background: #b71c1c;
        }
        .sessions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .sessions-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .sessions-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .sessions-table tr:hover {
            background: #f9f9f9;
        }
        .code-display {
            background: #8B1538;
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 18px;
            letter-spacing: 2px;
        }
        .attendance-stats {
            color: #666;
            font-size: 14px;
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
            <a href="faculty_dashboard.php">Dashboard</a>
            <a href="class_sessions.php">Class Sessions</a>
            <a href="reports.php">Reports</a>
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
            <h3>Create New Class Session</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="session_date">Date</label>
                        <input type="date" id="session_date" name="session_date" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" required>
                    </div>
                    <div class="form-group">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" required>
                    </div>
                </div>
                <button type="submit" name="create_session" class="btn">Create Session</button>
            </form>
        </div>

        <div class="card">
            <h3>Class Sessions</h3>
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <p>No class sessions created yet.</p>
                </div>
            <?php else: ?>
                <table class="sessions-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Attendance Code</th>
                            <th>Attendance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($session['course_code']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($session['course_name']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($session['start_time'])) . ' - ' . date('g:i A', strtotime($session['end_time'])); ?></td>
                                <td><span class="code-display"><?php echo $session['attendance_code']; ?></span></td>
                                <td>
                                    <span class="attendance-stats">
                                        <?php echo $session['present_count']; ?> / <?php echo $session['total_students']; ?> students
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this session?');">
                                        <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                        <button type="submit" name="delete_session" class="btn btn-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('session_date').valueAsDate = new Date();
    </script>
</body>
</html>