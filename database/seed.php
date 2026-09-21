<?php
require_once __DIR__ . '/../config/database.php';

try {
    $config = require __DIR__ . '/../config/database.php';
    $dsn_no_db = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    $pdo = new PDO($dsn_no_db, $config['user'], $config['pass'], $options);

    // DEVELOPMENT / TEST ACCOUNT ONLY
    // CHANGE BEFORE PRODUCTION
    // 
    // Creates default roles and a test admin user
    echo "Creating database if not exists...\n";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$config['name']}`");

    echo "Importing schema...\n";
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schema);

    echo "Seeding roles...\n";
    $roles = [
        'ADMIN', 'PRINCIPAL', 'CLASS_TEACHER', 'SUBJECT_TEACHER', 'BURSAR', 'STUDENT', 'PARENT'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (name) VALUES (:name)");
    foreach ($roles as $role) {
        $stmt->execute(['name' => $role]);
    }

    echo "Seeding Admin user...\n";
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'ADMIN'");
    $stmt->execute();
    $adminRoleId = $stmt->fetchColumn();

    $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (role_id, username, email, password_hash) VALUES (:role, :user, :email, :pass)");
    $stmt->execute([
        'role' => $adminRoleId,
        'user' => 'admin',
        'email' => 'admin@shoheedschools.com',
        'pass' => $adminPass
    ]);

    echo "Seeding Academic Sessions...\n";
    $stmt = $pdo->prepare("INSERT IGNORE INTO academic_sessions (name, is_active, start_date, end_date) VALUES (:name, :active, :start, :end)");
    $stmt->execute(['name' => '2025/2026', 'active' => 0, 'start' => '2025-09-01', 'end' => '2026-07-31']);
    $stmt->execute(['name' => '2026/2027', 'active' => 1, 'start' => '2026-09-01', 'end' => '2027-07-31']);
    $stmt->execute(['name' => '2027/2028', 'active' => 0, 'start' => '2027-09-01', 'end' => '2028-07-31']);
    
    // Ensure only one is active at a time during seeding if re-run
    $pdo->exec("UPDATE academic_sessions SET is_active = 0 WHERE name != '2026/2027'");
    $pdo->exec("UPDATE academic_sessions SET is_active = 1 WHERE name = '2026/2027'");

    $stmt = $pdo->prepare("SELECT id FROM academic_sessions WHERE name = '2026/2027'");
    $stmt->execute();
    $sessionId = $stmt->fetchColumn();

    echo "Seeding Terms...\n";
    $terms = ['First Term', 'Second Term', 'Third Term'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO terms (name, academic_session_id, is_active) VALUES (:name, :session_id, :is_active)");
    foreach ($terms as $index => $term) {
        $stmt->execute([
            'name' => $term,
            'session_id' => $sessionId,
            'is_active' => ($index === 0 ? 1 : 0) // First term active
        ]);
    }

    echo "Seeding Classes...\n";
    $classes = [
        ['JSS1', 1],
        ['JSS2', 2],
        ['JSS3', 3],
        ['SSS1', 4],
        ['SSS2', 5],
        ['SSS3', 6]
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO classes (name, level) VALUES (:name, :level)");
    foreach ($classes as $class) {
        $stmt->execute(['name' => $class[0], 'level' => $class[1]]);
    }

    echo "Seeding Subjects...\n";
    $subjects = [
        'Mathematics' => 'MTH',
        'English Language' => 'ENG',
        'Basic Science' => 'BSC',
        'Basic Technology' => 'BTE',
        'Civic Education' => 'CIV',
        'Biology' => 'BIO',
        'Chemistry' => 'CHM',
        'Physics' => 'PHY',
        'Economics' => 'ECO'
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO subjects (name, code) VALUES (:name, :code)");
    foreach ($subjects as $name => $code) {
        $stmt->execute(['name' => $name, 'code' => $code]);
    }

    echo "Seeding completed successfully.\n";
    echo "Default Admin Login:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage() . "\n");
}
