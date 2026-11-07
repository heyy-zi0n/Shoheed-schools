<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
$returnUrl = $studentId ? "admin/students/view.php?id={$studentId}" : 'admin/students/index.php';

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect($returnUrl);
}

if (!$studentId) {
    set_flash('error', 'Invalid student ID.');
    redirect('admin/students/index.php');
}

$firstName = trim((string)($_POST['first_name'] ?? ''));
$middleName = trim((string)($_POST['middle_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$dob = trim((string)($_POST['date_of_birth'] ?? ''));
$status = strtoupper(trim((string)($_POST['status'] ?? 'ACTIVE')));

if ($firstName === '' || $lastName === '') {
    set_flash('error', 'First name and last name are required.');
    redirect($returnUrl);
}

if (!in_array($gender, ['Male', 'Female'], true)) {
    set_flash('error', 'Please select a valid gender.');
    redirect($returnUrl);
}

if (!in_array($status, ['ACTIVE', 'INACTIVE', 'GRADUATED', 'WITHDRAWN'], true)) {
    set_flash('error', 'Invalid student status.');
    redirect($returnUrl);
}

$dobVal = null;
if ($dob !== '') {
    $parsedDob = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$parsedDob || $parsedDob->format('Y-m-d') !== $dob || $dob > date('Y-m-d')) {
        set_flash('error', 'Please enter a valid date of birth.');
        redirect($returnUrl);
    }
    $dobVal = $dob;
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $stuStmt = $pdo->prepare("SELECT id, user_id, admission_number FROM students WHERE id = ? FOR UPDATE");
    $stuStmt->execute([$studentId]);
    $student = $stuStmt->fetch();

    if (!$student) {
        throw new Exception('Student record not found.');
    }

    $upd = $pdo->prepare("
        UPDATE students
        SET first_name = ?, middle_name = ?, last_name = ?, gender = ?, date_of_birth = ?, status = ?
        WHERE id = ?
    ");
    $upd->execute([
        $firstName,
        $middleName !== '' ? $middleName : null,
        $lastName,
        $gender,
        $dobVal,
        $status,
        $studentId,
    ]);

    // Sync linked portal account status if present
    if (!empty($student['user_id'])) {
        $portalStatus = ($status === 'ACTIVE') ? 'ACTIVE' : 'INACTIVE';
        $uStmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $uStmt->execute([$portalStatus, $student['user_id']]);
    }

    log_audit(
        $pdo,
        current_user_id(),
        'UPDATE_STUDENT',
        'student',
        $studentId,
        "Updated student {$student['admission_number']} ({$firstName} {$lastName}), status={$status}."
    );

    $pdo->commit();
    set_flash('success', 'Student profile updated successfully.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error updating student: ' . $e->getMessage());
    set_flash('error', $e->getMessage());
}

redirect($returnUrl);
