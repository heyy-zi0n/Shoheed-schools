<?php
function url(string $path = ''): string {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    $baseUrl = rtrim($config['url'], '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}
