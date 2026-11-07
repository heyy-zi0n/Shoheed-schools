<?php
require_once __DIR__ . '/../config/bootstrap.php';

$staffId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$staffId) {
    http_response_code(400);
    exit('Invalid staff ID.');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT profile_image, show_in_directory, status FROM staff WHERE id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff || empty($staff['profile_image'])) {
    http_response_code(404);
    exit('Image not found.');
}

$isPublic = ($staff['show_in_directory'] == 1 && $staff['status'] === 'ACTIVE');

// Authorization logic for private images
if (!$isPublic) {
    require_auth();
    
    $currentUserId = current_user_id();
    $currentUserRole = current_user_role();
    
    // Check if the current user owns this staff record
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE id = ? AND user_id = ?");
    $stmt->execute([$staffId, $currentUserId]);
    $isOwner = (bool)$stmt->fetch();
    
    // Check if the current user is an Admin
    $isAdmin = ($currentUserRole === ROLE_ADMIN);
    
    if (!$isOwner && !$isAdmin) {
        http_response_code(403);
        exit('Access denied.');
    }
}

// Serve the file (supports both storage/profiles/... and legacy storage/staff_profiles/...)
$relativePath = (string)$staff['profile_image'];
$normalised = ltrim(str_replace('\\', '/', $relativePath), '/');

$realPath = false;
$storageReal = false;

if (str_starts_with($normalised, 'storage/profiles/')) {
    $storageReal = realpath(__DIR__ . '/../storage/profiles/');
    $realPath = realpath(__DIR__ . '/../' . $normalised);
} elseif (str_starts_with($normalised, 'storage/staff_profiles/')) {
    $storageReal = realpath(__DIR__ . '/../storage/staff_profiles/');
    $realPath = realpath(__DIR__ . '/../' . $normalised);
} elseif (!str_contains($normalised, '/')) {
    // Check storage/staff_profiles/ first, then storage/profiles/
    $safeBase = basename($normalised);
    $candStaff = realpath(__DIR__ . '/../storage/staff_profiles/' . $safeBase);
    if ($candStaff && is_file($candStaff)) {
        $storageReal = realpath(__DIR__ . '/../storage/staff_profiles/');
        $realPath = $candStaff;
    } else {
        $storageReal = realpath(__DIR__ . '/../storage/profiles/');
        $realPath = realpath(__DIR__ . '/../storage/profiles/' . $safeBase);
    }
}

if (!$realPath || !$storageReal || !str_starts_with($realPath, $storageReal) || !is_file($realPath)) {
    http_response_code(404);
    exit('Image not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($realPath);
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(403);
    exit('Forbidden file type.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Content-Disposition: inline; filename="' . addslashes(basename($realPath)) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; sandbox");
header('Cache-Control: ' . ($isPublic ? 'public, max-age=86400' : 'private, max-age=3600'));
readfile($realPath);
exit;
