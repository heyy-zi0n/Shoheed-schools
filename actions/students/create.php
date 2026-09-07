<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/students/create.php');
}

$admissionNumber = trim($_POST['admission_number'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$gender = trim($_POST['gender'] ?? '');
$admissionDate = trim($_POST['admission_date'] ?? '');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');
$sessionId = filter_var($_POST['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);

$errors = validate_required([
    'admission_number' => $admissionNumber,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'gender' => $gender,
    'admission_date' => $admissionDate,
    'academic_session_id' => $sessionId,
    'class_id' => $classId
], ['admission_number', 'first_name', 'last_name', 'gender', 'admission_date', 'academic_session_id', 'class_id']);

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('admin/students/create.php');
}

if (!in_array($gender, ['Male', 'Female'], true)) {
    set_flash('error', 'Invalid gender selected.');
    redirect('admin/students/create.php');
}

try {
    $pdo = get_db_connection();
    
    // Check admission number uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE admission_number = :adm");
    $stmt->execute(['adm' => $admissionNumber]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('error', 'Admission number is already in use.');
        redirect('admin/students/create.php');
    }
    
    $pdo->beginTransaction();
    
    // 1. Create Student
    $stmt = $pdo->prepare("INSERT INTO students (admission_number, first_name, middle_name, last_name, gender, date_of_birth, admission_date) VALUES (:adm, :first, :middle, :last, :gender, :dob, :adate)");
    $stmt->execute([
        'adm' => $admissionNumber,
        'first' => $firstName,
        'middle' => $middleName !== '' ? $middleName : null,
        'last' => $lastName,
        'gender' => $gender,
        'dob' => $dateOfBirth !== '' ? $dateOfBirth : null,
        'adate' => $admissionDate
    ]);
    $studentId = $pdo->lastInsertId();
    
    // 2. Create Enrollment
    $enrollStmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, academic_session_id, class_id) VALUES (:stu, :sess, :cls)");
    $enrollStmt->execute([
        'stu' => $studentId,
        'sess' => $sessionId,
        'cls' => $classId
    ]);
    
    // 3. Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
    $auditStmt->execute([
        'user_id' => current_user_id(),
        'action' => 'CREATE_STUDENT',
        'type' => 'student',
        'eid' => $studentId,
        'desc' => "Created and enrolled student {$firstName} {$lastName} ({$admissionNumber}) in class ID {$classId}."
    ]);

    $pdo->commit();
    set_flash('success', 'Student created and enrolled successfully.');
    redirect('admin/students/index.php');
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to create student. ' . $e->getMessage());
    redirect('admin/students/create.php');
}
