<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);
$subjectId = filter_input(INPUT_GET, 'subject', FILTER_VALIDATE_INT);

if (!$classId || !$subjectId) {
    redirect('teacher/results/index.php');
}

$staffId = current_staff_id();
if (!$staffId) {
    set_flash('error', 'Access denied. You do not have a staff profile.');
    redirect('');
}

$pageTitle = 'Enter Scores';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

if (!$activeSession || !$activeTerm) {
    set_flash('error', 'No active academic session or term is configured.');
    redirect('teacher/results/index.php');
}

// Verify assignment
if (!staff_can_access_subject_assignment($staffId, $classId, $subjectId, $activeSession['id'])) {
    set_flash('error', 'Unauthorized access to this subject.');
    redirect('teacher/results/index.php');
}

$classStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
$classStmt->execute([$classId]);
$className = $classStmt->fetchColumn();

$subjectStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
$subjectStmt->execute([$subjectId]);
$subjectName = $subjectStmt->fetchColumn();

// Get students enrolled in this class
$stmt = $pdo->prepare("
    SELECT s.id, s.admission_number, s.first_name, s.last_name, s.middle_name
    FROM students s
    JOIN student_enrollments se ON s.id = se.student_id
    WHERE se.class_id = :class_id AND se.academic_session_id = :session_id AND se.status = 'ACTIVE'
    ORDER BY s.last_name ASC
");
$stmt->execute(['class_id' => $classId, 'session_id' => $activeSession['id']]);
$students = $stmt->fetchAll();

// Get existing batch and subject status
$stmt = $pdo->prepare("
    SELECT rb.id as batch_id, rb.status as class_status, ss.status as subject_status, ss.comments
    FROM result_batches rb
    LEFT JOIN subject_submissions ss ON rb.id = ss.result_batch_id AND ss.subject_id = :sid
    WHERE rb.class_id = :cid AND rb.academic_session_id = :sess AND rb.term_id = :tid
");
$stmt->execute(['sid' => $subjectId, 'cid' => $classId, 'sess' => $activeSession['id'], 'tid' => $activeTerm['id']]);
$batchInfo = $stmt->fetch();

$batchId = $batchInfo ? $batchInfo['batch_id'] : null;
$subjectStatus = $batchInfo && $batchInfo['subject_status'] ? $batchInfo['subject_status'] : 'DRAFT';
$classStatus = $batchInfo ? $batchInfo['class_status'] : 'DRAFT';
$revisionComments = $batchInfo ? $batchInfo['comments'] : null;

// Readonly if subject is SUBMITTED or if Class Batch is verified/approved/published
$readonly = ($subjectStatus === 'SUBMITTED' || in_array($classStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED', 'PUBLISHED']));

// Get existing scores
$scoresByStudent = [];
$completedCount = 0;
if ($batchId) {
    $stmt = $pdo->prepare("SELECT * FROM scores WHERE result_batch_id = :bid AND subject_id = :sid");
    $stmt->execute(['bid' => $batchId, 'sid' => $subjectId]);
    foreach ($stmt->fetchAll() as $score) {
        $scoresByStudent[$score['student_id']] = $score;
    }
}
foreach ($students as $stRow) {
    $sc = $scoresByStudent[$stRow['id']] ?? null;
    if ($sc && ($sc['ca_score'] !== null || $sc['exam_score'] !== null)) {
        $completedCount++;
    }
}
$totalStudentCount = count($students);

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="page-header">
                <div>
                    <a href="<?= url('teacher/results/index.php') ?>" class="text-xs font-semibold text-school-blue hover:text-school-navy mb-2 inline-flex items-center gap-1">
                        <span aria-hidden="true">&larr;</span>
                        <span>Back to Assigned Subjects</span>
                    </a>
                    <p class="page-eyebrow mt-1">Result Entry</p>
                    <h1 class="text-2xl sm:text-3xl font-serif font-bold text-school-navy mt-0.5"><?= e($subjectName) ?> &mdash; <?= e($className) ?></h1>
                    <p class="text-sm text-slate-gray mt-1">
                        <?= e($activeTerm['name']) ?> &bull; <?= e($activeSession['name']) ?> Session
                    </p>
                </div>
                <div class="flex flex-col sm:items-end gap-2">
                    <?php
                    $statusBadgeClass = match ($subjectStatus) {
                        'SUBMITTED' => 'status-badge-info',
                        'REVISION_REQUIRED' => 'status-badge-warning',
                        default => 'status-badge-neutral',
                    };
                    ?>
                    <span class="status-badge <?= $statusBadgeClass ?>">
                        <span class="w-1.5 h-1.5 rounded-full bg-current" aria-hidden="true"></span>
                        Status: <?= e($subjectStatus) ?>
                    </span>
                    <?php if ($totalStudentCount > 0): ?>
                        <div class="flex items-center gap-3 text-xs text-slate-gray">
                            <label for="score-completion-progress" class="font-semibold text-charcoal">
                                Progress: <span id="score-progress-text" class="tabular-nums font-bold text-school-navy"><?= $completedCount ?></span> of <span class="tabular-nums"><?= $totalStudentCount ?></span> completed
                            </label>
                            <progress id="score-completion-progress" max="<?= $totalStudentCount ?>" value="<?= $completedCount ?>" class="w-24 h-2 rounded overflow-hidden accent-school-blue"></progress>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-200/80">
                <?php if ($subjectStatus === 'REVISION_REQUIRED'): ?>
                    <div class="mb-6 p-4 bg-yellow-50 text-yellow-800 rounded-md border border-yellow-200">
                        <h2 class="font-bold text-sm mb-1">Revision Required</h2>
                        <p class="text-sm"><?= e($revisionComments ?? 'Please review and resubmit the scores.') ?></p>
                    </div>
                <?php elseif ($readonly): ?>
                    <div class="mb-6 p-4 bg-blue-50 text-blue-800 rounded-md text-sm border border-blue-200">
                        <?php 
                            if ($classStatus === 'PUBLISHED') echo 'This result has been published and can no longer be edited.';
                            elseif ($classStatus === 'PRINCIPAL_APPROVED') echo 'This result has been approved by the Principal and is locked from editing.';
                            elseif ($classStatus === 'CLASS_TEACHER_VERIFIED') echo 'This result has been verified by the Class Teacher and is locked from editing.';
                            else echo 'These scores have been submitted and are currently locked from editing.';
                        ?>
                    </div>
                <?php endif; ?>
                
                <form action="<?= url('actions/results/save_scores.php') ?>" method="POST" id="score-entry-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    <input type="hidden" name="action_type" value="save_draft">
                    
                    <div class="overflow-x-auto max-h-[640px]" role="region" aria-label="Student score entry table" tabindex="0">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                            <thead class="bg-ivory sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-gray uppercase tracking-wider w-1/2">Student</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-slate-gray uppercase tracking-wider">CA / 40</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-slate-gray uppercase tracking-wider">Exam / 60</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-slate-gray uppercase tracking-wider">Total / 100</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $student): 
                                    $sid = $student['id'];
                                    $score = $scoresByStudent[$sid] ?? null;
                                    $ca = $score ? $score['ca_score'] : '';
                                    $ex = $score ? $score['exam_score'] : '';
                                    $tot = $score ? $score['total_score'] : '';
                                    $studentLabel = trim($student['last_name'] . ' ' . $student['first_name']);
                                ?>
                                <tr class="hover:bg-ivory/40 transition-colors" data-student-row="<?= $sid ?>">
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <div class="text-sm font-semibold text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']) ?></div>
                                        <div class="text-xs text-slate-gray font-mono"><?= e($student['admission_number']) ?></div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                        <input type="number" step="0.01" min="0" max="40" name="scores[<?= $sid ?>][ca]" aria-label="CA Score for <?= e($studentLabel) ?>" value="<?= e($ca) ?>" <?= $readonly ? 'disabled' : '' ?> class="score-input w-20 px-2 py-1.5 text-center tabular-nums border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue sm:text-sm" data-student-id="<?= $sid ?>" data-score-part="ca">
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                        <input type="number" step="0.01" min="0" max="60" name="scores[<?= $sid ?>][exam]" aria-label="Exam Score for <?= e($studentLabel) ?>" value="<?= e($ex) ?>" <?= $readonly ? 'disabled' : '' ?> class="score-input w-20 px-2 py-1.5 text-center tabular-nums border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue sm:text-sm" data-student-id="<?= $sid ?>" data-score-part="exam">
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap text-center font-bold text-school-navy tabular-nums" id="total_<?= $sid ?>">
                                        <?= e($tot) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($students)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-gray">No active students in this class.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!$readonly && !empty($students)): ?>
                    <div class="mt-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-ivory/60 p-4 border border-gray-200 rounded-md">
                        <p class="text-xs text-slate-gray">
                            Save your work as a draft at any time, or submit completed scores to the Class Teacher for verification.
                        </p>
                        <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                            <button type="submit" data-action-type="save_draft" class="bg-paper text-school-navy border border-gray-300 px-5 py-2.5 rounded-md font-semibold text-sm hover:bg-gray-50 transition-colors shadow-sm">
                                Save Draft
                            </button>
                            <button type="submit" data-action-type="submit" data-confirm="Submit these scores to the Class Teacher for verification? Once submitted, scores are locked from editing unless returned for revision." class="bg-school-blue text-white px-6 py-2.5 rounded-md font-semibold text-sm hover:bg-school-navy transition-colors shadow-sm">
                                Submit Scores
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
        </main>
    </div>
</div>

<script>
function calculateTotal(studentId) {
    const caInput = document.querySelector(`input[name="scores[${studentId}][ca]"]`);
    const examInput = document.querySelector(`input[name="scores[${studentId}][exam]"]`);
    const totalDisplay = document.getElementById(`total_${studentId}`);
    if (!caInput || !examInput || !totalDisplay) return;
    
    let ca = parseFloat(caInput.value) || 0;
    let exam = parseFloat(examInput.value) || 0;
    
    if (ca > 40) { ca = 40; caInput.value = 40; }
    if (exam > 60) { exam = 60; examInput.value = 60; }
    
    let total = ca + exam;
    
    if (caInput.value === '' && examInput.value === '') {
        totalDisplay.innerText = '';
    } else {
        totalDisplay.innerText = total.toFixed(2);
    }
    updateCompletionProgress();
}

function updateCompletionProgress() {
    const rows = document.querySelectorAll('tr[data-student-row]');
    let completed = 0;
    rows.forEach(row => {
        const sid = row.getAttribute('data-student-row');
        const ca = document.querySelector(`input[name="scores[${sid}][ca]"]`);
        const exam = document.querySelector(`input[name="scores[${sid}][exam]"]`);
        if ((ca && ca.value !== '') || (exam && exam.value !== '')) {
            completed++;
        }
    });
    const textEl = document.getElementById('score-progress-text');
    const progEl = document.getElementById('score-completion-progress');
    if (textEl) textEl.textContent = String(completed);
    if (progEl) progEl.value = completed;
}

document.querySelectorAll('.score-input[data-student-id]').forEach(input => {
    input.addEventListener('input', () => {
        calculateTotal(input.getAttribute('data-student-id'));
    });
});
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
