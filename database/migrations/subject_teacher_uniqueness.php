<?php
require_once 'c:\xampp\htdocs\Shoheed schools\config\bootstrap.php';
$pdo = get_db_connection();

echo "Running Migration...\n";

try {
    // Determine the name of the existing unique key
    $stmt = $pdo->query("SHOW INDEX FROM subject_teacher_assignments");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $oldKeyName = '';
    foreach ($indexes as $idx) {
        // If it's a unique index with 4 columns
        if ($idx['Non_unique'] == 0 && $idx['Key_name'] != 'PRIMARY' && $idx['Key_name'] != 'uq_subject_class_session') {
            $oldKeyName = $idx['Key_name'];
            break;
        }
    }
    
    if ($oldKeyName) {
        $pdo->exec("ALTER TABLE subject_teacher_assignments ADD INDEX idx_staff_id (staff_id)");
        echo "Added index idx_staff_id to support foreign key.\n";
        
        $pdo->exec("ALTER TABLE subject_teacher_assignments DROP INDEX `$oldKeyName`");
        echo "Dropped old redundant index: $oldKeyName\n";
    }

    $pdo->exec("ALTER TABLE subject_teacher_assignments ADD UNIQUE KEY uq_subject_class_session (class_id, subject_id, academic_session_id)");
    echo "Added new constraint: uq_subject_class_session\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
