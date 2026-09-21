<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_role([ROLE_PRINCIPAL]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    set_flash('error', 'Invalid CSRF token.');
    redirect('principal/assignments/classes.php');
}

$classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
$staffId = filter_var($_POST['staff_id'] ?? null, FILTER_VALIDATE_INT);

$activeSession = get_active_academic_session();
if (!$activeSession) {
    set_flash('error', 'No active academic session.');
    redirect('principal/assignments/classes.php');
}

$pdo = get_db_connection();

try {
    $pdo->beginTransaction();

    if ($staffId) {
        // Pre-save validation: Check if staff is already a class teacher in the same session for a DIFFERENT class
        $checkStmt = $pdo->prepare("
            SELECT c.name as class_name 
            FROM class_teacher_assignments cta
            JOIN classes c ON c.id = cta.class_id
            WHERE cta.staff_id = ? AND cta.academic_session_id = ? AND cta.class_id != ?
            LIMIT 1
        ");
        $checkStmt->execute([$staffId, $activeSession['id'], $classId]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $staffInfoStmt = $pdo->prepare("SELECT first_name, last_name FROM staff WHERE id = ?");
            $staffInfoStmt->execute([$staffId]);
            $staffInfo = $staffInfoStmt->fetch();
            $teacherName = $staffInfo ? "{$staffInfo['first_name']} {$staffInfo['last_name']}" : "This teacher";
            
            throw new Exception("{$teacherName} is already assigned as Class Teacher to {$existing['class_name']} for the {$activeSession['name']} academic session.");
        }

        // Assign or update
        $stmt = $pdo->prepare("
            INSERT INTO class_teacher_assignments (class_id, staff_id, academic_session_id) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE staff_id = VALUES(staff_id)
        ");
        $stmt->execute([$classId, $staffId, $activeSession['id']]);
        
        $actionDesc = "Assigned staff ID {$staffId} to class ID {$classId}";
    } else {
        // Remove assignment
        $stmt = $pdo->prepare("
            DELETE FROM class_teacher_assignments 
            WHERE class_id = ? AND academic_session_id = ?
        ");
        $stmt->execute([$classId, $activeSession['id']]);
        
        $actionDesc = "Removed class teacher assignment for class ID {$classId}";
    }

    // Audit Log
    $auditStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description) VALUES (?, ?, ?, ?, ?)");
    $auditStmt->execute([
        current_user_id(),
        'UPDATE_CLASS_TEACHER',
        'class',
        $classId,
        $actionDesc
    ]);

    $pdo->commit();
    set_flash('success', 'Class teacher assignment updated.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', $e->getMessage());
}

redirect('principal/assignments/classes.php');
