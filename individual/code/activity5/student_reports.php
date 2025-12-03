<?php
require_once 'config.php';

require_login();
if ($_SESSION['role'] !== 'student') {
    header("Location: faculty_dashboard.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        c.course_code,
        c.course_name,
        COUNT(DISTINCT cs.id) as total_sessions,
        COUNT(DISTINCT a.session_id) as attended_sessions,
        ROUND((COUNT(DISTINCT a.session_id) / COUNT(DISTINCT cs.id) * 100), 1) as attendance_percentage
    FROM courses c
    JOIN enrollment_requests er ON c.id = er.course_id
    JOIN class_sessions cs ON c.id = cs.course_id
    LEFT JOIN attendance a ON cs.id = a.session_id AND a.student_id = ?
    WHERE er.student_id = ? AND er.status = 'approved'
    GROUP BY c.id
    ORDER BY c.course_code
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$course_stats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT 
        cs.session_date,
        cs.start_time,
        c.course_code,
        c.course_name,
        CASE WHEN a.id IS NOT NULL THEN 'Present' ELSE 'Absent' END as status,
        a.marked_at
    FROM class_sessions cs
    JOIN courses c ON cs.course_id = c.id
    JOIN enrollment_requests er ON c.id = er.course_id
    LEFT JOIN attendance a ON cs.id = a.session_id AND a.student_id = ?
    WHERE er.student_id = ? AND er.status = 'approved'
    ORDER BY cs.session_date DESC, cs.start_time DESC
    LIMIT 50
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$daily_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - Ashesi Attendance System</title>
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #8B1538;
        }
        .stat-card .course-code {
            color: #8B1538;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .stat-card .course-name {
            color: #333;
            font-size: 16px;
            margin-bottom: 15px;
        }
        .stat-card .percentage {
            font-size: 36px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-card .percentage.good {
            color: #2e7d32;
        }
        .stat-card .percentage.warning {
            color: #f57c00;
        }
        .stat-card .percentage.danger {
            color: #c62828;
        }
        .stat-card .stat-details {
            color: #666;
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
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-present {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-absent {
            background: #ffebee;
            color: #c62828;
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
        <h2 style="margin-bottom: 20px; color: #333;">Overall Attendance Summary</h2>
        
        <?php if (empty($course_stats)): ?>
            <div class="card">
                <div class="empty-state">
                    <p>No attendance records available yet.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="stats-grid">
                <?php foreach ($course_stats as $stat): ?>
                    <?php
                        $percentage = floatval($stat['attendance_percentage']);
                        $percentageClass = $percentage >= 75 ? 'good' : ($percentage >= 50 ? 'warning' : 'danger');
                    ?>
                    <div class="stat-card">
                        <div class="course-code"><?php echo htmlspecialchars($stat['course_code']); ?></div>
                        <div class="course-name"><?php echo htmlspecialchars($stat['course_name']); ?></div>
                        <div class="percentage <?php echo $percentageClass; ?>">
                            <?php echo number_format($percentage, 1); ?>%
                        </div>
                        <div class="stat-details">
                            <?php echo $stat['attended_sessions']; ?> of <?php echo $stat['total_sessions']; ?> classes attended
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <h3>Daily Attendance Records</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Course</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daily_records as $record): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['course_code']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($record['course_name']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($record['session_date'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($record['start_time'])); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($record['status']); ?>">
                                        <?php echo $record['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $record['marked_at'] ? date('g:i A', strtotime($record['marked_at'])) : '-'; ?>
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