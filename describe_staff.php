<?php
require_once __DIR__ . '/config/database.php';
$config = require __DIR__ . '/config/database.php';
$dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset={$config['charset']}";
$pdo = new PDO($dsn, $config['user'], $config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['guardians','student_guardians','students'] as $tbl) {
    $r = $pdo->query("DESCRIBE $tbl");
    echo "=== $tbl ===\n";
    while ($row = $r->fetch()) {
        echo "  {$row['Field']} — {$row['Type']}\n";
    }
}
