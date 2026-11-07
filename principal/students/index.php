<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_PRINCIPAL]);

$pageTitle = 'Student Directory';
$pdo = get_db_connection();

$search = trim((string)($_GET['q'] ?? ''));
$filterClassId = filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$filterStatus = trim((string)($_GET['status'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT) ?: 1;

$classes = $pdo->query("SELECT id, name FROM classes ORDER BY level ASC")->fetchAll();
$directoryResult = query_student_directory($pdo, $search, $filterClassId, $filterStatus, $page, 50);
$directoryActionUrl = url('principal/students/index.php');
$studentViewBaseUrl = 'principal/students/view.php';

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-6">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Student Directory</h1>
                <p class="text-sm text-slate-gray">Search and view student records and class enrollments.</p>
            </div>
            
            <?php require __DIR__ . '/../../templates/components/student_directory_table.php'; ?>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
