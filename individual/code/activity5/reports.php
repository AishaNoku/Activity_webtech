<?php
require_once 'config.php';

require_login();
if ($_SESSION['role'] !== 'faculty') {
    header("Location: student_dashboard.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, course_code, course_name FROM courses WHERE faculty_id = ? ORDER BY course_code");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : (count($courses) > 0 ? $courses[0]['id'] : 0);

$course_report = [];
$session_details = [];

if ($selected_course > 0) {
    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.email,
            COUNT(DISTINCT cs.id) as total_sessions,
            COUNT(DISTINCT a.session_id) as attended_sessions,
            ROUND((COUNT(DISTINCT a.session_id) / COUNT(DISTINCT cs.id) * 100), 1) as attendance_percentage
        FROM users u
        JOIN enrollment_requests er ON u.id = er.student_id
        JOIN class_sessions cs ON er.course_id = cs.course_id
        LEFT JOIN attendance a ON cs.id = a.session_id AND a.student_id = u.id
        WHERE er.course_id = ? AND er.status = 'approved'
        GROUP BY u.id
        ORDER BY u.full_name
    ");
    $stmt->bind_param("i", $selected_course);
    $stmt->execute();
    $course_report = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $stmt = $conn->prepare("
        SELECT 
            cs.id,
            cs.session_date,
            cs.start_time,
            cs.attendance_code,
            COUNT(DISTINCT a.student_id) as present_count,
            (SELECT COUNT(*) FROM enrollment_requests WHERE course_id = cs.course_id AND status = 'approved') as total_students
        FROM class_sessions cs
        LEFT JOIN attendance a ON cs.id = a.session_id AND a.status = 'present'
        WHERE cs.course_id = ?
        GROUP BY cs.id
        ORDER BY cs.session_date DESC, cs.start_time DESC
    ");
    $stmt->bind_param("i", $selected_course);
    $stmt->execute();
    $session_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Ashesi Attendance System</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
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
        .course-selector {
            margin-bottom: 20px;
        }
        .course-selector select {
            width: 100%;
            max-width: 400px;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .report-table th {
            background: #f5f5f5;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .report-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        .percentage {
            font-weight: bold;
            font-size: 16px;
        }
        .percentage.good {
            color: #2e7d32;
        }
        .percentage.warning {
            color: #f57c00;
        }
        .percentage.danger {
            color: #c62828;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #8B1538;
        }
        .stat-box .label {
            color: #666;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .stat-box .value {
            color: #333;
            font-size: 24px;
            font-weight: bold;
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
        <div class="course-selector">
            <label for="course_select" style="display: block; margin-bottom: 8px; font-weight: 600;">Select Course:</label>
            <select id="course_select" onchange="window.location.href='reports.php?course_id=' + this.value">
                <?php foreach ($courses as $course): ?>
                    <option value="<?php echo $course['id']; ?>" <?php echo $course['id'] == $selected_course ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($courses)): ?>
            <div class="card">
                <div class="empty-state">
                    <p>No courses available. Create a course first.</p>
                </div>
            </div>
        <?php elseif (empty($course_report)): ?>
            <div class="card">
                <div class="empty-state">
                    <p>No students enrolled in this course yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h3>Session Overview</h3>
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="label">Total Sessions</div>
                        <div class="value"><?php echo count($session_details); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Enrolled Students</div>
                        <div class="value"><?php echo count($course_report); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">Average Attendance</div>
                        <div class="value">
                            <?php 
                                $avg = count($course_report) > 0 ? array_sum(array_column($course_report, 'attendance_percentage')) / count($course_report) : 0;
                                echo number_format($avg, 1);
                            ?>%
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Student Attendance Summary</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Sessions Attended</th>
                            <th>Total Sessions</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($course_report as $student): ?>
                            <?php
                                $percentage = floatval($student['attendance_percentage']);
                                $percentageClass = $percentage >= 75 ? 'good' : ($percentage >= 50 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo $student['attended_sessions']; ?></td>
                                <td><?php echo $student['total_sessions']; ?></td>
                                <td>
                                    <span class="percentage <?php echo $percentageClass; ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card">
                <h3>Session Details</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Code</th>
                            <th>Present</th>
                            <th>Total</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($session_details as $session): ?>
                            <?php
                                $sessionPercentage = $session['total_students'] > 0 ? ($session['present_count'] / $session['total_students'] * 100) : 0;
                                $sessionClass = $sessionPercentage >= 75 ? 'good' : ($sessionPercentage >= 50 ? 'warning' : 'danger');
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($session['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($session['start_time'])); ?></td>
                                <td><strong><?php echo $session['attendance_code']; ?></strong></td>
                                <td><?php echo $session['present_count']; ?></td>
                                <td><?php echo $session['total_students']; ?></td>
                                <td>
                                    <span class="percentage <?php echo $sessionClass; ?>">
                                        <?php echo number_format($sessionPercentage, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>