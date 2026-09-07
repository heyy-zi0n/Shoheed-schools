<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_CLASS_TEACHER]);

$pageTitle = 'Class Verification';
$pdo = get_db_connection();
$userId = current_user_id();

// Get active session and term
$activeSession = $pdo->query("SELECT * FROM academic_sessions WHERE is_active = 1")->fetch();
$activeTerm = $pdo->query("SELECT * FROM terms WHERE is_active = 1")->fetch();

if (!$activeSession || !$activeTerm) {
    die("No active session or term.");
}

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);

// Get staff id
$stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$staffId = $stmt->fetchColumn();

// Get assigned classes
$stmt = $pdo->prepare("
    SELECT c.id, c.name, c.level
    FROM class_teacher_assignments cta
    JOIN classes c ON cta.class_id = c.id
    WHERE cta.staff_id = :staff_id AND cta.academic_session_id = :session_id
");
$stmt->execute(['staff_id' => $staffId, 'session_id' => $activeSession['id']]);
$assignedClasses = $stmt->fetchAll();

if (empty($assignedClasses)) {
    die("You are not assigned as a Class Teacher for the current session.");
}

if (!$classId) {
    // Redirect to the first assigned class
    redirect('teacher/results/class_verification.php?class=' . $assignedClasses[0]['id']);
}

// Verify this class belongs to the teacher
$isAssigned = false;
$className = '';
foreach ($assignedClasses as $ac) {
    if ($ac['id'] == $classId) {
        $isAssigned = true;
        $className = $ac['name'];
        break;
    }
}

if (!$isAssigned) {
    die("You are not the assigned Class Teacher for this class.");
}

// Get batch status
$stmt = $pdo->prepare("SELECT * FROM result_batches WHERE class_id = :cid AND academic_session_id = :sid AND term_id = :tid");
$stmt->execute(['cid' => $classId, 'sid' => $activeSession['id'], 'tid' => $activeTerm['id']]);
$batch = $stmt->fetch();

$batchStatus = $batch ? $batch['status'] : 'DRAFT';
$batchId = $batch ? $batch['id'] : null;

// Get expected subjects (based on class_subjects)
$stmt = $pdo->prepare("
    SELECT s.id, s.name, ss.status, ss.comments, u.first_name, u.last_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN subject_submissions ss ON ss.subject_id = s.id AND ss.result_batch_id = :batch_id
    LEFT JOIN subject_teacher_assignments sta ON sta.subject_id = s.id AND sta.class_id = :class_id AND sta.academic_session_id = :sess_id
    LEFT JOIN staff st ON sta.staff_id = st.id
    LEFT JOIN users u ON st.user_id = u.id
    WHERE cs.class_id = :class_id
    ORDER BY s.name ASC
");
$stmt->execute([
    'batch_id' => $batchId ?? 0,
    'class_id' => $classId,
    'sess_id' => $activeSession['id']
]);
$subjects = $stmt->fetchAll();

$allSubmitted = true;
$anySubject = false;
foreach ($subjects as $sub) {
    $anySubject = true;
    if ($sub['status'] !== 'SUBMITTED') {
        $allSubmitted = false;
        break;
    }
}

$canVerify = ($allSubmitted && $anySubject && in_array($batchStatus, ['DRAFT', 'SUBMITTED']));

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Class Result Verification</h1>
                    <p class="text-sm text-slate-gray">
                        <?= e($className) ?> &bull; <?= e($activeTerm['name']) ?>
                    </p>
                </div>
                <div>
                    <span class="px-3 py-1 text-xs font-bold rounded-full <?= $batchStatus === 'CLASS_TEACHER_VERIFIED' ? 'bg-sage text-white' : 'bg-gray-200 text-gray-800' ?>">
                        Class Status: <?= e(str_replace('_', ' ', $batchStatus)) ?>
                    </span>
                </div>
            </div>
            
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Class Selector -->
                <div class="md:w-1/4">
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 sticky top-6">
                        <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Assigned Classes</h3>
                        <ul class="space-y-2">
                            <?php foreach ($assignedClasses as $ac): ?>
                                <li>
                                    <a href="<?= url("teacher/results/class_verification.php?class={$ac['id']}") ?>" 
                                       class="block p-3 rounded-md text-sm transition-colors <?= $ac['id'] == $classId ? 'bg-school-blue text-white font-medium' : 'bg-gray-50 text-charcoal hover:bg-gray-100 border border-gray-200' ?>">
                                        <?= e($ac['name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Subjects List -->
                <div class="md:w-3/4">
                    <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mb-6">
                        <h3 class="text-lg font-bold text-school-navy mb-4">Subject Submissions</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Subject</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Teacher</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($subjects as $sub): 
                                        $subStatus = $sub['status'] ?? 'NOT STARTED';
                                        $statusColor = 'bg-gray-100 text-gray-800';
                                        if ($subStatus === 'SUBMITTED') $statusColor = 'bg-blue-100 text-blue-800';
                                        if ($subStatus === 'DRAFT') $statusColor = 'bg-yellow-100 text-yellow-800';
                                        if ($subStatus === 'REVISION_REQUIRED') $statusColor = 'bg-red-100 text-red-800';
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-charcoal">
                                            <?= e($sub['name']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                            <?= $sub['first_name'] ? e($sub['last_name'] . ' ' . $sub['first_name']) : 'Unassigned' ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statusColor ?>">
                                                <?= e(str_replace('_', ' ', $subStatus)) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <?php if ($subStatus === 'SUBMITTED' && in_array($batchStatus, ['DRAFT', 'SUBMITTED'])): ?>
                                                <button onclick="requestRevision(<?= $sub['id'] ?>, '<?= e($sub['name']) ?>')" class="text-red-600 hover:text-red-900">Request Revision</button>
                                            <?php elseif ($subStatus === 'REVISION_REQUIRED'): ?>
                                                <span class="text-xs text-red-500">Awaiting Revision</span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($subjects)): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-slate-gray">No subjects assigned to this class.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (in_array($batchStatus, ['DRAFT', 'SUBMITTED'])): ?>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <h3 class="text-lg font-bold text-school-navy mb-2">Class Verification</h3>
                        <?php if ($canVerify): ?>
                            <p class="text-sm text-slate-gray mb-4">All subjects have been submitted. You can now verify the results for this class and forward them to the Principal.</p>
                            <form action="<?= url('actions/results/class_verify.php') ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="class_id" value="<?= $classId ?>">
                                <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                                <button type="submit" class="bg-sage text-white px-6 py-2 rounded font-medium text-sm hover:bg-green-700 transition-colors shadow-sm">
                                    Verify Class Results
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="text-sm text-red-600 mb-4 font-medium">You cannot verify this class yet. All subjects must be SUBMITTED.</p>
                            <button disabled class="bg-gray-300 text-gray-500 px-6 py-2 rounded font-medium text-sm cursor-not-allowed">
                                Verify Class Results
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </main>
    </div>
</div>

<!-- Revision Modal -->
<div id="revisionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
        <form action="<?= url('actions/results/request_revision.php') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="hidden" name="batch_id" value="<?= $batchId ?>">
            <input type="hidden" name="subject_id" id="revision_subject_id" value="">
            
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-bold text-school-navy">Request Revision</h3>
            </div>
            <div class="p-6">
                <p class="text-sm text-slate-gray mb-4">Requesting revision for <strong id="revision_subject_name"></strong>. This will unlock the scores for the subject teacher to edit.</p>
                <div>
                    <label class="block text-sm font-medium text-charcoal mb-1">Reason for Revision</label>
                    <textarea name="comments" required rows="3" class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-school-blue focus:border-school-blue sm:text-sm"></textarea>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 bg-gray-50 rounded-b-lg">
                <button type="button" onclick="closeRevisionModal()" class="bg-white border border-gray-300 text-charcoal px-4 py-2 rounded text-sm font-medium hover:bg-gray-50">Cancel</button>
                <button type="submit" class="bg-school-red text-white px-4 py-2 rounded text-sm font-medium hover:bg-red-700">Request Revision</button>
            </div>
        </form>
    </div>
</div>

<script>
function requestRevision(subjectId, subjectName) {
    document.getElementById('revision_subject_id').value = subjectId;
    document.getElementById('revision_subject_name').innerText = subjectName;
    document.getElementById('revisionModal').classList.remove('hidden');
}
function closeRevisionModal() {
    document.getElementById('revisionModal').classList.add('hidden');
}
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
