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
$activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();

if (!$activeSession || !$activeTerm) {
    redirect('teacher/results/index.php');
}

// Verify assignment
if (!staff_can_access_subject_assignment($staffId, $classId, $subjectId, $activeSession['id'])) {
    die('Unauthorized access to this subject.');
}

$className = $pdo->query("SELECT name FROM classes WHERE id = " . (int)$classId)->fetchColumn();
$subjectName = $pdo->query("SELECT name FROM subjects WHERE id = " . (int)$subjectId)->fetchColumn();

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
if ($batchId) {
    $stmt = $pdo->prepare("SELECT * FROM scores WHERE result_batch_id = :bid AND subject_id = :sid");
    $stmt->execute(['bid' => $batchId, 'sid' => $subjectId]);
    foreach ($stmt->fetchAll() as $score) {
        $scoresByStudent[$score['student_id']] = $score;
    }
}

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-8 flex justify-between items-center">
                <div>
                    <a href="<?= url('teacher/results/index.php') ?>" class="text-sm text-slate-gray hover:text-school-navy mb-2 inline-block">&larr; Back to Subjects</a>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Score Entry</h1>
                    <p class="text-sm text-slate-gray">
                        <?= e($className) ?> &bull; <?= e($subjectName) ?> &bull; <?= e($activeTerm['name']) ?>
                    </p>
                </div>
                <div>
                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-200 text-gray-800">
                        Status: <?= e($subjectStatus) ?>
                    </span>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <?php if ($subjectStatus === 'REVISION_REQUIRED'): ?>
                    <div class="mb-6 p-4 bg-yellow-50 text-yellow-800 rounded-md border border-yellow-200">
                        <h4 class="font-bold mb-1">Revision Required</h4>
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
                
                <form action="<?= url('actions/results/save_scores.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="class_id" value="<?= $classId ?>">
                    <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider w-1/2">Student</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">CA (Max 40)</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Exam (Max 60)</th>
                                    <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($students as $student): 
                                    $sid = $student['id'];
                                    $score = $scoresByStudent[$sid] ?? null;
                                    $ca = $score ? $score['ca_score'] : '';
                                    $ex = $score ? $score['exam_score'] : '';
                                    $tot = $score ? $score['total_score'] : '';
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']) ?></div>
                                        <div class="text-xs text-slate-gray"><?= e($student['admission_number']) ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <input type="number" step="0.01" min="0" max="40" name="scores[<?= $sid ?>][ca]" value="<?= e($ca) ?>" <?= $readonly ? 'disabled' : '' ?> class="score-input w-20 text-center border-gray-300 rounded-md shadow-sm focus:ring-school-blue focus:border-school-blue sm:text-sm" oninput="calculateTotal(<?= $sid ?>)">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center">
                                        <input type="number" step="0.01" min="0" max="60" name="scores[<?= $sid ?>][exam]" value="<?= e($ex) ?>" <?= $readonly ? 'disabled' : '' ?> class="score-input w-20 text-center border-gray-300 rounded-md shadow-sm focus:ring-school-blue focus:border-school-blue sm:text-sm" oninput="calculateTotal(<?= $sid ?>)">
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-center font-bold text-school-navy" id="total_<?= $sid ?>">
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
                    <div class="mt-6 flex justify-between items-center bg-gray-50 p-4 border border-gray-200 rounded-md">
                        <div>
                            <select name="action_type" class="border-gray-300 rounded-md shadow-sm focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="save_draft">Save Draft</option>
                                <option value="submit">Submit to Class Teacher</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">
                            Save Scores
                        </button>
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
}
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
