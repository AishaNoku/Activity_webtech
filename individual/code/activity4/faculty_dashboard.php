<?php
include 'db.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    $course_code = sanitize_input($_POST['course_code'] ?? '');
    $course_name = sanitize_input($_POST['course_name'] ?? '');
    $schedule = sanitize_input($_POST['schedule'] ?? '');

    if (!preg_match('/^[A-Z]{2,4}\d{3,4}$/', $course_code)) {
        $error = "Course code must be 2-4 letters followed by 3-4 digits (e.g., CS101)";
    } elseif (!preg_match('/^[a-zA-Z0-9\s\-\&]{5,150}$/', $course_name)) {
        $error = "Course name must be 5-150 characters (letters, numbers, spaces, hyphens, &)";
    } else {
        $stmt = $conn->prepare("INSERT INTO courses (course_code, course_name, faculty_id, schedule) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssis", $course_code, $course_name, $user_id, $schedule);
        
        if ($stmt->execute()) {
            $message = "Course created successfully!";
        } else {
            $error = "Course code already exists or error occurred.";
        }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_request'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    
     $stmt = $conn->prepare("
        UPDATE enrollment_requests er 
        JOIN courses c ON er.course_id = c.id 
        SET er.status = ?, er.responded_at = NOW() 
        WHERE er.id = ? AND c.faculty_id = ?
    ");
    $stmt->bind_param("sii", $action, $request_id, $user_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $message = "Request " . $action . " successfully!";
    }
    $stmt->close();
}

$stmt = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollment_requests WHERE course_id = c.id AND status = 'approved') as student_count
    FROM courses c 
    WHERE c.faculty_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$courses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("
    SELECT er.*, u.full_name, u.email, c.course_code, c.course_name 
    FROM enrollment_requests er
    JOIN users u ON er.student_id = u.id
    JOIN courses c ON er.course_id = c.id
    WHERE c.faculty_id = ? AND er.status = 'pending'
    ORDER BY er.requested_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pending_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="faculty.css">
    <title>Faculty Dashboard - Ashesi Attendance System</title>

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
            <button class="tab active" onclick="showTab('courses')">My Courses</button>
            <button class="tab" onclick="showTab('requests')">
                Enrollment Requests 
                <?php if (count($pending_requests) > 0): ?>
                    <span class="badge badge-pending"><?php echo count($pending_requests); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab" onclick="showTab('create')">Create Course</button>
        </div>

        <div id="courses" class="tab-content active">
            <?php if (empty($courses)): ?>
                <div class="empty-state">
                    <p>You haven't created any courses yet.</p>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                            <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                            <div class="course-meta">
                                <span><?php echo $course['student_count']; ?> Students</span>
                                <span><?php echo htmlspecialchars($course['schedule'] ?: 'No schedule'); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div id="requests" class="tab-content">
            <?php if (empty($pending_requests)): ?>
                <div class="empty-state">
                    <p>No pending enrollment requests.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pending_requests as $request): ?>
                    <div class="request-card">
                        <div class="request-info">
                            <h4><?php echo htmlspecialchars($request['full_name']); ?></h4>
                            <p><?php echo htmlspecialchars($request['email']); ?></p>
                            <p>Requesting to join: <strong><?php echo htmlspecialchars($request['course_code'] . ' - ' . $request['course_name']); ?></strong></p>
                        </div>
                        <div class="request-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" name="handle_request" class="btn btn-approve">Approve</button>
                            </form>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <button type="submit" name="handle_request" class="btn btn-reject">Reject</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div id="create" class="tab-content">
            <div class="card">
                <h3>Create New Course</h3>
                <form method="POST" id="createCourseForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_code">Course Code</label>
                            <input type="text" id="course_code" name="course_code" 
                                   placeholder="e.g., CS101" required 
                                   pattern="[A-Z]{2,4}[0-9]{3,4}" 
                                   title="2-4 uppercase letters followed by 3-4 digits">
                        </div>
                        <div class="form-group">
                            <label for="schedule">Schedule</label>
                            <input type="text" id="schedule" name="schedule" 
                                   placeholder="e.g., Mon, Wed, Fri">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="course_name">Course Name</label>
                        <input type="text" id="course_name" name="course_name" 
                               placeholder="e.g., Introduction to Computer Science" required
                               minlength="5" maxlength="150">
                    </div>
                    <button type="submit" name="create_course" class="btn">Create Course</button>
                </form>
            </div>
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