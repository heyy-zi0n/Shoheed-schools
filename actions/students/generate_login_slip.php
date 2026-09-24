<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('admin/students/index.php');
}

$studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
if (!$studentId) {
    set_flash('error', 'Invalid student ID.');
    redirect('admin/students/index.php');
}

try {
    $pdo = get_db_connection();

    // Lock the student row for update
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT s.*,
               u.id as current_user_id, u.role_id, u.status as user_status, u.username as current_username,
               c.name as class_name, sess.name as session_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN student_enrollments se ON se.student_id = s.id AND se.status = 'ACTIVE'
        LEFT JOIN classes c ON c.id = se.class_id
        LEFT JOIN academic_sessions sess ON sess.id = se.academic_session_id
        WHERE s.id = ?
        FOR UPDATE
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        $pdo->rollBack();
        set_flash('error', 'Student not found.');
        redirect('admin/students/index.php');
    }

    $admissionNumber = $student['admission_number'];
    $pin = (string) random_int(100000, 999999);
    $passwordHash = password_hash($pin, PASSWORD_DEFAULT);

    $userId = $student['current_user_id'];
    $username = $admissionNumber;

    if ($userId === null) {
        // Create account
        // Check global username collision
        $userCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :uname");
        $userCheckStmt->execute(['uname' => $admissionNumber]);
        if ($userCheckStmt->fetchColumn() > 0) {
            $pdo->rollBack();
            set_flash('error', 'Cannot create portal account: Admission number is already in use as a portal username.');
            redirect('admin/students/view.php?id=' . $studentId);
        }

        $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = :rname");
        $roleStmt->execute(['rname' => ROLE_STUDENT]);
        $roleId = $roleStmt->fetchColumn();

        if (!$roleId) {
            $pdo->rollBack();
            throw new Exception("Student role not found in database.");
        }

        $userStmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, status) VALUES (:uname, :pwd, :rid, 'ACTIVE')");
        $userStmt->execute([
            'uname' => $admissionNumber,
            'pwd' => $passwordHash,
            'rid' => $roleId
        ]);
        $userId = $pdo->lastInsertId();

        $pdo->prepare("UPDATE students SET user_id = ? WHERE id = ?")->execute([$userId, $studentId]);

        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
        $auditStmt->execute([
            'user_id' => current_user_id(),
            'action' => 'STUDENT_PORTAL_ACCOUNT_CREATED',
            'type' => 'user',
            'eid' => $userId,
            'desc' => "Admin manually created portal account for student ID {$studentId}."
        ]);

    } else {
        // Regenerate slip / reset password
        $username = $student['current_username'];

        $roleStmt = $pdo->prepare("SELECT name FROM roles WHERE id = ?");
        $roleStmt->execute([$student['role_id']]);
        $roleName = $roleStmt->fetchColumn();

        if ($roleName !== ROLE_STUDENT) {
            $pdo->rollBack();
            set_flash('error', 'Linked user is not a Student role.');
            redirect('admin/students/view.php?id=' . $studentId);
        }

        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$passwordHash, $userId]);

        $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (:user_id, :action, :type, :eid, :desc)");
        $auditStmt->execute([
            'user_id' => current_user_id(),
            'action' => 'STUDENT_LOGIN_SLIP_REGENERATED',
            'type' => 'user',
            'eid' => $userId,
            'desc' => "Admin generated new login slip (reset PIN) for student ID {$studentId}."
        ]);
    }

    $pdo->commit();

    // Store in short-lived session
    $_SESSION['student_login_slip'] = [
        'student_id' => $student['id'],
        'student_name' => trim("{$student['first_name']} {$student['middle_name']} {$student['last_name']}"),
        'admission_number' => $username, // Use actual username for the slip just in case it's legacy
        'pin' => $pin,
        'class_name' => $student['class_name'],
        'session_name' => $student['session_name']
    ];

    redirect('admin/students/login_slip.php');

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', 'Failed to generate login slip. ' . $e->getMessage());
    redirect('admin/students/view.php?id=' . $studentId);
}
