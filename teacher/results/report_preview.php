<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
$studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

if (!$batchId || !$studentId) {
    set_flash('error', 'Invalid preview parameters.');
    redirect('');
}

$pdo = get_db_connection();
$bStmt = $pdo->prepare("SELECT * FROM result_batches WHERE id = ?");
$bStmt->execute([$batchId]);
$batch = $bStmt->fetch();

if (!$batch) {
    set_flash('error', 'Result batch not found.');
    redirect('');
}

$role = current_user_role();
$staffId = current_staff_id();
$canView = ($role === ROLE_PRINCIPAL || $role === ROLE_ADMIN);
if (!$canView && $staffId) {
    $canView = staff_can_access_class_teacher_function($staffId, (int)$batch['class_id'], (int)$batch['academic_session_id']);
}

if (!$canView) {
    http_response_code(403);
    exit('Access Denied.');
}

$report = build_student_report_card_data(
    $pdo,
    $studentId,
    (int)$batch['academic_session_id'],
    (int)$batch['term_id'],
    (int)$batch['class_id'],
    false
);

if (!$report) {
    set_flash('error', 'Report card data not found for this student.');
    redirect('');
}

$backUrl = ($role === ROLE_PRINCIPAL || $role === ROLE_ADMIN)
    ? url("principal/results/review.php?batch_id={$batchId}")
    : url("teacher/results/class_verification.php?class={$batch['class_id']}");

$pageTitle = 'Report Card Preview';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden print-layout-root">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden print-layout-content">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="max-w-4xl mx-auto mb-6 flex flex-wrap justify-between items-center gap-4 no-print">
                <a href="<?= e($backUrl) ?>" class="text-sm text-school-blue hover:underline font-medium">
                    &larr; Back to Result Review
                </a>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-200 text-charcoal">
                        Batch Status: <?= e(str_replace('_', ' ', $batch['status'])) ?>
                    </span>
                    <button type="button" onclick="window.print()" class="bg-school-blue text-white hover:bg-school-navy px-5 py-2 rounded text-sm font-medium transition-colors inline-flex items-center shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print Report Card
                    </button>
                </div>
            </div>

            <?php require __DIR__ . '/../../templates/components/report_card.php'; ?>
        </main>
    </div>
</div>

<style>
@media print {
    aside, header, nav, button, .no-print {
        display: none !important;
    }
    body, .print-layout-root, .print-layout-content, main {
        height: auto !important;
        min-height: 0 !important;
        display: block !important;
        overflow: visible !important;
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }
}
</style>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
