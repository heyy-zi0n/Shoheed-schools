<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_STUDENT]);

$pageTitle = 'My Results';
$pdo = get_db_connection();
$userId = current_user_id();

// Get student
$stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('error', 'Student profile not found.');
    redirect('student/dashboard.php');
}

// Fetch all available published results for this student
$stmt = $pdo->prepare("
    SELECT DISTINCT rb.academic_session_id, rb.term_id, a.name as session_name, t.name as term_name, c.name as class_name, c.id as class_id
    FROM scores s
    JOIN result_batches rb ON s.result_batch_id = rb.id
    JOIN academic_sessions a ON rb.academic_session_id = a.id
    JOIN terms t ON rb.term_id = t.id
    JOIN classes c ON rb.class_id = c.id
    WHERE s.student_id = :sid AND rb.status = 'PUBLISHED'
    ORDER BY a.start_date DESC, t.start_date DESC
");
$stmt->execute(['sid' => $student['id']]);
$availableTerms = $stmt->fetchAll();

// Check if a specific term is selected
$selectedSessionId = filter_input(INPUT_GET, 'session', FILTER_VALIDATE_INT);
$selectedTermId = filter_input(INPUT_GET, 'term', FILTER_VALIDATE_INT);
$selectedClassId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);

$termDetails = null;
$report = null;

if ($selectedSessionId && $selectedTermId && $selectedClassId) {
    foreach ($availableTerms as $t) {
        if ($t['academic_session_id'] == $selectedSessionId && $t['term_id'] == $selectedTermId && $t['class_id'] == $selectedClassId) {
            $termDetails = $t;
            break;
        }
    }

    if ($termDetails) {
        $report = build_student_report_card_data(
            $pdo,
            (int)$student['id'],
            (int)$selectedSessionId,
            (int)$selectedTermId,
            (int)$selectedClassId,
            true
        );
    }
} elseif (!empty($availableTerms)) {
    // Select first one by default
    $default = $availableTerms[0];
    redirect("student/results.php?session={$default['academic_session_id']}&term={$default['term_id']}&class={$default['class_id']}");
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden print-layout-root">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden print-layout-content">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="no-print">
                <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            </div>
            
            <div class="mb-8 flex flex-wrap justify-between items-center gap-4 no-print">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">My Results</h1>
                    <p class="text-sm text-slate-gray">View and print your published Continuous Assessment Report.</p>
                </div>
                <?php if ($report): ?>
                <button type="button" onclick="window.print()" class="bg-school-blue text-white hover:bg-school-navy px-5 py-2 rounded text-sm font-medium transition-colors inline-flex items-center shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Result
                </button>
                <?php endif; ?>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8 print-layout-row">
                <!-- Sidebar selector -->
                <div class="md:w-1/4 no-print">
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 sticky top-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Available Results</h3>
                        
                        <?php if (empty($availableTerms)): ?>
                            <p class="text-sm text-slate-gray">No published results available yet.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($availableTerms as $t): 
                                    $isActive = ($termDetails && $t['term_id'] == $termDetails['term_id'] && $t['academic_session_id'] == $termDetails['academic_session_id'] && $t['class_id'] == $termDetails['class_id']);
                                ?>
                                    <li>
                                        <a href="<?= url("student/results.php?session={$t['academic_session_id']}&term={$t['term_id']}&class={$t['class_id']}") ?>" 
                                           class="block p-3 rounded-md text-sm transition-colors <?= $isActive ? 'bg-school-blue text-white font-medium' : 'bg-gray-50 text-charcoal hover:bg-gray-100 border border-gray-200' ?>">
                                            <div class="font-bold"><?= e($t['term_name']) ?></div>
                                            <div class="text-xs <?= $isActive ? 'text-blue-200' : 'text-slate-gray' ?>"><?= e($t['session_name']) ?> &bull; <?= e($t['class_name']) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Main result display -->
                <div class="md:w-3/4 print-report-column">
                    <?php if ($report): ?>
                        <?php require __DIR__ . '/../templates/components/report_card.php'; ?>

                        <div class="mt-6 text-center no-print">
                            <button type="button" onclick="window.print()" class="bg-gray-200 text-charcoal hover:bg-gray-300 px-6 py-2 rounded text-sm font-medium transition-colors inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                Print Result
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="bg-paper p-12 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center justify-center text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <h3 class="text-lg font-medium text-school-navy mb-2">Select a Result</h3>
                            <p class="text-sm text-slate-gray max-w-md">Please select an academic term from the sidebar to view your detailed performance report.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </main>
    </div>
</div>

<style>
@media print {
    aside, header, nav, button, .no-print {
        display: none !important;
    }
    body, .print-layout-root, .print-layout-content, main, .print-layout-row {
        height: auto !important;
        min-height: 0 !important;
        display: block !important;
        overflow: visible !important;
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .print-report-column {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
    }
}
</style>

<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
