<?php
function url(string $path = ''): string {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    $baseUrl = rtrim($config['url'], '/');
    $cleanPath = ltrim(str_replace('\\', '/', $path), '/');
    return $baseUrl . '/' . $cleanPath;
}

function asset(string $path = ''): string {
    return url($path);
}

/**
 * Validate and normalize an internal relative redirect path.
 * Rejects external URLs, protocol-relative URLs, backslashes, or directory traversal.
 */
function sanitize_internal_redirect_path(string $path, string $default = ''): string {
    $path = trim($path);
    if ($path === '' || preg_match('/[\r\n\0]/', $path)) {
        return $default;
    }
    if (
        str_contains($path, '://') ||
        str_starts_with($path, '//') ||
        str_contains($path, '\\') ||
        preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $path)
    ) {
        return $default;
    }
    $clean = ltrim($path, '/');
    if ($clean === '' || str_starts_with($clean, '/') || str_contains($clean, '..')) {
        return $default;
    }
    return $clean;
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}
