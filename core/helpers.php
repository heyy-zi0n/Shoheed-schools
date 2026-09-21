<?php
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get all academic sessions ordered by start date (newest first).
 */
function get_all_academic_sessions(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
}

/**
 * Get the currently active academic session.
 * Returns null if no session is active.
 */
function get_active_academic_session(): ?array {
    $pdo = get_db_connection();
    $session = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1 LIMIT 1")->fetch();
    return $session ?: null;
}

/**
 * Get an academic session by its ID.
 */
function get_academic_session_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM academic_sessions WHERE id = ?");
    $stmt->execute([$id]);
    $session = $stmt->fetch();
    return $session ?: null;
}

