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
    die("Student profile not found.");
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

$scores = [];
$termDetails = null;

if ($selectedSessionId && $selectedTermId && $selectedClassId) {
    // Find term name
    foreach ($availableTerms as $t) {
        if ($t['academic_session_id'] == $selectedSessionId && $t['term_id'] == $selectedTermId) {
            $termDetails = $t;
            break;
        }
    }
    
    if ($termDetails) {
        $stmt = $pdo->prepare("
            SELECT sub.name as subject_name, s.ca_score, s.exam_score, s.total_score, s.grade, s.remark
            FROM scores s
            JOIN result_batches rb ON s.result_batch_id = rb.id
            JOIN subjects sub ON s.subject_id = sub.id
            WHERE s.student_id = :sid AND rb.academic_session_id = :session_id AND rb.term_id = :term_id AND rb.class_id = :class_id AND rb.status = 'PUBLISHED'
            ORDER BY sub.name ASC
        ");
        $stmt->execute([
            'sid' => $student['id'],
            'session_id' => $selectedSessionId,
            'term_id' => $selectedTermId,
            'class_id' => $selectedClassId
        ]);
        $scores = $stmt->fetchAll();
    }
} elseif (!empty($availableTerms)) {
    // Select first one by default
    $default = $availableTerms[0];
    redirect("student/results.php?session={$default['academic_session_id']}&term={$default['term_id']}&class={$default['class_id']}");
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">My Results</h1>
                <p class="text-sm text-slate-gray">View your published academic performance.</p>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Sidebar selector -->
                <div class="md:w-1/4">
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 sticky top-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Available Results</h3>
                        
                        <?php if (empty($availableTerms)): ?>
                            <p class="text-sm text-slate-gray">No published results available yet.</p>
                        <?php else: ?>
                            <ul class="space-y-2">
                                <?php foreach ($availableTerms as $t): 
                                    $isActive = ($termDetails && $t['term_id'] == $termDetails['term_id'] && $t['academic_session_id'] == $termDetails['academic_session_id']);
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
                <div class="md:w-3/4">
                    <?php if ($termDetails): ?>
                        <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100">
                            <!-- Report Card Header -->
                            <div class="border-b-4 border-school-blue pb-6 mb-8 text-center flex flex-col items-center">
                                <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Logo" class="h-16 mb-4">
                                <h2 class="text-2xl font-serif font-bold text-school-navy uppercase tracking-wide">Shoheed Schools</h2>
                                <p class="text-xs text-school-red uppercase tracking-widest font-bold mb-4">Terminal Academic Report</p>
                                
                                <div class="w-full flex justify-between text-left mt-6 pt-6 border-t border-gray-100 bg-gray-50 p-4 rounded text-sm">
                                    <div>
                                        <p><span class="font-semibold text-slate-gray">Name:</span> <span class="font-bold text-charcoal"><?= e($student['last_name'] . ' ' . $student['first_name']) ?></span></p>
                                        <p><span class="font-semibold text-slate-gray">Admission No:</span> <span class="font-bold text-charcoal"><?= e($student['admission_number']) ?></span></p>
                                    </div>
                                    <div>
                                        <p><span class="font-semibold text-slate-gray">Class:</span> <span class="font-bold text-charcoal"><?= e($termDetails['class_name']) ?></span></p>
                                        <p><span class="font-semibold text-slate-gray">Term/Session:</span> <span class="font-bold text-charcoal"><?= e($termDetails['term_name']) ?> (<?= e($termDetails['session_name']) ?>)</span></p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Grades -->
                            <?php if (empty($scores)): ?>
                                <p class="text-center text-slate-gray py-8">No scores recorded for this term.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                                        <thead class="bg-school-navy text-white">
                                            <tr>
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Subject</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">CA (40)</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Exam (60)</th>
                                                <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Total (100)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php 
                                            $totalOverall = 0;
                                            $subjectCount = count($scores);
                                            foreach ($scores as $score): 
                                                $ca = (float)($score['ca_score'] ?? 0);
                                                $ex = (float)($score['exam_score'] ?? 0);
                                                $tot = (float)($score['total_score'] ?? 0);
                                                $totalOverall += $tot;
                                            ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-charcoal">
                                                    <?= e($score['subject_name']) ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-slate-gray">
                                                    <?= $score['ca_score'] !== null ? number_format($ca, 1) : '-' ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-slate-gray">
                                                    <?= $score['exam_score'] !== null ? number_format($ex, 1) : '-' ?>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-bold text-school-navy">
                                                    <?= $score['total_score'] !== null ? number_format($tot, 1) : '-' ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                            <tr>
                                                <td colspan="3" class="px-4 py-3 whitespace-nowrap text-right text-sm font-bold text-charcoal uppercase">
                                                    Overall Average:
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap text-center font-bold text-lg text-school-blue">
                                                    <?= $subjectCount > 0 ? number_format($totalOverall / $subjectCount, 1) : '0.0' ?>%
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                
                                <div class="mt-12 flex justify-between border-t border-gray-200 pt-8">
                                    <div class="text-center">
                                        <div class="w-48 border-b border-gray-800 mb-2"></div>
                                        <p class="text-xs text-slate-gray uppercase tracking-wider font-bold">Class Teacher's Signature</p>
                                    </div>
                                    <div class="text-center">
                                        <div class="w-48 border-b border-gray-800 mb-2"></div>
                                        <p class="text-xs text-slate-gray uppercase tracking-wider font-bold">Principal's Signature</p>
                                    </div>
                                </div>
                                
                                <div class="mt-8 text-center">
                                    <button onclick="window.print()" class="bg-gray-200 text-charcoal hover:bg-gray-300 px-6 py-2 rounded text-sm font-medium transition-colors inline-flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                        Print Result
                                    </button>
                                </div>
                            <?php endif; ?>
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
    .bg-school-navy, .bg-school-blue, aside, header, .flex-1.flex-col, button, .md\:w-1\/4, nav {
        display: none !important;
    }
    .flex.h-screen { height: auto !important; display: block !important; overflow: visible !important; }
    .md\:w-3\/4 { width: 100% !important; margin: 0 !important; }
    main { padding: 0 !important; overflow: visible !important; background: white !important; }
    .bg-paper { box-shadow: none !important; border: none !important; padding: 0 !important; }
}
</style>

<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
