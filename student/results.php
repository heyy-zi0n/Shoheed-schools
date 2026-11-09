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

// Pre-check clearance for each term for the sidebar status badges
foreach ($availableTerms as &$t) {
    $tClr = check_student_tuition_result_clearance($pdo, (int)$student['id'], (int)$t['academic_session_id'], (int)$t['term_id']);
    $t['is_cleared'] = $tClr['is_cleared'];
    $t['has_waiver'] = $tClr['has_active_waiver'];
}
unset($t);

// Check if a specific term is selected
$selectedSessionId = filter_input(INPUT_GET, 'session', FILTER_VALIDATE_INT);
$selectedTermId = filter_input(INPUT_GET, 'term', FILTER_VALIDATE_INT);
$selectedClassId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);

$termDetails = null;
$report = null;
$clearance = null;

if ($selectedSessionId && $selectedTermId && $selectedClassId) {
    foreach ($availableTerms as $t) {
        if ($t['academic_session_id'] == $selectedSessionId && $t['term_id'] == $selectedTermId && $t['class_id'] == $selectedClassId) {
            $termDetails = $t;
            break;
        }
    }

    if ($termDetails) {
        $clearance = check_student_tuition_result_clearance(
            $pdo,
            (int)$student['id'],
            (int)$selectedSessionId,
            (int)$selectedTermId
        );

        if ($clearance['is_cleared']) {
            $report = build_student_report_card_data(
                $pdo,
                (int)$student['id'],
                (int)$selectedSessionId,
                (int)$selectedTermId,
                (int)$selectedClassId,
                true
            );
        }
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
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-2 sm:p-6 lg:p-8">
            <div class="no-print">
                <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            </div>
            
            <div class="mb-6 sm:mb-8 flex flex-wrap justify-between items-center gap-3 no-print">
                <div>
                    <h1 class="text-xl sm:text-2xl font-serif font-bold text-school-navy">My Results</h1>
                    <p class="text-xs sm:text-sm text-slate-gray">View and print your published Continuous Assessment Report.</p>
                </div>
                <?php if ($report): ?>
                <button type="button" onclick="window.print()" class="bg-school-blue text-white hover:bg-school-navy px-4 sm:px-5 py-2 rounded-md text-xs sm:text-sm font-semibold transition-colors inline-flex items-center shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    <span>Print Result</span>
                </button>
                <?php endif; ?>
            </div>
            
            <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 print-layout-row">
                <!-- Sidebar selector -->
                <div class="lg:w-1/4 no-print">
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 sticky top-6">
                        <h3 class="text-xs font-bold text-school-navy uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">Available Results</h3>
                        
                        <?php if (empty($availableTerms)): ?>
                            <p class="text-xs sm:text-sm text-slate-gray">No published results available yet.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($availableTerms as $t): 
                                    $isActive = ($termDetails && $t['term_id'] == $termDetails['term_id'] && $t['academic_session_id'] == $termDetails['academic_session_id'] && $t['class_id'] == $termDetails['class_id']);
                                ?>
                                    <li>
                                        <a href="<?= url("student/results.php?session={$t['academic_session_id']}&term={$t['term_id']}&class={$t['class_id']}") ?>" 
                                           class="block p-2.5 sm:p-3 rounded-md text-xs sm:text-sm transition-colors <?= $isActive ? 'bg-school-blue text-white font-medium shadow-sm' : 'bg-gray-50 text-charcoal hover:bg-gray-100 border border-gray-200' ?>">
                                            <div class="flex items-center justify-between gap-1">
                                                <span class="font-bold"><?= e($t['term_name']) ?></span>
                                                <?php if ($t['has_waiver']): ?>
                                                    <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded font-semibold <?= $isActive ? 'bg-amber-400 text-school-navy' : 'bg-amber-100 text-amber-900 border border-amber-300' ?>" title="Administrative Waiver Granted">
                                                        Waiver
                                                    </span>
                                                <?php elseif ($t['is_cleared']): ?>
                                                    <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded font-semibold <?= $isActive ? 'bg-green-400 text-school-navy' : 'bg-green-100 text-green-800 border border-green-200' ?>">
                                                        Cleared
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center text-[10px] px-1.5 py-0.5 rounded font-semibold <?= $isActive ? 'bg-red-400 text-white' : 'bg-red-100 text-school-red border border-red-200' ?>" title="Tuition Clearance Required">
                                                        <svg class="w-2.5 h-2.5 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                                        Locked
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] <?= $isActive ? 'text-blue-100' : 'text-slate-gray' ?> mt-0.5"><?= e($t['session_name']) ?> &bull; <?= e($t['class_name']) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Main result display -->
                <div class="lg:w-3/4 min-w-0 print-report-column">
                    <?php if ($report): ?>
                        <?php if (!empty($clearance['has_active_waiver'])): ?>
                        <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-3.5 text-xs sm:text-sm flex items-start gap-3 no-print shadow-sm">
                            <div class="p-1 rounded-full bg-amber-100 text-amber-700 shrink-0 mt-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-school-navy">Administrative Clearance Active</p>
                                <p class="text-xs text-amber-800 mt-0.5">
                                    Access to this academic report has been granted by School Management (<?= e($clearance['waiver']['granted_by_role'] ? format_role_name($clearance['waiver']['granted_by_role']) : 'Administration') ?>)<?= !empty($clearance['waiver']['reason']) ? ': "' . e($clearance['waiver']['reason']) . '"' : '.' ?>
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php require __DIR__ . '/../templates/components/report_card.php'; ?>

                        <div class="mt-6 text-center no-print">
                            <button type="button" onclick="window.print()" class="bg-gray-200 text-charcoal hover:bg-gray-300 px-6 py-2 rounded text-sm font-medium transition-colors inline-flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                Print Result
                            </button>
                        </div>
                    <?php elseif ($termDetails && (!$clearance || !$clearance['is_cleared'])): ?>
                        <!-- Tuition Clearance Required Lock Screen -->
                        <div class="bg-paper p-6 sm:p-8 rounded-xl shadow-sm border border-gray-200">
                            <!-- Header banner -->
                            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 pb-6 border-b border-gray-100">
                                <div class="w-12 h-12 rounded-xl bg-red-50 border border-red-200 flex items-center justify-center text-school-red shrink-0 shadow-sm">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-red-100 text-school-red">
                                        <span>Tuition Clearance Required</span>
                                    </div>
                                    <h2 class="text-lg sm:text-xl font-serif font-bold text-school-navy mt-1">Continuous Assessment Report Restricted</h2>
                                    <p class="text-xs sm:text-sm text-slate-gray">
                                        <?= e($termDetails['term_name']) ?> &bull; <?= e($termDetails['session_name']) ?> &bull; <?= e($termDetails['class_name']) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Explanatory note -->
                            <div class="my-6 bg-ivory/60 border border-gray-200 rounded-lg p-4 text-xs sm:text-sm text-charcoal leading-relaxed">
                                <p class="font-semibold text-school-navy mb-1">Shoheed Schools Financial Policy Notice:</p>
                                <p>In accordance with school policy, terminal Continuous Assessment Reports are restricted until a minimum of <strong>70% of current term tuition</strong> is paid, and all outstanding tuition debt from previous terms is 100% settled.</p>
                            </div>

                            <!-- Progress Breakdown -->
                            <div class="space-y-4 mb-6">
                                <!-- Current Term Tuition Meter -->
                                <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-xs">
                                    <div class="flex flex-wrap justify-between items-baseline gap-2 mb-2">
                                        <div class="text-xs font-bold uppercase tracking-wider text-slate-gray">
                                            <?= e($termDetails['term_name']) ?> Tuition Progress
                                        </div>
                                        <div class="text-xs sm:text-sm font-bold <?= $clearance['current_percentage'] >= 70 ? 'text-sage' : 'text-school-red' ?>">
                                            ₦<?= number_format($clearance['current_tuition_paid'], 2) ?> / ₦<?= number_format($clearance['current_tuition_invoiced'], 2) ?>
                                            <span class="text-xs text-slate-gray font-normal">(<?= number_format($clearance['current_percentage'], 1) ?>%)</span>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="relative w-full h-3 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                                        <div class="h-full rounded-full transition-all duration-500 <?= $clearance['current_percentage'] >= 70 ? 'bg-sage' : 'bg-amber' ?>"
                                             style="width: <?= min(100, $clearance['current_percentage']) ?>%;"></div>
                                        <!-- 70% threshold marker -->
                                        <div class="absolute top-0 bottom-0 left-[70%] w-0.5 bg-school-navy" title="70% Clearance Threshold"></div>
                                    </div>
                                    <div class="flex justify-between items-center text-[10px] text-slate-gray mt-1.5 font-medium">
                                        <span>0%</span>
                                        <span class="text-school-navy font-bold">70% Threshold Required</span>
                                        <span>100%</span>
                                    </div>
                                </div>

                                <?php if ($clearance['prior_tuition_debt'] > 0): ?>
                                <!-- Prior Term Tuition Debt -->
                                <div class="bg-red-50/70 border border-red-200 rounded-lg p-4">
                                    <div class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-school-red shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        <div class="flex-1">
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs font-bold text-school-red uppercase tracking-wide">Prior Term Tuition Balance</span>
                                                <span class="text-sm font-bold text-school-red">₦<?= number_format($clearance['prior_tuition_debt'], 2) ?></span>
                                            </div>
                                            <p class="text-xs text-red-700 mt-1">
                                                Outstanding tuition balances from prior academic terms must be fully settled before current term results can be released.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Total Needed to Unlock -->
                                <div class="bg-ivory border border-school-blue/20 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                                    <div>
                                        <div class="text-xs font-bold text-school-navy uppercase tracking-wider">Amount Needed to Unlock Result</div>
                                        <div class="text-xs text-slate-gray mt-0.5">Pay this amount or higher to automatically clear result access.</div>
                                    </div>
                                    <div class="text-xl sm:text-2xl font-bold font-serif text-school-navy">
                                        ₦<?= number_format($clearance['total_needed_to_unlock'], 2) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions & Assistance -->
                            <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                                <a href="<?= url('student/fees.php') ?>" class="bg-school-blue hover:bg-school-navy text-white px-5 py-2.5 rounded-md text-xs sm:text-sm font-semibold transition-colors inline-flex items-center justify-center shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                    <span>Proceed to Fee Payment</span>
                                </a>

                                <div class="text-[11px] sm:text-xs text-slate-gray sm:text-right">
                                    <span>Special arrangement or promissory note?</span><br>
                                    <span class="text-charcoal font-medium">Contact Bursary or Principal for Administrative Clearance.</span>
                                </div>
                            </div>
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
