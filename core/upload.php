<?php
/**
 * Shared upload helper for profile images (students and staff) and payment proofs.
 * Usage: require_once __DIR__ . '/../core/upload.php';
 */

define('UPLOAD_MAX_SIZE_BYTES', 2 * 1024 * 1024); // 2 MB
define('UPLOAD_PROOF_MAX_SIZE_BYTES', 5 * 1024 * 1024); // 5 MB
define('UPLOAD_ALLOWED_MIMES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('UPLOAD_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('UPLOAD_STORAGE_DIR', __DIR__ . '/../storage/profiles/');
define('UPLOAD_STORAGE_PATH', 'storage/profiles/');
define('UPLOAD_PROOF_STORAGE_DIR', __DIR__ . '/../storage/payment_proofs/');

/**
 * Translate PHP upload error code to a safe user-facing message.
 */
function upload_error_message(int $errorCode): string {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server maximum size.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form maximum size.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
    ];
    return $messages[$errorCode] ?? 'Unknown upload error.';
}

/**
 * Reject filenames with null bytes, path traversal sequences, or executable double extensions.
 */
function assert_safe_original_filename(string $originalName): void {
    if ($originalName === '' || str_contains($originalName, "\0") || str_contains($originalName, '..')) {
        throw new RuntimeException('Invalid file name.');
    }
    if (preg_match('/\.(php[0-9]?|phtml|phar|inc|cgi|pl|py|sh|asp|aspx|jsp|htaccess|htpasswd)(\.|$)/i', $originalName)) {
        throw new RuntimeException('Executable or script file names are not permitted.');
    }
}

/**
 * Handle a profile image upload.
 *
 * @param array $file   The $_FILES entry (e.g. $_FILES['profile_image'])
 * @return string       Relative path to store in DB (e.g. 'storage/profiles/abc123.jpg')
 * @throws RuntimeException on any validation or IO failure
 */
function handle_profile_upload(array $file): string {
    // 1. Check for upload errors
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }

    // 2. File size check
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Uploaded file is empty.');
    }
    if ($size > UPLOAD_MAX_SIZE_BYTES) {
        throw new RuntimeException('File size exceeds the 2 MB maximum allowed.');
    }

    // 3. Validate original filename & extension allowlist
    $originalName = (string)($file['name'] ?? '');
    assert_safe_original_filename($originalName);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException("File extension '{$ext}' is not permitted. Allowed: jpg, jpeg, png, gif, webp.");
    }

    // 4. MIME type check using finfo on actual file contents (never trust $_FILES['type'])
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Invalid temporary upload file.');
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    if (!isset($mimeMap[$mime])) {
        throw new RuntimeException('Invalid file content. Only JPEG, PNG, GIF, and WebP images are accepted.');
    }

    // 5. Verify image header/dimensions
    $imgInfo = @getimagesize($tmpName);
    if ($imgInfo === false || ($imgInfo[0] ?? 0) <= 0 || ($imgInfo[1] ?? 0) <= 0) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    // 6. Ensure storage directory exists and is writable
    if (!is_dir(UPLOAD_STORAGE_DIR)) {
        if (!mkdir(UPLOAD_STORAGE_DIR, 0755, true)) {
            throw new RuntimeException('Could not create storage directory.');
        }
    }
    if (!is_writable(UPLOAD_STORAGE_DIR)) {
        throw new RuntimeException('Storage directory is not writable.');
    }

    // 7. Generate unique server-side filename using canonical MIME extension
    $safeExt = $mimeMap[$mime];
    $uniqueName = bin2hex(random_bytes(16)) . '.' . $safeExt;
    $destination = UPLOAD_STORAGE_DIR . $uniqueName;

    // 8. Move uploaded file
    if (!move_uploaded_file($tmpName, $destination)) {
        throw new RuntimeException('Failed to store uploaded file.');
    }

    return UPLOAD_STORAGE_PATH . $uniqueName;
}

/**
 * Handle a payment proof upload (JPEG, PNG, or PDF up to 5 MB).
 *
 * @param array $file   The $_FILES['proof'] entry
 * @return string       Generated safe basename (e.g. 'proof_a1b2c3...pdf') stored in storage/payment_proofs/
 * @throws RuntimeException on any validation or IO failure
 */
function handle_proof_upload(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message((int)($file['error'] ?? UPLOAD_ERR_NO_FILE)));
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Uploaded proof file is empty.');
    }
    if ($size > UPLOAD_PROOF_MAX_SIZE_BYTES) {
        throw new RuntimeException('File size exceeds 5MB limit.');
    }

    $originalName = (string)($file['name'] ?? '');
    assert_safe_original_filename($originalName);
    $clientExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($clientExt, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
        throw new RuntimeException('Invalid file extension. Only JPG, PNG, and PDF files are allowed.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_file($tmpName)) {
        throw new RuntimeException('Invalid temporary upload file.');
    }

    $mimeMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'application/pdf' => 'pdf',
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    if (!isset($mimeMap[$mime])) {
        throw new RuntimeException('Invalid file type. Only JPG, PNG, and PDF are allowed.');
    }

    if ($mime === 'application/pdf') {
        $fh = @fopen($tmpName, 'rb');
        $header = $fh ? fread($fh, 5) : '';
        if ($fh) {
            fclose($fh);
        }
        if ($header !== '%PDF-') {
            throw new RuntimeException('The uploaded PDF file is corrupted or invalid.');
        }
    } else {
        $imgInfo = @getimagesize($tmpName);
        if ($imgInfo === false || ($imgInfo[0] ?? 0) <= 0 || ($imgInfo[1] ?? 0) <= 0) {
            throw new RuntimeException('The uploaded image file is corrupted or invalid.');
        }
    }

    if (!is_dir(UPLOAD_PROOF_STORAGE_DIR)) {
        if (!mkdir(UPLOAD_PROOF_STORAGE_DIR, 0755, true)) {
            throw new RuntimeException('Could not create payment proof storage directory.');
        }
    }
    if (!is_writable(UPLOAD_PROOF_STORAGE_DIR)) {
        throw new RuntimeException('Payment proof storage directory is not writable.');
    }

    $safeExt = $mimeMap[$mime];
    $filename = 'proof_' . bin2hex(random_bytes(16)) . '.' . $safeExt;
    $destPath = UPLOAD_PROOF_STORAGE_DIR . $filename;

    if (!move_uploaded_file($tmpName, $destPath)) {
        throw new RuntimeException('Failed to save uploaded file.');
    }

    return $filename;
}

/**
 * Delete a payment proof file safely (prevents path traversal).
 */
function delete_proof_file(string $filename): void {
    $basename = basename($filename);
    if ($basename === '' || $basename !== $filename) {
        return;
    }
    $target = UPLOAD_PROOF_STORAGE_DIR . $basename;
    $real = realpath($target);
    $storageReal = realpath(UPLOAD_PROOF_STORAGE_DIR);
    if ($real && $storageReal && str_starts_with($real, $storageReal)) {
        @unlink($real);
    }
}

/**
 * Delete an old profile image safely (prevents path traversal).
 */
function delete_profile_image(string $relativePath): void {
    if (empty($relativePath)) return;
    $normalised = ltrim(str_replace('\\', '/', $relativePath), '/');

    if (str_starts_with($normalised, 'storage/profiles/')) {
        $allowedRoot = realpath(__DIR__ . '/../storage/profiles/');
        $candidate = __DIR__ . '/../' . $normalised;
    } elseif (str_starts_with($normalised, 'storage/staff_profiles/')) {
        $allowedRoot = realpath(__DIR__ . '/../storage/staff_profiles/');
        $candidate = __DIR__ . '/../' . $normalised;
    } elseif (!str_contains($normalised, '/')) {
        // Legacy bare filename in storage/staff_profiles/
        $allowedRoot = realpath(__DIR__ . '/../storage/staff_profiles/');
        $candidate = __DIR__ . '/../storage/staff_profiles/' . basename($normalised);
    } else {
        return;
    }

    $real = realpath($candidate);
    if ($real && $allowedRoot && str_starts_with($real, $allowedRoot)) {
        @unlink($real);
    }
}
