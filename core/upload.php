<?php
/**
 * Shared upload helper for profile images (students and staff).
 * Usage: require_once __DIR__ . '/../core/upload.php';
 * Returns: ['path' => 'storage/profiles/filename.jpg'] or throws RuntimeException.
 */

define('UPLOAD_MAX_SIZE_BYTES', 2 * 1024 * 1024); // 2 MB
define('UPLOAD_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_STORAGE_DIR', __DIR__ . '/../storage/profiles/');
define('UPLOAD_STORAGE_PATH', 'storage/profiles/');

/**
 * Handle a profile image upload.
 *
 * @param array $file   The $_FILES entry (e.g. $_FILES['profile_image'])
 * @return string       Relative path to store in DB (e.g. 'storage/profiles/abc123.jpg')
 * @throws RuntimeException on any validation or IO failure
 */
function handle_profile_upload(array $file): string {
    // 1. Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server maximum size.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form maximum size.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        throw new RuntimeException($messages[$file['error']] ?? 'Unknown upload error.');
    }

    // 2. File size
    if ($file['size'] > UPLOAD_MAX_SIZE_BYTES) {
        throw new RuntimeException('File size exceeds the 2 MB maximum allowed.');
    }

    // 3. Extension check (never trust original name; reject executable-looking names)
    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException("File extension '{$ext}' is not permitted. Allowed: jpg, jpeg, png, gif, webp.");
    }

    // 4. MIME type check using finfo on the actual temp file (not the browser-claimed type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_ALLOWED_MIMES, true)) {
        throw new RuntimeException("File MIME type '{$mime}' is not permitted. Only images are accepted.");
    }

    // 5. Ensure storage directory exists and is writable
    if (!is_dir(UPLOAD_STORAGE_DIR)) {
        if (!mkdir(UPLOAD_STORAGE_DIR, 0755, true)) {
            throw new RuntimeException('Could not create storage directory.');
        }
    }
    if (!is_writable(UPLOAD_STORAGE_DIR)) {
        throw new RuntimeException('Storage directory is not writable.');
    }

    // 6. Generate unique server-side filename — never trust original filename
    $uniqueName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = UPLOAD_STORAGE_DIR . $uniqueName;

    // 7. Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }

    // 8. Return relative path for DB storage
    return UPLOAD_STORAGE_PATH . $uniqueName;
}

/**
 * Delete an old profile image safely (prevents path traversal).
 */
function delete_profile_image(string $relativePath): void {
    if (empty($relativePath)) return;
    // Ensure path is under storage/profiles/ — reject anything else
    $normalised = str_replace('\\', '/', $relativePath);
    if (!str_starts_with($normalised, 'storage/profiles/')) return;
    $absolute = __DIR__ . '/../' . $normalised;
    $real = realpath($absolute);
    $storageReal = realpath(UPLOAD_STORAGE_DIR);
    if ($real && $storageReal && str_starts_with($real, $storageReal)) {
        @unlink($real);
    }
}
