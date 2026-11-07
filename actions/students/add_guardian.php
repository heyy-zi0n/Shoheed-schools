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
$lastName = trim((string)($_POST['last_name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$relationship = trim((string)($_POST['relationship'] ?? 'Parent/Guardian'));
$address = trim((string)($_POST['address'] ?? ''));

if ($firstName === '' || $lastName === '' || $phone === '') {
    set_flash('error', 'Guardian first name, last name, and phone number are required.');
    redirect($returnUrl);
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    $checkStu = $pdo->prepare("SELECT id, admission_number FROM students WHERE id = ?");
    $checkStu->execute([$studentId]);
    $stu = $checkStu->fetch();
    if (!$stu) {
        throw new Exception('Student not found.');
    }

    // Reuse existing guardian with same phone + name if present, else create
    $findG = $pdo->prepare("SELECT id FROM guardians WHERE phone = ? AND first_name = ? AND last_name = ? LIMIT 1");
    $findG->execute([$phone, $firstName, $lastName]);
    $guardianId = (int)$findG->fetchColumn();

    if (!$guardianId) {
        $insG = $pdo->prepare("INSERT INTO guardians (first_name, last_name, phone, address) VALUES (?, ?, ?, ?)");
        $insG->execute([$firstName, $lastName, $phone, $address !== '' ? $address : null]);
        $guardianId = (int)$pdo->lastInsertId();
    }

    $linkStmt = $pdo->prepare("
        INSERT INTO student_guardians (student_id, guardian_id, relationship)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE relationship = VALUES(relationship)
    ");
    $linkStmt->execute([$studentId, $guardianId, $relationship !== '' ? $relationship : 'Parent/Guardian']);

    log_audit(
        $pdo,
        current_user_id(),
        'LINK_STUDENT_GUARDIAN',
        'student',
        $studentId,
        "Linked guardian {$firstName} {$lastName} ({$phone}) to student {$stu['admission_number']}."
    );

    $pdo->commit();
    set_flash('success', 'Guardian linked to student successfully.');
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error linking guardian: ' . $e->getMessage());
    set_flash('error', 'Unable to save guardian details.');
}

redirect($returnUrl);
