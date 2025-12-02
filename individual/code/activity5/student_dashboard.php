<?php
include 'db.php';
require_once 'config.php';

// Require login and check role
require_login();
if ($_SESSION['role'] !== 'student') {
    header("Location: faculty_dashboard.php");
    exit();
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle course join request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_join'])) {
    $course_id = intval($_POST['course_id']);
    
    // Check if request already exists
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

// Fetch enrolled courses (approved requests)
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

// Fetch pending requests
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

// Fetch available courses (not yet requested or enrolled)
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
    <link rel="stylesheet" href="student.css">
    <title>Student Dashboard - Ashesi Attendance System</title>
    
</head>
<body>
    <nav class="navbar">
        <h1>Ashesi Attendance System</h1>
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

        <!-- Enrolled Courses Tab -->
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

        <!-- Pending Requests Tab -->
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

        <!-- Available Courses Tab -->
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
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>