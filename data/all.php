<?php
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "sms";

// Create connection without selecting a DB, to allow DB creation
$conn = mysqli_connect($servername, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

/*
-- WARNING: The following line will delete your entire database.
-- It is commented out for safety. Uncomment it only if you want to completely reset your database.
-- $sql_drop_db = "DROP DATABASE IF EXISTS `$dbname`";
-- mysqli_query($conn, $sql_drop_db);
*/

// Create database if it doesn't exist
$sql_create_db = "CREATE DATABASE IF NOT EXISTS `$dbname`";
if (!mysqli_query($conn, $sql_create_db)) {
    die("Error creating database: " . mysqli_error($conn));
}

// Select the database
mysqli_select_db($conn, $dbname);

// ================= USERS =================
$sql_users = "CREATE TABLE IF NOT EXISTS users (
    user_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student', 'director', 'rep', 'guardian') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    google2fa_secret VARCHAR(255) NULL,
    google2fa_enabled BOOLEAN NOT NULL DEFAULT 0
)";

// ================= STUDENTS =================
$sql_students = "CREATE TABLE IF NOT EXISTS students (
    student_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT(2) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    nationality VARCHAR(50) NOT NULL DEFAULT 'Ethiopian',
    religion VARCHAR(50) NOT NULL DEFAULT 'Amhara',
    city VARCHAR(50) NOT NULL DEFAULT 'Debre Markos',
    wereda VARCHAR(100) NOT NULL,
    kebele VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE,
    emergency_contact INT(20) NOT NULL,
    blood_type VARCHAR(5) NULL DEFAULT NULL,
    grade_level ENUM('9','10','11','12') NOT NULL,
    stream ENUM('Natural','Social','Both') DEFAULT 'Both' NOT NULL,
    last_school VARCHAR(100) NOT NULL,
    last_score FLOAT NOT NULL,
    last_grade VARCHAR(10) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    photo_path VARCHAR(255) DEFAULT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";


// ================= GUARDIANS =================
$sql_guardians = "CREATE TABLE IF NOT EXISTS guardians (
    guardian_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) UNIQUE,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20)NOT NULL,
    email VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
)";

// ================= STUDENT GUARDIAN MAP =================
$sql_student_guardian_map = "CREATE TABLE IF NOT EXISTS student_guardian_map (
    map_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    guardian_id INT(11) NOT NULL,
    relation VARCHAR(50) NOT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_id) REFERENCES guardians(guardian_id) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_guardian` (`student_id`, `guardian_id`)
)";


// ================= TEACHERS =================
$sql_teachers = "CREATE TABLE IF NOT EXISTS teachers (
    teacher_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male','female') NOT NULL,
    nationality VARCHAR(50) NOT NULL DEFAULT 'Ethiopian',
    religion VARCHAR(50) NOT NULL DEFAULT 'Amhara',
    city VARCHAR(50) NOT NULL DEFAULT 'Debre Markos',
    wereda VARCHAR(100) NOT NULL,
    kebele VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    photo_path VARCHAR(255) DEFAULT NULL,
    document_path VARCHAR(255) DEFAULT NULL,
    hire_date DATE NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// ================= SUBJECTS =================
$sql_subjects = "CREATE TABLE IF NOT EXISTS subjects (
    subject_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100),
    code VARCHAR(20) UNIQUE,
    grade_level INT,
    periods_per_week INT NOT NULL DEFAULT 1,
    stream ENUM('Natural','Social','Both') DEFAULT 'Both',
    description TEXT
)";

// ================= CLASS ASSIGNMENTS =================
$sql_class_assignments = "CREATE TABLE IF NOT EXISTS class_assignments (
    schedule_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    classroom_id INT(11) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id)
)";

// ================= CLASS ASSIGNMENT HISTORY =================
$sql_class_assignment_history = "CREATE TABLE IF NOT EXISTS class_assignment_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    classroom_id INT(11) NOT NULL,
    assigned_date DATETIME NOT NULL,
    left_date DATETIME NULL,
    assigned_by_user_id INT(11) NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= SUBJECT ASSIGNMENTS =================
$sql_subject_assignments = "CREATE TABLE IF NOT EXISTS subject_assignments (
    assignment_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    subject_id INT(11) NOT NULL,
    classroom_id INT(11) NOT NULL,
    teacher_id INT(11) NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id),
    UNIQUE KEY `unique_assignment` (`subject_id`, `classroom_id`, `teacher_id`)
)";

// ================= CLASSROOMS =================
$sql_classrooms = "CREATE TABLE IF NOT EXISTS classrooms (
    classroom_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    grade_level INT(2) NOT NULL,
    capacity INT NOT NULL,
    resources TEXT
)";

// ================= WEEKLY SHIFT ASSIGNMENTS =================
$sql_weekly_shifts = "CREATE TABLE IF NOT EXISTS weekly_shift_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    year INT NOT NULL,
    week_of_year INT NOT NULL,
    grade_level INT NOT NULL,
    shift ENUM('Morning', 'Afternoon') NOT NULL,
    UNIQUE KEY `unique_shift_assignment` (`year`, `week_of_year`, `grade_level`)
)";

// ================= SCHEDULE PERIODS (Template) =================
$sql_schedule_periods = "CREATE TABLE IF NOT EXISTS schedule_periods (
    period_id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    shift ENUM('Morning', 'Afternoon') NOT NULL,
    period_number INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_break BOOLEAN DEFAULT 0
)";

// ================= CLASS SCHEDULE (The actual timetable) =================
$sql_class_schedule = "CREATE TABLE IF NOT EXISTS class_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT(11) NOT NULL,
    period_id INT(11) NOT NULL,
    subject_assignment_id INT(11) NOT NULL,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE CASCADE,
    FOREIGN KEY (period_id) REFERENCES schedule_periods(period_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_assignment_id) REFERENCES subject_assignments(assignment_id) ON DELETE CASCADE,
    UNIQUE KEY `unique_class_period` (`classroom_id`, `period_id`)
)";

// ================= ATTENDANCE =================
$sql_attendance = "CREATE TABLE IF NOT EXISTS attendance (
    attendance_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    classroom_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    taken_by_teacher_id INT,
    teacher_id INT(11) NOT NULL,
    date DATE,
    status ENUM('Present','Absent','Late','Excused') NOT NULL,
    locked BOOLEAN DEFAULT 0,
    marked_by VARCHAR(100) NOT NULL,
    marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id),
    UNIQUE KEY `unique_attendance` (`student_id`, `subject_id`, `date`)
)";

// ================= GRADES =================
$sql_grades = "CREATE TABLE IF NOT EXISTS grades (
    grade_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    teacher_id INT(11) NOT NULL,
    test DECIMAL(5,2) NOT NULL,
    assignment DECIMAL(5,2) NOT NULL,
    activity DECIMAL(5,2) NOT NULL,
    exercise DECIMAL(5,2) NOT NULL,
    midterm DECIMAL(5,2) NOT NULL,
    final DECIMAL(5,2) NOT NULL,
    total DECIMAL(5,2) NOT NULL,
    remarks VARCHAR(255) NULL DEFAULT NULL,
    updated_by VARCHAR(100) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id),
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id),
    UNIQUE KEY `unique_grade_entry` (`student_id`, `subject_id`)
)";

// ================= MESSAGES =================
$sql_messages = "CREATE TABLE IF NOT EXISTS `messages` (
    message_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    parent_message_id INT(11) NULL,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sender_deleted TINYINT(1) NOT NULL DEFAULT 0,
    receiver_deleted TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// ================= MESSAGE ATTACHMENTS =================
$sql_message_attachments = "CREATE TABLE IF NOT EXISTS message_attachments (
    attachment_id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT(11) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE
)";
// ================= REPORTS =================
$sql_reports = "CREATE TABLE IF NOT EXISTS reports (
    report_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    generated_by_user_id INT(11) NOT NULL,
    classroom_id INT(11) NULL,
    type ENUM('Attendance','Behavior','Academic') NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by_user_id) REFERENCES users(user_id),
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE SET NULL
)";

// ================= NEWS =================
$sql_news = "CREATE TABLE IF NOT EXISTS news (
    news_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(255),
    author_id INT,
    status ENUM('published', 'draft') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE SET NULL
)";

// ================= GALLERY =================
$sql_gallery = "CREATE TABLE IF NOT EXISTS gallery (
    gallery_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    description TEXT,
    image_path VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// ================= Profile =================
$sql_profile = "CREATE TABLE IF NOT EXISTS profile (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,   -- unique ID
    user_id INT NOT NULL,                             -- from users table
    student_id INT NOT NULL,                          -- from students table
    full_name VARCHAR(150) NOT NULL,                  -- first + middle + last name
    age INT NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    nationality VARCHAR(100) NOT NULL,
    class_level VARCHAR(50) NOT NULL,                 -- e.g. Grade 9, Grade 10
    section VARCHAR(10) NOT NULL,                     -- e.g. A, B, C
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- auto give day
    emergency_contact_name VARCHAR(100) NOT NULL,
    emergency_contact_phone VARCHAR(20) NOT NULL,
    
    photo LONGBLOB,                                   -- student photo (big image)
    qr_code BLOB,                                     -- QR code image

    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_profile` (`student_id`)
)";

// ================= ABSENCE =================
$sql_absent = "CREATE TABLE IF NOT EXISTS `absence_excuses` (
  `excuse_id` int(11) NOT NULL AUTO_INCREMENT,
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `explanation` text NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`excuse_id`),
  UNIQUE KEY `unique_excuse` (`attendance_id`),
  KEY `student_id` (`student_id`),
  KEY `reviewed_by` (`reviewed_by`),
  FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= GRADE DEADLINES =================
$sql_grade_deadlines = "CREATE TABLE IF NOT EXISTS grade_deadlines (
    deadline_id INT AUTO_INCREMENT PRIMARY KEY,
    classroom_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    deadline_date DATE NOT NULL,
    set_by INT(11) NULL,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (set_by) REFERENCES users(user_id) ON DELETE SET NULL,
    UNIQUE KEY `unique_deadline` (`classroom_id`, `subject_id`)
)";

// ================= NOTIFICATIONS =================
$sql_notifications = "CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= GRADE LOGS =================
$sql_grade_logs = "CREATE TABLE IF NOT EXISTS grade_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    grade_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    subject_id INT(11) NOT NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    changed_by_user_id INT(11),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (grade_id) REFERENCES grades(grade_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= SYSTEM LOGS =================
$sql_system_logs = "CREATE TABLE IF NOT EXISTS system_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    username_attempt VARCHAR(255),
    action VARCHAR(100) NOT NULL,
    status VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= ANNOUNCEMENTS =================
$sql_announcements = "CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    classroom_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (classroom_id) REFERENCES classrooms(classroom_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= EXAM ROOMS =================
$sql_exam_rooms = "CREATE TABLE IF NOT EXISTS exam_rooms (
    room_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    capacity INT NOT NULL
)";

// ================= EXAMS =================
$sql_exams = "CREATE TABLE IF NOT EXISTS exams (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    exam_date DATE NOT NULL,
    semester ENUM('1', '2') NOT NULL,
    type ENUM('Midterm', 'Final') NOT NULL
)";

// ================= EXAM ASSIGNMENTS =================
$sql_exam_assignments = "CREATE TABLE IF NOT EXISTS exam_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    room_id INT NOT NULL,
    seat_number VARCHAR(10),
    FOREIGN KEY (exam_id) REFERENCES exams(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES exam_rooms(room_id) ON DELETE CASCADE,
    UNIQUE KEY `unique_student_exam` (`exam_id`, `student_id`)
)";

// ================= ACADEMIC CALENDAR =================
$sql_academic_calendar = "CREATE TABLE IF NOT EXISTS academic_calendar (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    type ENUM('Holiday', 'Exam Period', 'Term Start', 'Term End', 'Event') NOT NULL,
    description TEXT,
    created_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
)";

// ================= LEAVE REQUESTS =================
$sql_leave_requests = "CREATE TABLE IF NOT EXISTS leave_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT(11) NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    leave_type ENUM('Sick Leave', 'Personal Leave', 'Vacation', 'Other') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    reason TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    reviewed_by INT(11) NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewer_comments TEXT NULL,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= VISITOR PASSES =================
$sql_visitor_passes = "CREATE TABLE IF NOT EXISTS visitor_passes (
    pass_id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(255) NOT NULL,
    reason_for_visit VARCHAR(255) NOT NULL,
    person_to_visit VARCHAR(255) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    issued_by_user_id INT(11) NULL,
    FOREIGN KEY (issued_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


// ================= STUDENT PROFILE LOGS =================
$sql_student_profile_logs = "CREATE TABLE IF NOT EXISTS student_profile_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    changed_by_user_id INT(11) NULL,
    field_changed VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= PASSWORD RESETS =================
$sql_password_resets = "CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

// ================= ACTIVITY LOGS =================
$sql_activity_logs = "CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_name` varchar(255) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


$sql_discipline_record = "CREATE TABLE IF NOT EXISTS discipline_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT(11) NOT NULL,
    reported_by_user_id INT(11) NULL,
    incident_date DATE NOT NULL,
    incident_type VARCHAR(255) NOT NULL,
    description TEXT,
    action_taken VARCHAR(255),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


$sql_new_article_categories = "CREATE TABLE news_article_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql_new_article = "CREATE TABLE news_articles (
    article_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author_id INT NOT NULL,
    publish_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES news_article_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

// ================= EVENTS =================
$sql_events = "CREATE TABLE IF NOT EXISTS events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    location VARCHAR(255),
    image_path VARCHAR(255),
    created_by INT(11) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


// Array of all table creation queries
$queries = [
    $sql_users,
    $sql_teachers,
    $sql_subjects,
    $sql_classrooms,
    $sql_weekly_shifts,
    $sql_schedule_periods,
    $sql_gallery,
    $sql_students,
    $sql_guardians,
    $sql_student_guardian_map,
    $sql_class_assignments,
    $sql_class_assignment_history,
    $sql_student_profile_logs,
    $sql_subject_assignments,
    $sql_attendance,
    $sql_class_schedule,
    $sql_grades,
    $sql_messages,
    $sql_message_attachments,
    $sql_reports,
    $sql_news,
    $sql_profile,
    $sql_absent,
    $sql_grade_deadlines,
    $sql_notifications,
    $sql_grade_logs,
    $sql_system_logs,
    $sql_announcements,
    $sql_exam_rooms,
    $sql_exams,
    $sql_exam_assignments,
    $sql_academic_calendar,
    $sql_leave_requests,
    $sql_password_resets,
    $sql_visitor_passes,
    $sql_activity_logs,
    $sql_discipline_record,
    $sql_new_article_categories,
    $sql_new_article,
    $sql_events
];

// Execute each query
foreach ($queries as $query) {
    if (!mysqli_query($conn, $query)) {
        die("Error creating table: " . mysqli_error($conn));
    }
}

// ================= POPULATE SCHEDULE PERIODS =================
$check_periods = mysqli_query($conn, "SELECT period_id FROM schedule_periods LIMIT 1");
if (mysqli_num_rows($check_periods) == 0) {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $morning_slots = [
        ['08:00:00', '08:40:00'], ['08:40:00', '09:20:00'], ['09:20:00', '10:00:00'],
        ['10:15:00', '10:55:00'], ['10:55:00', '11:35:00'], ['11:35:00', '12:15:00']
    ];
    $afternoon_slots = [
        ['13:00:00', '13:40:00'], ['13:40:00', '14:20:00'], ['14:20:00', '15:00:00'],
        ['15:15:00', '15:55:00'], ['15:55:00', '16:35:00'], ['16:35:00', '17:15:00']
    ];
    $morning_break = ['10:00:00', '10:15:00'];
    $afternoon_break = ['15:00:00', '15:15:00'];

    $shift_morning = 'Morning';
    $shift_afternoon = 'Afternoon';

    $sql = "INSERT INTO schedule_periods (day_of_week, shift, period_number, start_time, end_time, is_break) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);

    foreach ($days as $day) {
        // Morning Shift
        $period_num = 1;
        foreach ($morning_slots as $index => $slot) {
            $is_break = 0;
            mysqli_stmt_bind_param($stmt, "ssissi", $day, $shift_morning, $period_num, $slot[0], $slot[1], $is_break);
            mysqli_stmt_execute($stmt);
            if ($index == 2) { // Add break after 3rd period
                $is_break = 1;
                mysqli_stmt_bind_param($stmt, "ssissi", $day, $shift_morning, $period_num, $morning_break[0], $morning_break[1], $is_break);
                mysqli_stmt_execute($stmt);
            }
            $period_num++;
        }
        // Afternoon Shift
        $period_num = 1;
        foreach ($afternoon_slots as $index => $slot) {
            $is_break = 0;
            mysqli_stmt_bind_param($stmt, "ssissi", $day, $shift_afternoon, $period_num, $slot[0], $slot[1], $is_break);
            mysqli_stmt_execute($stmt);
            if ($index == 2) { // Add break after 3rd period
                $is_break = 1;
                mysqli_stmt_bind_param($stmt, "ssissi", $day, $shift_afternoon, $period_num, $afternoon_break[0], $afternoon_break[1], $is_break);
                mysqli_stmt_execute($stmt);
            }
            $period_num++;
        }
    }
}

// ================= CREATE DEFAULT ADMIN USER =================
// This will create an admin user if one doesn't already exist.
$admin_username = 'admin';
$admin_password = '4321';
$admin_role = 'admin';

// Check if admin user already exists to prevent errors on re-running the script
$sql_check_admin = "SELECT user_id FROM users WHERE username = ?";
$stmt_check = mysqli_prepare($conn, $sql_check_admin);
mysqli_stmt_bind_param($stmt_check, "s", $admin_username);
mysqli_stmt_execute($stmt_check);
mysqli_stmt_store_result($stmt_check);

if (mysqli_stmt_num_rows($stmt_check) == 0) {
    // Admin user does not exist, so create it
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    $sql_insert_admin = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
    $stmt_insert = mysqli_prepare($conn, $sql_insert_admin);
    mysqli_stmt_bind_param($stmt_insert, "sss", $admin_username, $hashed_password, $admin_role);
    mysqli_stmt_execute($stmt_insert);
}

echo "Database and all tables checked/created successfully!";
mysqli_close($conn);
?>
