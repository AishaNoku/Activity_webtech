
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    program VARCHAR(100) NOT NULL,
    year_of_study INT NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('faculty', 'student') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(20) NOT NULL UNIQUE,
    course_name VARCHAR(150) NOT NULL,
    faculty_id INT NOT NULL,
    schedule VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS enrollment_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_request (student_id, course_id)
);

CREATE TABLE IF NOT EXISTS class_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    attendance_code VARCHAR(6) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    INDEX idx_attendance_code (attendance_code),
    INDEX idx_session_date (session_date)
);
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    status ENUM('present', 'absent') DEFAULT 'present',
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES class_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (session_id, student_id)
);

INSERT INTO `courses` (`id`, `course_code`, `course_name`, `faculty_id`, `schedule`, `created_at`) VALUES
(1, 'CS101', 'Introduction to Computer Science', 1, 'Mon, Wed, Fri', '2025-11-21 11:37:07'),
(2, 'CS201', 'Data Structures & Algorithms', 1, 'Tue, Thu', '2025-11-21 11:37:07'),
(3, 'CS301', 'Database Management Systems', 1, 'Mon, Wed', '2025-11-21 11:37:07'),
(4, 'CS401', 'Software Engineering', 1, 'Tue, Thu, Fri', '2025-11-21 11:37:07');


INSERT INTO `enrollment_requests` (`id`, `student_id`, `course_id`, `status`, `requested_at`, `responded_at`) VALUES
(1, 1238, 1, 'pending', '2025-12-03 08:34:30', NULL);

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(1, 'Prof. Angela Chanakira', 'angela.chanakira@ashesi.edu.gh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', '2025-11-21 11:37:07'),
(1117, '<script>alert(\"hello\");</script>', 'hanna.iiwe@ashesi.edu.gh', 'RGMugabe@123', 'student', '2025-11-24 12:41:01'),
(1237, '<script>alert(\"hello\");</script>', 'hanna.iwe@ashesi.edu.gh', 'RGMugabe@123', '', '2025-11-24 12:36:19'),
(1238, 'Nokutenda Chihuri', 'nokutenda@gmail.com', '$2y$10$1YJT151HGIVGnqKOJyHoCeHY/A023vLqSFymbeiRHXVLwlyX9Xwxu', 'student', '2025-12-03 08:34:03');
