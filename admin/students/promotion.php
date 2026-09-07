<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pdo = get_db_connection();

$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY name DESC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY level ASC")->fetchAll();

$sourceSessionId = filter_var($_GET['source_session_id'] ?? null, FILTER_VALIDATE_INT);
$sourceClassId = filter_var($_GET['source_class_id'] ?? null, FILTER_VALIDATE_INT);
$targetSessionId = filter_var($_GET['target_session_id'] ?? null, FILTER_VALIDATE_INT);

$students = [];
$termData = [];
$totalTermsInSession = 0;
$sourceSessionName = '';
$sourceClassName = '';

$progressionMap = [
    'JSS1' => 'JSS2',
    'JSS2' => 'JSS3',
    'JSS3' => 'SSS1',
    'SSS1' => 'SSS2',
    'SSS2' => 'SSS3',
    'SSS3' => 'GRADUATED'
];

if ($sourceSessionId && $sourceClassId) {
    // Determine expected target class
    foreach ($sessions as $s) {
        if ((int)$s['id'] === $sourceSessionId) {
            $sourceSessionName = $s['name'];
        }
    }
    
    $sourceClass = null;
    foreach ($classes as $c) {
        if ((int)$c['id'] === $sourceClassId) {
            $sourceClass = $c;
            $sourceClassName = $c['name'];
        }
    }
    
    $targetClassName = $progressionMap[$sourceClassName] ?? null;
    $targetClassId = null;
    
    if ($targetClassName && $targetClassName !== 'GRADUATED') {
        foreach ($classes as $c) {
            if ($c['name'] === $targetClassName) {
                $targetClassId = $c['id'];
                break;
            }
        }
    }

    // Get number of terms for the source session (usually 3)
    $termStmt = $pdo->prepare("SELECT id, name FROM terms WHERE academic_session_id = ? ORDER BY start_date ASC");
    $termStmt->execute([$sourceSessionId]);
    $terms = $termStmt->fetchAll();
    $totalTermsInSession = count($terms);

    // Get Active Students in this class/session
    $stuStmt = $pdo->prepare("
        SELECT st.id, st.admission_number, st.first_name, st.middle_name, st.last_name, se.id as enrollment_id
        FROM students st
        JOIN student_enrollments se ON st.id = se.student_id
        WHERE se.class_id = ? AND se.academic_session_id = ? AND se.status = 'ACTIVE'
        ORDER BY st.last_name ASC, st.first_name ASC
    ");
    $stuStmt->execute([$sourceClassId, $sourceSessionId]);
    $students = $stuStmt->fetchAll();

    if (!empty($students)) {
        // Fetch all published scores for these students in this session/class
        $scoreStmt = $pdo->prepare("
            SELECT sc.student_id, rb.term_id, sc.subject_id, sc.total_score
            FROM scores sc
            JOIN result_batches rb ON sc.result_batch_id = rb.id
            WHERE rb.class_id = ? AND rb.academic_session_id = ? AND rb.status = 'PUBLISHED'
        ");
        $scoreStmt->execute([$sourceClassId, $sourceSessionId]);
        $rawScores = $scoreStmt->fetchAll();

        // Structure: $termData[student_id][term_id] = ['total' => X, 'count' => Y]
        foreach ($rawScores as $row) {
            $sid = $row['student_id'];
            $tid = $row['term_id'];
            if (!isset($termData[$sid][$tid])) {
                $termData[$sid][$tid] = ['total' => 0, 'count' => 0];
            }
            $termData[$sid][$tid]['total'] += (float)$row['total_score'];
            $termData[$sid][$tid]['count'] += 1;
        }
    }
}

$pageTitle = 'Student Promotion';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Student Promotion Eligibility</h1>
                <p class="text-sm text-slate-gray">Calculate term-based averages to determine promotion eligibility (>= 45%).</p>
            </div>

            <!-- Filters -->
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
                <form action="<?= url('admin/students/promotion.php') ?>" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Source Session</label>
                        <select name="source_session_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Session...</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $sourceSessionId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Source Class</label>
                        <select name="source_class_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Class...</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $sourceClassId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Target Session (Next)</label>
                        <select name="target_session_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Session...</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $targetSessionId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-auto flex-1">
                        <button type="submit" class="w-full bg-charcoal text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors">Analyze Eligibility</button>
                    </div>
                </form>
            </div>
            
            <?php if ($sourceSessionId && $sourceClassId): ?>
                <?php if (empty($students)): ?>
                    <div class="bg-paper p-8 text-center rounded-lg border border-gray-200">
                        <p class="text-slate-gray">No ACTIVE students enrolled in this class for the selected session.</p>
                    </div>
                <?php elseif (!$targetSessionId): ?>
                     <div class="bg-paper p-8 text-center rounded-lg border border-gray-200">
                        <p class="text-slate-gray">Please select a Target Session to enable the promotion workflow.</p>
                    </div>
                <?php else: ?>
                    
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded text-sm text-school-navy">
                        <strong>Progression Rule:</strong> <?= e($sourceClassName) ?> &rarr; <span class="font-bold text-school-blue"><?= e($targetClassName ?? 'N/A') ?></span>
                        <?php if ($targetClassName === 'GRADUATED'): ?>
                            (Terminal Class. Eligible students will be marked as GRADUATED.)
                        <?php endif; ?>
                    </div>

                    <form action="<?= url('actions/students/promote.php') ?>" method="POST" id="promotion-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="source_session_id" value="<?= (int)$sourceSessionId ?>">
                        <input type="hidden" name="source_class_id" value="<?= (int)$sourceClassId ?>">
                        <input type="hidden" name="target_session_id" value="<?= (int)$targetSessionId ?>">
                        
                        <?php if ($targetClassName !== 'GRADUATED' && $targetClassId): ?>
                            <input type="hidden" name="target_class_id" value="<?= (int)$targetClassId ?>">
                        <?php elseif ($targetClassName === 'GRADUATED'): ?>
                            <input type="hidden" name="mark_graduated" value="1">
                        <?php else: ?>
                            <div class="alert alert-error mb-4">Error: Target class mapping not found for <?= e($sourceClassName) ?>.</div>
                        <?php endif; ?>

                        <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-x-auto mb-6">
                            <table class="min-w-full divide-y divide-gray-200 border-collapse">
                                <thead class="bg-school-navy text-white text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Student</th>
                                        <th class="px-4 py-3 text-center">Term Averages</th>
                                        <th class="px-4 py-3 text-center bg-school-blue">Session Avg</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                    <?php 
                                    foreach ($students as $student): 
                                        $sid = $student['id'];
                                        $completedTerms = 0;
                                        $sumTermAverages = 0;
                                        $termAvgsText = [];
                                        
                                        foreach ($terms as $term) {
                                            $tid = $term['id'];
                                            if (isset($termData[$sid][$tid]) && $termData[$sid][$tid]['count'] > 0) {
                                                $termAvg = $termData[$sid][$tid]['total'] / $termData[$sid][$tid]['count'];
                                                $sumTermAverages += $termAvg;
                                                $completedTerms++;
                                                $termAvgsText[] = e($term['name']) . ': ' . number_format($termAvg, 1) . '%';
                                            } else {
                                                $termAvgsText[] = e($term['name']) . ': N/A';
                                            }
                                        }
                                        
                                        $sessionAvg = 0;
                                        $isEligible = false;
                                        $isIncomplete = ($completedTerms < $totalTermsInSession);
                                        
                                        if ($completedTerms > 0) {
                                            $sessionAvg = $sumTermAverages / $completedTerms;
                                            $isEligible = ($sessionAvg >= 45.0);
                                        }
                                    ?>
                                        <tr class="hover:bg-ivory transition-colors <?= $isEligible && !$isIncomplete ? 'bg-green-50' : '' ?>">
                                            <td class="px-4 py-3 font-medium text-charcoal">
                                                <?= e($student['last_name'] . ', ' . $student['first_name']) ?>
                                                <div class="text-xs text-slate-gray"><?= e($student['admission_number']) ?></div>
                                            </td>
                                            
                                            <td class="px-4 py-3 text-center text-xs text-slate-gray">
                                                <?= implode('<br>', $termAvgsText) ?>
                                            </td>
                                            
                                            <td class="px-4 py-3 text-center font-bold bg-gray-50 <?= $sessionAvg >= 45.0 ? 'text-sage' : 'text-dusty-red' ?>">
                                                <?= $completedTerms > 0 ? number_format($sessionAvg, 1) . '%' : 'N/A' ?>
                                            </td>
                                            
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($isIncomplete): ?>
                                                    <span class="px-2 py-1 rounded text-xs font-bold bg-yellow-100 text-yellow-800 border border-yellow-200">INCOMPLETE SESSION RESULTS</span><br>
                                                    <span class="text-[10px] text-gray-500">(Provisional Avg shown)</span>
                                                <?php elseif ($isEligible): ?>
                                                    <span class="px-2 py-1 rounded text-xs font-bold bg-green-100 text-green-800 border border-green-200">ELIGIBLE (>=45%)</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-800 border border-red-200">NOT ELIGIBLE (<45%)</span>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <td class="px-4 py-3 text-center">
                                                <select name="action[<?= (int)$student['enrollment_id'] ?>]" class="border border-gray-300 rounded p-1 text-sm">
                                                    <?php if ($isIncomplete): ?>
                                                        <option value="skip" selected>Skip (Incomplete)</option>
                                                        <option value="override_promote">Override: Promote</option>
                                                        <option value="override_repeat">Override: Repeat</option>
                                                    <?php elseif ($isEligible): ?>
                                                        <option value="promote" selected>Promote to <?= e($targetClassName) ?></option>
                                                        <option value="skip">Skip / Hold</option>
                                                    <?php else: ?>
                                                        <option value="repeat" selected>Repeat <?= e($sourceClassName) ?></option>
                                                        <option value="skip">Skip / Hold</option>
                                                        <option value="override_promote">Override: Promote to <?= e($targetClassName) ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($targetClassName === 'GRADUATED' || $targetClassId): ?>
                            <div class="flex justify-end bg-paper p-4 rounded border border-gray-200 items-center gap-4">
                                <p class="text-sm text-slate-gray font-medium">Verify actions before proceeding.</p>
                                <button type="button" onclick="if(confirm('Execute batch promotion/retention logic? This action will create new enrollments in the target session and mark current enrollments as PROMOTED/REPEATED.')) document.getElementById('promotion-form').submit();" class="bg-school-navy text-white px-6 py-3 rounded font-bold hover:bg-school-blue transition-colors shadow">
                                    Execute Batch Action
                                </button>
                            </div>
                        <?php endif; ?>

                    </form>
                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
