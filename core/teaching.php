<?php

/**
 * Get the current user's staff ID, or null if they are not staff.
 */
function current_staff_id() {
    if (!is_logged_in()) return null;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([current_user_id()]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Check if a staff member has ANY subject assignments in the given session.
 */
function staff_has_subject_assignments($staffId, $sessionId) {
    if (!$staffId) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subject_teacher_assignments WHERE staff_id = ? AND academic_session_id = ?");
    $stmt->execute([$staffId, $sessionId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if a staff member has ANY class teacher assignments in the given session.
 */
function staff_has_class_teacher_assignment($staffId, $sessionId) {
    if (!$staffId) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_teacher_assignments WHERE staff_id = ? AND academic_session_id = ?");
    $stmt->execute([$staffId, $sessionId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Check if a staff member is assigned to a specific subject in a specific class and session.
 */
function staff_can_access_subject_assignment($staffId, $classId, $subjectId, $sessionId) {
    if (!$staffId) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM subject_teacher_assignments WHERE staff_id = ? AND class_id = ? AND subject_id = ? AND academic_session_id = ?");
    $stmt->execute([$staffId, $classId, $subjectId, $sessionId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Check if a staff member is the class teacher for a specific class and session.
 */
function staff_can_access_class_teacher_function($staffId, $classId, $sessionId) {
    if (!$staffId) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM class_teacher_assignments WHERE staff_id = ? AND class_id = ? AND academic_session_id = ?");
    $stmt->execute([$staffId, $classId, $sessionId]);
    return (bool)$stmt->fetchColumn();
}
