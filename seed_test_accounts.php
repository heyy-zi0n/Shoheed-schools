<?php
/**
 * Seed test accounts for every system role.
 * Run once: C:\xampp\php\php.exe seed_test_accounts.php
 */

// DEVELOPMENT / TEST ACCOUNT ONLY
// CHANGE BEFORE PRODUCTION
//
// Creates default test accounts.

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

$config = require __DIR__ . '/config/database.php';
$dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function create_user(PDO $pdo, string $username, string $email, string $password, int $roleId): int {
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, email, password_hash, role_id, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
    $stmt->execute([$username, $email, $hash, $roleId]);
    if ($pdo->lastInsertId()) return (int)$pdo->lastInsertId();
    return (int)$pdo->query("SELECT id FROM users WHERE username = " . $pdo->quote($username))->fetchColumn();
}

$activeSession = $pdo->query("SELECT id FROM academic_sessions WHERE is_active = 1")->fetchColumn();
$activeClass   = $pdo->query("SELECT id FROM classes WHERE level = 1 LIMIT 1")->fetchColumn(); // JSS1

echo "Using session ID: {$activeSession}, class ID: {$activeClass}\n";

// --- ROLES ---
// Get role IDs
$roles = [];
foreach ($pdo->query("SELECT id, name FROM roles") as $r) {
    $roles[$r['name']] = $r['id'];
}

// 1. Admin (may already exist)
$adminId = create_user($pdo, 'admin', 'admin@shoheed.edu', 'admin123', $roles[ROLE_ADMIN]);
echo "Admin user ID: {$adminId}\n";

// 2. Principal
$principalUserId = create_user($pdo, 'principal', 'principal@shoheed.edu', 'principal123', $roles[ROLE_PRINCIPAL]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name) VALUES (?, 'Amina', 'Adeyemi')");
$stmt->execute([$principalUserId]);
$principalStaffId = $pdo->query("SELECT id FROM staff WHERE user_id = {$principalUserId}")->fetchColumn();
echo "Principal user ID: {$principalUserId}, staff ID: {$principalStaffId}\n";

// 3. Class Teacher (also may teach a subject)
$ctUserId = create_user($pdo, 'classteacher', 'classteacher@shoheed.edu', 'teacher123', $roles[ROLE_CLASS_TEACHER]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name) VALUES (?, 'Biodun', 'Okafor')");
$stmt->execute([$ctUserId]);
$ctStaffId = $pdo->query("SELECT id FROM staff WHERE user_id = {$ctUserId}")->fetchColumn();

// Assign as Class Teacher for JSS1
if ($activeSession && $activeClass) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO class_teacher_assignments (staff_id, class_id, academic_session_id) VALUES (?, ?, ?)");
    $stmt->execute([$ctStaffId, $activeClass, $activeSession]);
}
echo "Class Teacher user ID: {$ctUserId}, staff ID: {$ctStaffId}\n";

// 4. Subject Teacher
$stUserId = create_user($pdo, 'subjectteacher', 'subjectteacher@shoheed.edu', 'teacher123', $roles[ROLE_SUBJECT_TEACHER]);
$stmt = $pdo->prepare("INSERT IGNORE INTO staff (user_id, first_name, last_name) VALUES (?, 'Emeka', 'Nwosu')");
$stmt->execute([$stUserId]);
$stStaffId = $pdo->query("SELECT id FROM staff WHERE user_id = {$stUserId}")->fetchColumn();

// Assign a subject (first subject) to this teacher for JSS1
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
$studentId = $pdo->query("SELECT id FROM students WHERE user_id = {$studentUserId}")->fetchColumn();

// Enroll student in JSS1
if ($activeSession && $activeClass) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO student_enrollments (student_id, academic_session_id, class_id, status) VALUES (?, ?, ?, 'ACTIVE')");
    $stmt->execute([$studentId, $activeSession, $activeClass]);
}
echo "Student user ID: {$studentUserId}, student ID: {$studentId}\n";

// 7. Parent/Guardian (Deprecated - Users no longer receive ROLE_PARENT)
$stmt = $pdo->prepare("INSERT IGNORE INTO guardians (user_id, first_name, last_name, phone) VALUES (NULL, 'Ngozi', 'Obiora', '08012345678')");
$stmt->execute();
$guardianId = $pdo->lastInsertId();
if (!$guardianId) {
    $guardianId = $pdo->query("SELECT id FROM guardians WHERE phone = '08012345678'")->fetchColumn();
}

// Link guardian to student
$stmt = $pdo->prepare("INSERT IGNORE INTO student_guardians (student_id, guardian_id, relationship) VALUES (?, ?, 'MOTHER')");
$stmt->execute([$studentId, $guardianId]);
echo "Guardian ID: {$guardianId}\n";

echo "\n=== SEEDING COMPLETE ===\n";
echo "Accounts created:\n";
echo "  admin / admin123          (Administrator)\n";
echo "  principal / principal123  (Principal)\n";
echo "  classteacher / teacher123 (Class Teacher — JSS1)\n";
echo "  subjectteacher / teacher123 (Subject Teacher — 1st subject, JSS1)\n";
echo "  bursar / bursar123        (Bursar)\n";
echo "  student01 / student123    (Student — enrolled JSS1)\n";
echo "  parent01 / parent123      (Parent — linked to student01)\n";
