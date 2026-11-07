<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$staffId = current_staff_id();
if (!$staffId) {
    set_flash('error', 'Access denied. You do not have a staff profile.');
    redirect('');
}

$pageTitle = 'Review Subject Results';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

if (!$activeSession || !$activeTerm) {
    set_flash('error', 'No active academic session or term is configured.');
    redirect('teacher/dashboard.php');
}

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);
$subjectId = filter_input(INPUT_GET, 'subject', FILTER_VALIDATE_INT);

if (!$classId || !$subjectId) {
    redirect('teacher/results/class_verification.php');
}

// 1. Verify Class Teacher ownership
if (!staff_can_access_class_teacher_function($staffId, $classId, $activeSession['id'])) {
    set_flash('error', 'You are not authorized to verify this class.');
    redirect('teacher/results/class_verification.php');
}

$classStmt = $pdo->prepare("SELECT name FROM classes WHERE id = ?");
$classStmt->execute([$classId]);
$className = $classStmt->fetchColumn();

$subjectStmt = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
$subjectStmt->execute([$subjectId]);
$subjectName = $subjectStmt->fetchColumn();

// 2. Get batch and subject submission info
$stmt = $pdo->prepare("SELECT * FROM result_batches WHERE class_id = :cid AND academic_session_id = :sid AND term_id = :tid");
$stmt->execute(['cid' => $classId, 'sid' => $activeSession['id'], 'tid' => $activeTerm['id']]);
$batch = $stmt->fetch();
$batchId = $batch ? $batch['id'] : null;
$batchStatus = $batch ? $batch['status'] : 'DRAFT';

$subSub = null;
if ($batchId) {
    $stmt = $pdo->prepare("SELECT * FROM subject_submissions WHERE result_batch_id = ? AND subject_id = ?");
    $stmt->execute([$batchId, $subjectId]);
    $subSub = $stmt->fetch();
}

$subStatus = $subSub ? $subSub['status'] : 'NOT STARTED';
$revisionComments = $subSub ? $subSub['comments'] : null;

// Get subject teacher name
$stStmt = $pdo->prepare("
    SELECT st.first_name, st.last_name
    FROM subject_teacher_assignments sta
    JOIN staff st ON sta.staff_id = st.id
    WHERE sta.subject_id = ? AND sta.class_id = ? AND sta.academic_session_id = ?
");
$stStmt->execute([$subjectId, $classId, $activeSession['id']]);
$subjectTeacher = $stStmt->fetch();
$subjectTeacherName = $subjectTeacher ? $subjectTeacher['last_name'] . ' ' . $subjectTeacher['first_name'] : 'Unassigned';

// 3. Fetch scores
$students = [];
if ($batchId) {
    $stuStmt = $pdo->prepare("
        SELECT st.id, st.admission_number, st.first_name, st.middle_name, st.last_name, sc.ca_score, sc.exam_score, sc.total_score
        FROM students st
        JOIN student_enrollments se ON st.id = se.student_id
        LEFT JOIN scores sc ON st.id = sc.student_id AND sc.result_batch_id = :batch_id AND sc.subject_id = :subject_id
        WHERE se.class_id = :class_id AND se.academic_session_id = :session_id AND se.status = 'ACTIVE'
        ORDER BY st.last_name ASC, st.first_name ASC
    ");
    $stuStmt->execute([
        'batch_id' => $batchId,
        'subject_id' => $subjectId,
        'class_id' => $classId,
        'session_id' => $activeSession['id']
    ]);
    $students = $stuStmt->fetchAll();
}

require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-6 flex justify-between items-center">
                <div>
                    <a href="<?= url("teacher/results/class_verification.php?class={$classId}") ?>" class="text-sm text-slate-gray hover:text-school-navy mb-2 inline-block">&larr; Back to Class Verification</a>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Review Subject Results</h1>
                    <p class="text-sm text-slate-gray">
                        <?= e($className) ?> &bull; <?= e($subjectName) ?> &bull; <?= e($activeTerm['name']) ?> (<?= e($activeSession['name']) ?>)
                    </p>
                </div>
                <div>
                    <?php 
                        $statusColor = 'bg-gray-200 text-gray-800';
                        if ($subStatus === 'SUBMITTED') $statusColor = 'bg-blue-100 text-blue-800';
                        if ($subStatus === 'REVISION_REQUIRED') $statusColor = 'bg-red-100 text-red-800';
                    ?>
                    <span class="px-3 py-1 text-xs font-bold rounded-full <?= $statusColor ?>">
                        Status: <?= e(str_replace('_', ' ', $subStatus)) ?>
                    </span>
                </div>
            </div>

            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mb-6 flex flex-wrap gap-6 items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Subject Teacher</h3>
                    <p class="font-medium text-charcoal"><?= e($subjectTeacherName) ?></p>
                </div>
                
                <?php if ($subStatus === 'SUBMITTED' && in_array($batchStatus, ['DRAFT', 'SUBMITTED', 'REVISION_REQUIRED'])): ?>
                <div>
                    <button onclick="document.getElementById('revisionModal').classList.remove('hidden')" class="bg-school-red/10 text-school-red px-4 py-2 rounded text-sm font-semibold hover:bg-school-red hover:text-white transition-colors border border-school-red/20">
                        Request Revision
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($subStatus === 'REVISION_REQUIRED' && $revisionComments): ?>
                <div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                    <h4 class="text-sm font-bold text-yellow-800 mb-1">Revision Requested</h4>
                    <p class="text-sm text-yellow-700"><?= nl2br(e($revisionComments)) ?></p>
                </div>
            <?php endif; ?>

            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-school-navy text-white">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Student</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">CA / 40</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Exam / 60</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider border-l border-white/20">Total / 100</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($students as $student): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']) ?></div>
                                <div class="text-xs text-slate-gray"><?= e($student['admission_number']) ?></div>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm text-charcoal">
                                <?= $student['ca_score'] !== null ? number_format((float)$student['ca_score'], 1) : '<span class="text-gray-300">-</span>' ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm text-charcoal">
                                <?= $student['exam_score'] !== null ? number_format((float)$student['exam_score'], 1) : '<span class="text-gray-300">-</span>' ?>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-bold text-school-navy border-l border-gray-100 bg-gray-50">
                                <?= $student['total_score'] !== null ? number_format((float)$student['total_score'], 1) : '<span class="text-gray-300">-</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-slate-gray">No students found or no scores recorded yet.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>

<!-- Revision Modal -->
<?php if ($subStatus === 'SUBMITTED' && in_array($batchStatus, ['DRAFT', 'SUBMITTED', 'REVISION_REQUIRED'])): ?>
<div id="revisionModal" role="dialog" aria-modal="true" aria-labelledby="revisionModalTitle" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <form action="<?= url('actions/results/request_revision.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="batch_id" value="<?= $batchId ?>">
            <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
            
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 id="revisionModalTitle" class="text-lg font-bold text-school-navy">Request Revision</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-gray mb-4">Requesting revision for <strong><?= e($subjectName) ?></strong>. This will unlock the scores for the subject teacher to edit.</p>
                <div>
                    <label for="revision_comments" class="block text-sm font-medium text-charcoal mb-1">Reason for Revision</label>
                    <textarea id="revision_comments" name="comments" required rows="3" class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-school-blue focus:border-school-blue sm:text-sm"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="document.getElementById('revisionModal').classList.add('hidden')" class="bg-white border border-gray-300 text-charcoal px-4 py-2 rounded text-sm font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="bg-school-red text-white px-4 py-2 rounded text-sm font-medium hover:bg-red-700">Request Revision</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
