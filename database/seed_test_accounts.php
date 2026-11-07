<?php
/**
 * Seed test accounts for every system role (CLI & Development only).
 * Usage: php database/seed_test_accounts.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden: This script may only be executed from the command line.');
}

require_once __DIR__ . '/../config/bootstrap.php';

if (strtolower((string)env('APP_ENV', 'local')) === 'production') {
    fwrite(STDERR, "Refusing to seed test accounts in production environment.\n");
    exit(1);
}

$pdo = get_db_connection();

function create_user(PDO $pdo, string $username, string $email, string $password, int $roleId): int {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
    $stmt->execute([$username, $email, $hash, $roleId]);
    if ($pdo->lastInsertId()) {
        return (int)$pdo->lastInsertId();
    }
    $find = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $find->execute([$username]);
    return (int)$find->fetchColumn();
}

$activeSession = $pdo->query("SELECT id FROM academic_sessions WHERE is_active = 1")->fetchColumn();
$activeClass   = $pdo->query("SELECT id FROM classes WHERE level = 1 LIMIT 1")->fetchColumn(); // JSS1

echo "Using session ID: {$activeSession}, class ID: {$activeClass}\n";

$roles = [];
foreach ($pdo->query("SELECT id, name FROM roles") as $r) {
    $roles[$r['name']] = (int)$r['id'];
}

// 1. Admin
$adminId = create_user($pdo, 'admin', 'admin@shoheed.edu', 'admin123', $roles[ROLE_ADMIN]);
echo "Admin user ID: {$adminId}\n";

// 2. Principal
$principalUserId = create_user($pdo, 'principal', 'principal@shoheed.edu', 'principal123', $roles[ROLE_PRINCIPAL]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name) VALUES (?, 'Amina', 'Adeyemi')");
$stmt->execute([$principalUserId]);
$findStaff = $pdo->prepare("SELECT id FROM staff WHERE user_id = ? LIMIT 1");
$findStaff->execute([$principalUserId]);
$principalStaffId = $findStaff->fetchColumn();
echo "Principal user ID: {$principalUserId}, staff ID: {$principalStaffId}\n";

// 3. Class Teacher
$ctUserId = create_user($pdo, 'classteacher', 'classteacher@shoheed.edu', 'teacher123', $roles[ROLE_CLASS_TEACHER]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name, can_teach_subjects, can_be_class_teacher) VALUES (?, 'Biodun', 'Okafor', 1, 1)");
$stmt->execute([$ctUserId]);
$findStaff->execute([$ctUserId]);
$ctStaffId = $findStaff->fetchColumn();

if ($activeSession && $activeClass) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO class_teacher_assignments (staff_id, class_id, academic_session_id) VALUES (?, ?, ?)");
    $stmt->execute([$ctStaffId, $activeClass, $activeSession]);
}
echo "Class Teacher user ID: {$ctUserId}, staff ID: {$ctStaffId}\n";

// 4. Subject Teacher
$stUserId = create_user($pdo, 'subjectteacher', 'subjectteacher@shoheed.edu', 'teacher123', $roles[ROLE_SUBJECT_TEACHER]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name, can_teach_subjects, can_be_class_teacher) VALUES (?, 'Emeka', 'Nwosu', 1, 0)");
$stmt->execute([$stUserId]);
$findStaff->execute([$stUserId]);
$stStaffId = $findStaff->fetchColumn();

$firstSubjectId = $pdo->query("SELECT id FROM subjects LIMIT 1")->fetchColumn();
if ($firstSubjectId && $activeSession && $activeClass) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO subject_teacher_assignments (staff_id, subject_id, class_id, academic_session_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$stStaffId, $firstSubjectId, $activeClass, $activeSession]);
}
echo "Subject Teacher user ID: {$stUserId}, staff ID: {$stStaffId}\n";

// 5. Bursar
$bursarUserId = create_user($pdo, 'bursar', 'bursar@shoheed.edu', 'bursar123', $roles[ROLE_BURSAR]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name) VALUES (?, 'Funke', 'Adeleke')");
$stmt->execute([$bursarUserId]);
echo "Bursar user ID: {$bursarUserId}\n";

// 6. Student
$studentUserId = create_user($pdo, 'student01', 'student01@shoheed.edu', 'student123', $roles[ROLE_STUDENT]);
$stmt = $pdo->prepare("INSERT IGNORE INTO students (user_id, first_name, last_name, admission_number, date_of_birth, gender, status) VALUES (?, 'Chukwuemeka', 'Obiora', 'SHD/2026/001', '2012-03-15', 'Male', 'ACTIVE')");
$stmt->execute([$studentUserId]);
$findStudent = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
$findStudent->execute([$studentUserId]);
$studentId = $findStudent->fetchColumn();

if ($activeSession && $activeClass) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO student_enrollments (student_id, academic_session_id, class_id, status) VALUES (?, ?, ?, 'ACTIVE')");
    $stmt->execute([$studentId, $activeSession, $activeClass]);
}
echo "Student user ID: {$studentUserId}, student ID: {$studentId}\n";

// 7. Guardian record linked to student
$stmt = $pdo->prepare("INSERT IGNORE INTO guardians (user_id, first_name, last_name, phone) VALUES (NULL, 'Ngozi', 'Obiora', '08012345678')");
$stmt->execute();
$guardianId = $pdo->lastInsertId();
if (!$guardianId) {
    $guardianId = $pdo->query("SELECT id FROM guardians WHERE phone = '08012345678'")->fetchColumn();
}

$stmt = $pdo->prepare("INSERT IGNORE INTO student_guardians (student_id, guardian_id, relationship) VALUES (?, ?, 'MOTHER')");
$stmt->execute([$studentId, $guardianId]);
echo "Guardian ID: {$guardianId}\n";

echo "\n=== SEEDING COMPLETE ===\n";
