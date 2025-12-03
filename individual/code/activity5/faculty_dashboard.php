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
    <title>Faculty Dashboard - Ashesi Attendance System</title>
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
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        .form-group input:focus {
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
        .btn-approve {
            background: #2e7d32;
        }
        .btn-approve:hover {
            background: #1b5e20;
        }
        .btn-reject {
            background: #c62828;
        }
        .btn-reject:hover {
            background: #b71c1c;
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
            margin-bottom: 15px;
        }
        .course-meta {
            display: flex;
            justify-content: space-between;
            color: #666;
            font-size: 14px;
        }
        .request-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .request-info h4 {
            color: #333;
            margin-bottom: 5px;
        }
        .request-info p {
            color: #666;
            font-size: 14px;
        }
        .request-actions {
            display: flex;
            gap: 10px;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-pending {
            background: #fff3e0;
            color: #e65100;
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
        <div style="display: flex; gap: 20px; align-items: center;">
            <a href="faculty_dashboard.php" style="color: white; text-decoration: none;">Dashboard</a>
            <a href="class_sessions.php" style="color: white; text-decoration: none;">Class Sessions</a>
            <a href="reports.php" style="color: white; text-decoration: none;">Reports</a>
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