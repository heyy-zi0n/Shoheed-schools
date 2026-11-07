<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Invalid ID');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT student_id, proof_path FROM payment_submissions WHERE id = ?");
$stmt->execute([$id]);
$submission = $stmt->fetch();

if (!$submission) {
    http_response_code(404);
    exit('Not Found');
}

$userId = current_user_id();
$role = $_SESSION['role_name'] ?? '';

// Allow Admin, Bursar, or the Student who owns it
$allowed = false;
if (in_array($role, [ROLE_ADMIN, ROLE_BURSAR], true)) {
    $allowed = true;
} elseif ($role === ROLE_STUDENT) {
    // Check if the student user owns this submission
    $sStmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $sStmt->execute([$userId]);
    $studentId = $sStmt->fetchColumn();
    if ($studentId && (int)$studentId === (int)$submission['student_id']) {
        $allowed = true;
    }
}

if (!$allowed) {
    http_response_code(403);
    exit('Forbidden');
}

$proofFilename = basename((string)$submission['proof_path']);
if ($proofFilename === '' || $proofFilename !== (string)$submission['proof_path']) {
    http_response_code(400);
    exit('Invalid proof path');
}

$storageDir = dirname(__DIR__, 2) . '/storage/payment_proofs';
$storageReal = realpath($storageDir);
$realPath = realpath($storageDir . '/' . $proofFilename);

if (!$realPath || !$storageReal || !str_starts_with($realPath, $storageReal) || !is_file($realPath)) {
    http_response_code(404);
    exit('File not found on disk');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($realPath);
$allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(403);
    exit('Forbidden file type');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: inline; filename="' . addslashes($proofFilename) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox");
header('Cache-Control: private, no-store, max-age=0');
readfile($realPath);
exit;
