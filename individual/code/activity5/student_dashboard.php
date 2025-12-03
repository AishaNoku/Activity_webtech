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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_join'])) {
    $course_id = intval($_POST['course_id']);
    
    $stmt = $conn->prepare("SELECT id FROM enrollment_requests WHERE student_id = ? AND course_id = ?");
    $stmt->bind_param("ii", $user_id, $course_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $error = "You have already requested to join this course.";
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO enrollment_requests (student_id, course_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $course_id);
        
        if ($stmt->execute()) {
            $message = "Join request submitted successfully!";
        } else {
            $error = "Failed to submit request. Please try again.";
        }
    }
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT c.*, u.full_name as faculty_name,
           (SELECT COUNT(*) FROM enrollment_requests WHERE course_id = c.id AND status = 'approved') as student_count
    FROM courses c
    JOIN enrollment_requests er ON c.id = er.course_id
    JOIN users u ON c.faculty_id = u.id
    WHERE er.student_id = ? AND er.status = 'approved'
    ORDER BY c.course_code
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$enrolled_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT c.*, u.full_name as faculty_name, er.status, er.requested_at
    FROM courses c
    JOIN enrollment_requests er ON c.id = er.course_id
    JOIN users u ON c.faculty_id = u.id
    WHERE er.student_id = ? AND er.status = 'pending'
    ORDER BY er.requested_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT c.*, u.full_name as faculty_name,
           (SELECT COUNT(*) FROM enrollment_requests WHERE course_id = c.id AND status = 'approved') as student_count
    FROM courses c
    JOIN users u ON c.faculty_id = u.id
    WHERE c.id NOT IN (
        SELECT course_id FROM enrollment_requests WHERE student_id = ?
    )
    ORDER BY c.course_code
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$available_courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Ashesi Attendance System</title>
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
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 16px;
            color: #666;
            border-bottom: 3px solid transparent;
            margin-bottom: -12px;
        }
        .tab.active {
            color: #8B1538;
            border-bottom-color: #8B1538;
            font-weight: 600;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .course-code {
            color: #8B1538;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .course-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        .course-faculty {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .btn {
            padding: 10px 20px;
            background: #8B1538;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            width: 100%;
        }
        .btn:hover {
            background: #6d1029;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-enrolled {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .badge-pending {
            background: #fff3e0;
            color: #e65100;
        }
        .badge-rejected {
            background: #ffebee;
            color: #c62828;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
        }
        .pending-card {
            background: #fff3e0;
            border-left: 4px solid #e65100;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1>Ashesi Attendance System</h1>
        <div style="display: flex; gap: 20px; align-items: center;">
            <a href="student_dashboard.php" style="color: white; text-decoration: none;">Dashboard</a>
            <a href="mark_attendance.php" style="color: white; text-decoration: none;">Mark Attendance</a>
            <a href="student_reports.php" style="color: white; text-decoration: none;">My Reports</a>
        </div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            <div class="user-avatar"><?php echo get_initials($_SESSION['full_name']); ?></div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('enrolled')">My Courses</button>
            <button class="tab" onclick="showTab('pending')">
                Pending Requests
                <?php if (count($pending_requests) > 0): ?>
                    <span class="badge badge-pending"><?php echo count($pending_requests); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab" onclick="showTab('available')">Browse Courses</button>
        </div>
        <div id="enrolled" class="tab-content active">
            <h2 class="section-title">Enrolled Courses</h2>
            <?php if (empty($enrolled_courses)): ?>
                <div class="empty-state">
                    <p>You are not enrolled in any courses yet.</p>
                    <p>Browse available courses and request to join!</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($enrolled_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-faculty">Instructor: <?php echo htmlspecialchars($course['faculty_name']); ?></div>
                            <div class="course-meta">
                                <span><?php echo $course['student_count']; ?> Students</span>
                                <span><?php echo htmlspecialchars($course['schedule'] ?: 'No schedule'); ?></span>
                            </div>
                            <span class="badge badge-enrolled">Enrolled</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div id="pending" class="tab-content">
            <h2 class="section-title">Pending Enrollment Requests</h2>
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <p>No pending requests.</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($pending_requests as $course): ?>
                        <div class="course-card pending-card">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-faculty">Instructor: <?php echo htmlspecialchars($course['faculty_name']); ?></div>
                            <div class="course-meta">
                                <span>Requested: <?php echo date('M d, Y', strtotime($course['requested_at'])); ?></span>
                            </div>
                            <span class="badge badge-pending">Pending Approval</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="available" class="tab-content">
            <h2 class="section-title">Available Courses</h2>
            <?php if (empty($available_courses)): ?>
                <div class="empty-state">
                    <p>No courses available to join at the moment.</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($available_courses as $course): ?>
                        <div class="course-card">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-faculty">Instructor: <?php echo htmlspecialchars($course['faculty_name']); ?></div>
                            <div class="course-meta">
                                <span><?php echo $course['student_count']; ?> Students</span>
                                <span><?php echo htmlspecialchars($course['schedule'] ?: 'No schedule'); ?></span>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="request_join" class="btn">Request to Join</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>