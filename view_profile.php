<?php
session_start();

// 1. Security Check: User must be logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

require_once 'data/db_connect.php';
require_once 'phpqrcode/qrlib.php';

// 2. Get user_id from GET parameter
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    die("<h1>Error</h1><p>User ID not provided.</p>");
}
$profile_user_id = intval($_GET['user_id']);

// 3. Authorization Check
$is_authorized = false;
$viewer_role = $_SESSION['role'];
$viewer_user_id = $_SESSION['user_id'];

if (in_array($viewer_role, ['admin', 'director'])) {
    $is_authorized = true; // Staff can view any report
} elseif ($viewer_user_id == $profile_user_id) {
    $is_authorized = true; // Users can view their own profile
} elseif ($viewer_role === 'guardian') {
    // Check if the profile being viewed is one of the guardian's children
    $sql_verify = "
        SELECT COUNT(*) as count 
        FROM student_guardian_map sgm 
        JOIN guardians g ON sgm.guardian_id = g.guardian_id 
        JOIN students s ON sgm.student_id = s.student_id
        WHERE g.user_id = ? AND s.user_id = ?
    ";
    $stmt_verify = mysqli_prepare($conn, $sql_verify);
    mysqli_stmt_bind_param($stmt_verify, "ii", $viewer_user_id, $profile_user_id);
    mysqli_stmt_execute($stmt_verify);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['count'] > 0) {
        $is_authorized = true;
    }
    mysqli_stmt_close($stmt_verify);
}

if (!$is_authorized) {
    die("<h1>Access Denied</h1><p>You are not authorized to view this profile.</p>");
}

// 4. Fetch user data from the database.
$sql = "
    SELECT
        u.user_id, u.username, u.role, u.created_at as user_created_at,
        s.student_id, s.first_name as s_fname, s.middle_name as s_mname, s.last_name as s_lname, s.age as s_age, s.gender as s_gender, s.grade_level, s.blood_type, s.photo_path as s_photo, s.registered_at as s_reg_at, s.emergency_contact as s_emergency, s.nationality as s_nationality, s.email as s_email, s.phone as s_phone, s.status as s_status,
        t.teacher_id, t.first_name as t_fname, t.middle_name as t_mname, t.last_name as t_lname, t.gender as t_gender, t.photo_path as t_photo, t.hire_date, t.phone as t_phone, t.email as t_email, t.status as t_status,
        g.guardian_id, g.name as g_name, g.phone as g_phone, g.email as g_email,
        c.name as classroom_name,
        guardian.name as guardian_for_student_name, guardian.phone as guardian_for_student_phone
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    LEFT JOIN guardians g ON u.user_id = g.user_id
    LEFT JOIN class_assignments ca ON s.student_id = ca.student_id
    LEFT JOIN classrooms c ON ca.classroom_id = c.classroom_id
    LEFT JOIN student_guardian_map sgm ON s.student_id = sgm.student_id
    LEFT JOIN guardians as guardian ON sgm.guardian_id = guardian.guardian_id
    WHERE u.user_id = ?
    GROUP BY u.user_id
";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $profile_user_id);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user_data) {
    die("<h1>Error</h1><p>User not found.</p>");
}

// Fetch student profile change logs if the user is a student
$profile_logs = [];
if ($user_data['role'] === 'student' || $user_data['role'] === 'rep') {
    $sql_logs = "SELECT * FROM student_profile_logs WHERE student_id = ? ORDER BY changed_at DESC";
    $stmt_logs = mysqli_prepare($conn, $sql_logs);
    mysqli_stmt_bind_param($stmt_logs, "i", $user_data['student_id']);
    mysqli_stmt_execute($stmt_logs);
    $result_logs = mysqli_stmt_get_result($stmt_logs);
    while ($row = mysqli_fetch_assoc($result_logs)) {
        $profile_logs[] = $row;
    }
    mysqli_stmt_close($stmt_logs);
}

// 5. Process data for the ID card based on role
$id_card_data = [];
switch ($user_data['role']) {
    case 'student':
    case 'rep':
        $id_card_data['name'] = trim($user_data['s_fname'] . ' ' . $user_data['s_mname'] . ' ' . $user_data['s_lname']);
        $id_card_data['photo'] = $user_data['s_photo'];
        $id_card_data['details'] = [
            'Grade' => $user_data['grade_level'] . ' (' . ($user_data['classroom_name'] ?? 'N/A') . ')',
            'Guardian' => $user_data['guardian_for_student_name'] ?? 'N/A',
            'Guardian Phone' => $user_data['guardian_for_student_phone'] ?? 'N/A'
        ];
        $id_card_data['issue_date'] = new DateTime($user_data['s_reg_at']);
        break;
    case 'teacher':
        $id_card_data['name'] = trim($user_data['t_fname'] . ' ' . $user_data['t_mname'] . ' ' . $user_data['t_lname']);
        $id_card_data['photo'] = $user_data['t_photo'];
        $id_card_data['details'] = [
            'Role' => 'Teacher',
            'Phone' => $user_data['t_phone'],
            'Email' => $user_data['t_email']
        ];
        $id_card_data['issue_date'] = new DateTime($user_data['hire_date']);
        break;
    default: // admin, director, guardian
        $id_card_data['name'] = $user_data['g_name'] ?? $user_data['username'];
        $id_card_data['photo'] = null;
        $id_card_data['details'] = [
            'Role' => ucfirst($user_data['role']),
            'Phone' => $user_data['g_phone'] ?? 'N/A',
            'Email' => $user_data['g_email'] ?? 'N/A'
        ];
        $id_card_data['issue_date'] = new DateTime($user_data['user_created_at']);
        break;
}

// 6. Generate QR Code
$qr_dir = 'uploads/qrcodes/';
if (!is_dir($qr_dir)) { mkdir($qr_dir, 0755, true); }
$qr_code_path = $qr_dir . 'user_' . $profile_user_id . '.png';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];
$qr_content = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/view_profile.php?user_id=' . $profile_user_id;
if (!file_exists($qr_code_path)) {
    QRcode::png($qr_content, $qr_code_path, QR_ECLEVEL_L, 4);
}

mysqli_close($conn);

$page_title = 'User Profile';
include 'header.php';
?>
<style>
    /* --- New International ID Card Style --- */
    .id-card-preview {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        position: relative;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    .id-card-header {
        background-color: var(--primary-color);
        color: white;
        padding: 10px 15px;
        display: flex;
        align-items: center;
    }
    .id-card-header img {
        width: 35px;
        height: 35px;
        margin-right: 10px;
    }
    .id-card-header h5 {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
    }
    .id-card-body {
        padding: 15px;
        display: flex;
        gap: 15px;
    }
    .id-card-photo {
        width: 100px;
        height: 120px;
        object-fit: cover;
        border-radius: 8px;
        border: 3px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .id-card-info h4 {
        margin: 0 0 5px 0;
        font-size: 1.3rem;
        font-weight: 700;
        color: #1a253c;
    }
    .id-card-info .role-badge {
        background-color: #e9ecef;
        color: #495057;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 10px;
    }
    .id-card-info p {
        margin: 0 0 4px 0;
        font-size: 0.8rem;
        color: #555;
    }
    .id-card-footer {
        padding: 0 15px 10px 15px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
    }
    .id-card-qr img {
        width: 50px;
        height: 50px;
        border-radius: 5px;
    }
    .id-card-dates {
        text-align: right;
        font-size: 0.65rem;
        color: #777;
    }
    .profile-details .list-group-item {
        display: flex;
        justify-content: space-between;
    }
    .profile-details .label {
        font-weight: 600;
        color: var(--gray);
    }
</style>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">User Profile</h1>
        <div>
            <a href="generate_vcard.php?user_id=<?php echo $profile_user_id; ?>" class="btn btn-success"><i class="bi bi-person-vcard"></i> Download vCard</a>
            <a href="generate_user_id_card_pdf.php?user_id=<?php echo $profile_user_id; ?>" class="btn btn-info" target="_blank"><i class="bi bi-printer-fill"></i> Print ID Card</a>
            <?php if (in_array($viewer_role, ['admin', 'director'])): ?>
                <?php if (in_array($user_data['role'], ['student', 'rep'])): ?>
                    <a href="edit_student.php?id=<?php echo $user_data['student_id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-fill"></i> Edit Profile</a>
                <?php elseif ($user_data['role'] === 'teacher'): ?>
                    <a href="edit_teacher.php?id=<?php echo $user_data['teacher_id']; ?>" class="btn btn-primary"><i class="bi bi-pencil-fill"></i> Edit Profile</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left Column: ID Card Preview -->
        <div class="col-lg-5 col-xl-4">
            <div class="id-card-preview">
                <div class="id-card-header">
                    <img src="assets/school_logo.png" alt="School Logo">
                    <h5>Old Model School</h5>
                </div>
                <div class="id-card-body">
                    <img src="<?php echo (!empty($id_card_data['photo']) && file_exists($id_card_data['photo'])) ? $id_card_data['photo'] : 'assets/default-avatar.png'; ?>" alt="Profile Photo" class="id-card-photo">
                    <div class="id-card-info">
                        <h4><?php echo htmlspecialchars($id_card_data['name']); ?></h4>
                        <span class="role-badge"><?php echo htmlspecialchars(ucfirst($user_data['role'])); ?></span>
                        <p><strong>ID:</strong> <?php echo htmlspecialchars($user_data['username']); ?></p>
                    </div>
                </div>
                <div class="id-card-footer">
                    <div class="id-card-qr">
                        <img src="<?php echo $qr_code_path; ?>" alt="QR Code">
                    </div>
                    <div class="id-card-dates">
                        <?php
                            $issue_date = $id_card_data['issue_date'];
                            $expiry_date = (clone $issue_date)->modify('+1 year');
                        ?>
                        <p>Issued: <?php echo $issue_date->format('Y-m-d'); ?></p>
                        <p>Expires: <?php echo $expiry_date->format('Y-m-d'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Full Details -->
        <div class="col-lg-7 col-xl-8 profile-details">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="profile-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details-pane" type="button" role="tab"><i class="bi bi-person-lines-fill me-2"></i>Details</button>
                        </li>
                        <?php if (!empty($profile_logs)): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="log-tab" data-bs-toggle="tab" data-bs-target="#log-pane" type="button" role="tab"><i class="bi bi-clock-history me-2"></i>Activity Log</button>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-body tab-content" id="profile-tabs-content">
                    <div class="tab-pane fade show active" id="details-pane" role="tabpanel">
                        <?php foreach($id_card_data['details'] as $label => $value): ?>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <span class="label"><?php echo htmlspecialchars($label); ?></span>
                                    <span><?php echo htmlspecialchars($value); ?></span>
                                </li>
                            </ul>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($profile_logs)): ?>
                    <div class="tab-pane fade" id="log-pane" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Field</th>
                                        <th>Old Value</th>
                                        <th>New Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($profile_logs as $log): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d H:i', strtotime($log['changed_at'])); ?></td>
                                        <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['field_changed']))); ?></td>
                                        <td><?php echo htmlspecialchars($log['old_value']); ?></td>
                                        <td><?php echo htmlspecialchars($log['new_value']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>