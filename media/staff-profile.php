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

// Serve the file
$relativePath = $staff['profile_image'];
// Ensure path starts with storage/profiles/
$normalised = str_replace('\\', '/', $relativePath);
if (!str_starts_with($normalised, 'storage/profiles/')) {
    http_response_code(403);
    exit('Access denied.');
}

$absolutePath = __DIR__ . '/../' . $normalised;
$realPath = realpath($absolutePath);
$storageReal = realpath(__DIR__ . '/../storage/profiles/');

if (!$realPath || !$storageReal || !str_starts_with($realPath, $storageReal) || !file_exists($realPath)) {
    http_response_code(404);
    exit('Image not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($realPath);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: max-age=86400, public');
readfile($realPath);
exit;
