<?php
require_once __DIR__ . '/../config/bootstrap.php';
$pdo = get_db_connection();
$sql = file_get_contents(__DIR__ . '/../database/migrations/010_public_staff_directory.sql');
try {
    $pdo->exec($sql);
    echo "Migration successful.\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
