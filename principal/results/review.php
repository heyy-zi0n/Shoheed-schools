<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_PRINCIPAL, ROLE_ADMIN]);

$batchId = filter_input(INPUT_GET, 'batch_id', FILTER_VALIDATE_INT);
if (!$batchId) {
    redirect('principal/results/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT rb.*, c.name AS class_name, a.name AS session_name, t.name AS term_name, t.start_date AS term_start, t.end_date AS term_end
    FROM result_batches rb
    JOIN classes c ON rb.class_id = c.id
    JOIN academic_sessions a ON rb.academic_session_id = a.id
    JOIN terms t ON rb.term_id = t.id
    WHERE rb.id = ?
");
$stmt->execute([$batchId]);
$batch = $stmt->fetch();

if (!$batch) {
    set_flash('error', 'Result batch not found.');
    redirect('principal/results/index.php');
}

$classId = (int)$batch['class_id'];
$sessionId = (int)$batch['academic_session_id'];
$batchStatus = $batch['status'];
$isEditable = in_array($batchStatus, ['CLASS_TEACHER_VERIFIED', 'PRINCIPAL_APPROVED'], true);

// Fetch enrolled students in this class/session
$stuStmt = $pdo->prepare("
    SELECT st.id, st.admission_number, st.first_name, st.middle_name, st.last_name, st.gender
    FROM students st
    JOIN student_enrollments se ON st.id = se.student_id
    WHERE se.class_id = :cid AND se.academic_session_id = :sid AND se.status IN ('ACTIVE', 'PROMOTED', 'REPEATED')
    ORDER BY st.last_name ASC, st.first_name ASC
");
$stuStmt->execute(['cid' => $classId, 'sid' => $sessionId]);
$students = $stuStmt->fetchAll();

// Fetch existing evaluations
$evalMap = [];
$evalStmt = $pdo->prepare("SELECT * FROM student_result_evaluations WHERE result_batch_id = ?");
$evalStmt->execute([$batchId]);
foreach ($evalStmt->fetchAll() as $ev) {
    $evalMap[(int)$ev['student_id']] = $ev;
}

// Fetch student score averages & subject count
$scoreStats = [];
$scStmt = $pdo->prepare("
    SELECT student_id,
           COUNT(*) AS subj_count,
           SUM(COALESCE(ca_score, 0) + COALESCE(exam_score, 0)) AS total_obtained,
           AVG(COALESCE(ca_score, 0) + COALESCE(exam_score, 0)) AS avg_score
    FROM scores
    WHERE result_batch_id = ?
    GROUP BY student_id
");
$scStmt->execute([$batchId]);
foreach ($scStmt->fetchAll() as $row) {
    $scoreStats[(int)$row['student_id']] = $row;
}

$pageTitle = 'Review Class Results & Principal Comments';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                <div>
                    <a href="<?= url('principal/results/index.php') ?>" class="text-sm text-school-blue hover:underline mb-1 inline-block">&larr; Back to Result Approvals</a>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Review Results &amp; Principal Comments</h1>
                    <p class="text-sm text-slate-gray">
                        <?= e($batch['class_name']) ?> &bull; <?= e($batch['term_name']) ?> (<?= e($batch['session_name']) ?>)
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-gray-200 text-charcoal">
                        Status: <?= e(str_replace('_', ' ', $batchStatus)) ?>
                    </span>
                    <?php if ($batchStatus === 'CLASS_TEACHER_VERIFIED'): ?>
                        <form action="<?= url('actions/results/principal_approve.php') ?>" method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                            <input type="hidden" name="action_type" value="approve">
                            <button type="submit" class="bg-sage hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                                Approve Batch
                            </button>
                        </form>
                    <?php elseif ($batchStatus === 'PRINCIPAL_APPROVED'): ?>
                        <form action="<?= url('actions/results/principal_approve.php') ?>" method="POST" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                            <input type="hidden" name="action_type" value="publish">
                            <button type="submit" class="bg-school-blue hover:bg-school-navy text-white px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                                Publish Results
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <form action="<?= url('actions/results/save_evaluations.php') ?>" method="POST" class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                <input type="hidden" name="return_url" value="<?= e("principal/results/review.php?batch_id={$batchId}") ?>">

                <div class="flex flex-wrap justify-between items-center gap-4 border-b border-gray-200 pb-4 mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-school-navy">Terminal Duration &amp; Student Principal Comments</h3>
                        <p class="text-xs text-slate-gray">Review student performance, enter Principal comments, and preview individual report cards.</p>
                    </div>
                    <?php if ($isEditable && !empty($students)): ?>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" onclick="autoFillPrincipalComments()" class="bg-gray-100 hover:bg-gray-200 text-charcoal border border-gray-300 px-3 py-1.5 rounded text-xs font-semibold transition-colors">
                            Auto-Fill Empty Principal Comments
                        </button>
                        <button type="submit" class="bg-school-blue hover:bg-school-navy text-white px-5 py-1.5 rounded text-xs font-semibold transition-colors shadow-sm">
                            Save Principal Comments
                        </button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Terminal Duration Metadata -->
                <fieldset class="mb-6 bg-gray-50 p-4 rounded-md border border-gray-200" <?= !$isEditable ? 'disabled' : '' ?>>
                    <legend class="text-xs font-bold uppercase tracking-wider text-school-navy px-1">Terminal Duration</legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-2">
                        <div>
                            <label for="term_begins" class="block text-xs font-semibold text-charcoal mb-1">Term Begins</label>
                            <input type="date" id="term_begins" name="term_begins" value="<?= e($batch['term_begins'] ?: ($batch['term_start'] ?? '')) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label for="term_ends" class="block text-xs font-semibold text-charcoal mb-1">Term Ends</label>
                            <input type="date" id="term_ends" name="term_ends" value="<?= e($batch['term_ends'] ?: ($batch['term_end'] ?? '')) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label for="next_term_begins" class="block text-xs font-semibold text-charcoal mb-1">Next Term Begins</label>
                            <input type="date" id="next_term_begins" name="next_term_begins" value="<?= e($batch['next_term_begins'] ?? '') ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                        </div>
                        <div>
                            <label for="duration_weeks" class="block text-xs font-semibold text-charcoal mb-1">Duration (Weeks)</label>
                            <input type="number" id="duration_weeks" name="duration_weeks" min="1" max="52" value="<?= (int)($batch['duration_weeks'] ?: 14) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                        </div>
                    </div>
                </fieldset>

                <!-- Students Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                        <thead class="bg-school-navy text-white text-xs uppercase tracking-wider">
                            <tr>
                                <th class="px-4 py-3 text-left">Student</th>
                                <th class="px-3 py-3 text-center">Subjects</th>
                                <th class="px-3 py-3 text-center">Total / %</th>
                                <th class="px-3 py-3 text-left">Tuition Clearance</th>
                                <th class="px-4 py-3 text-left">Class Teacher's Comment</th>
                                <th class="px-4 py-3 text-left">Principal's Comment</th>
                                <th class="px-3 py-3 text-right">Report Card</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 text-xs">
                            <?php foreach ($students as $stu):
                                $sid = (int)$stu['id'];
                                $ev = $evalMap[$sid] ?? [];
                                $stStat = $scoreStats[$sid] ?? ['subj_count' => 0, 'total_obtained' => 0, 'avg_score' => 0];
                                $avg = (float)$stStat['avg_score'];
                                $defaultTeacher = get_default_teacher_comment($avg);
                                $defaultPrincipal = get_default_principal_comment($avg);
                                $clearance = check_student_tuition_result_clearance($pdo, $sid, $sessionId, (int)$batch['term_id']);
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-bold text-charcoal"><?= e($stu['last_name'] . ' ' . $stu['first_name'] . ' ' . ($stu['middle_name'] ?? '')) ?></div>
                                    <div class="text-slate-gray"><?= e($stu['admission_number']) ?></div>
                                </td>
                                <td class="px-3 py-3 text-center font-medium text-charcoal">
                                    <?= (int)$stStat['subj_count'] ?>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <div class="font-bold text-school-navy"><?= number_format((float)$stStat['total_obtained'], 1) ?></div>
                                    <div class="text-school-blue font-semibold"><?= number_format($avg, 1) ?>%</div>
                                </td>
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <?php if ($clearance['has_active_waiver']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-900 border border-amber-300" title="Granted by <?= e($clearance['waiver']['granted_by_role'] ? format_role_name($clearance['waiver']['granted_by_role']) : 'Admin') ?>: <?= e($clearance['waiver']['reason']) ?>">
                                            Waiver Active
                                        </span>
                                        <button type="button" 
                                                onclick="openRevokeModal(<?= $sid ?>, '<?= e(addslashes($stu['last_name'] . ' ' . $stu['first_name'])) ?>')"
                                                class="text-[10px] text-school-red hover:underline block mt-0.5 font-medium">
                                            Revoke Waiver
                                        </button>
                                    <?php elseif ($clearance['is_cleared']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-green-100 text-green-800 border border-green-200">
                                            Cleared (<?= (int)$clearance['current_percentage'] ?>%)
                                        </span>
                                        <span class="text-[10px] text-slate-gray block mt-0.5">≥70% Paid</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-school-red border border-red-200">
                                            Locked (<?= (int)$clearance['current_percentage'] ?>%)
                                        </span>
                                        <button type="button" 
                                                onclick="openGrantModal(<?= $sid ?>, '<?= e(addslashes($stu['last_name'] . ' ' . $stu['first_name'])) ?>', <?= (float)$clearance['current_percentage'] ?>, <?= (float)$clearance['prior_tuition_debt'] ?>)"
                                                class="text-[10px] text-school-blue hover:text-school-navy hover:underline block mt-0.5 font-medium">
                                            Grant Waiver
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-charcoal bg-gray-50 border border-gray-200 rounded px-2.5 py-1.5">
                                        <?= e(($ev['class_teacher_comment'] ?? '') !== '' ? $ev['class_teacher_comment'] : $defaultTeacher) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <label for="pc_<?= $sid ?>" class="sr-only">Principal Comment</label>
                                    <input type="text" id="pc_<?= $sid ?>" name="evaluations[<?= $sid ?>][principal_comment]" maxlength="500" value="<?= e($ev['principal_comment'] ?? '') ?>" data-default-comment="<?= e($defaultPrincipal) ?>" placeholder="<?= e($defaultPrincipal) ?>" <?= !$isEditable ? 'disabled' : '' ?> class="principal-comment-input w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <a href="<?= url("teacher/results/report_preview.php?batch_id={$batchId}&student_id={$sid}") ?>" class="text-school-blue hover:text-school-navy font-semibold">
                                        Preview / Print
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($isEditable && !empty($students)): ?>
                <div class="mt-6 flex justify-end">
                    <button type="submit" class="bg-school-blue hover:bg-school-navy text-white px-6 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                        Save Principal Comments
                    </button>
                </div>
                <?php endif; ?>
            </form>

            <!-- Grant Waiver Modal -->
            <div id="grant-waiver-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="grant-modal-title">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity" onclick="closeGrantModal()"></div>
                    <div class="inline-block bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md w-full p-6 relative z-10">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <h3 class="text-base font-bold text-school-navy" id="grant-modal-title">Grant Result Clearance Waiver</h3>
                            </div>
                            <button type="button" onclick="closeGrantModal()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>

                        <form action="<?= url('actions/finance/toggle_result_waiver.php') ?>" method="POST" class="mt-4 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" id="grant_student_id" value="">
                            <input type="hidden" name="academic_session_id" value="<?= $sessionId ?>">
                            <input type="hidden" name="term_id" value="<?= (int)$batch['term_id'] ?>">
                            <input type="hidden" name="action" value="grant">
                            <input type="hidden" name="redirect_url" value="<?= e(current_url()) ?>">

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs space-y-1.5">
                                <div class="flex justify-between">
                                    <span class="text-slate-gray">Student:</span>
                                    <span class="font-bold text-charcoal" id="grant_student_name"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-gray">Current Tuition Paid:</span>
                                    <span class="font-bold text-charcoal" id="grant_tuition_progress"></span>
                                </div>
                                <div class="flex justify-between" id="grant_prior_debt_row">
                                    <span class="text-school-red">Prior Term Debt:</span>
                                    <span class="font-bold text-school-red" id="grant_prior_debt"></span>
                                </div>
                            </div>

                            <div>
                                <label for="grant_reason" class="block text-xs font-bold text-slate-gray mb-1">Reason / Management Note *</label>
                                <textarea id="grant_reason" name="reason" rows="3" required class="w-full border border-gray-300 rounded-md p-2.5 text-xs focus:ring-school-blue focus:border-school-blue" placeholder="e.g. Approved on parental plea / promissory note to balance before resumption"></textarea>
                                <p class="text-[11px] text-slate-gray mt-1">This clearance authorization will be permanently logged in the school audit log.</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="button" onclick="closeGrantModal()" class="px-3.5 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-charcoal hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-1.5 bg-amber hover:bg-amber-600 text-white rounded-md text-xs font-semibold transition-colors shadow-sm">
                                    Authorize Result Waiver
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Revoke Waiver Modal -->
            <div id="revoke-waiver-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="revoke-modal-title">
                <div class="flex items-center justify-center min-h-screen p-4 text-center">
                    <div class="fixed inset-0 bg-black/40 backdrop-blur-xs transition-opacity" onclick="closeRevokeModal()"></div>
                    <div class="inline-block bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:max-w-md w-full p-6 relative z-10">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-red-50 text-school-red flex items-center justify-center font-bold">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                </div>
                                <h3 class="text-base font-bold text-school-navy" id="revoke-modal-title">Revoke Clearance Waiver</h3>
                            </div>
                            <button type="button" onclick="closeRevokeModal()" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                        </div>

                        <form action="<?= url('actions/finance/toggle_result_waiver.php') ?>" method="POST" class="mt-4 space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="student_id" id="revoke_student_id" value="">
                            <input type="hidden" name="academic_session_id" value="<?= $sessionId ?>">
                            <input type="hidden" name="term_id" value="<?= (int)$batch['term_id'] ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="redirect_url" value="<?= e(current_url()) ?>">

                            <p class="text-xs sm:text-sm text-charcoal">
                                Are you sure you want to revoke the administrative result clearance for <strong id="revoke_student_name"></strong>?
                            </p>
                            <p class="text-xs text-school-red bg-red-50 p-2.5 rounded border border-red-200">
                                Their Continuous Assessment Report will be immediately locked on the student portal until the 70% tuition requirement is satisfied.
                            </p>

                            <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                                <button type="button" onclick="closeRevokeModal()" class="px-3.5 py-1.5 border border-gray-300 rounded-md text-xs font-medium text-charcoal hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                <button type="submit" class="px-4 py-1.5 bg-school-red hover:bg-red-800 text-white rounded-md text-xs font-semibold transition-colors shadow-sm">
                                    Revoke Clearance
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function autoFillPrincipalComments() {
    document.querySelectorAll('.principal-comment-input').forEach(function (inp) {
        if (inp.value.trim() === '' && inp.dataset.defaultComment) {
            inp.value = inp.dataset.defaultComment;
        }
    });
}

function openGrantModal(studentId, studentName, currentPct, priorDebt) {
    document.getElementById('grant_student_id').value = studentId;
    document.getElementById('grant_student_name').textContent = studentName;
    document.getElementById('grant_tuition_progress').textContent = currentPct.toFixed(1) + '%';
    const priorDebtRow = document.getElementById('grant_prior_debt_row');
    if (priorDebt > 0) {
        priorDebtRow.classList.remove('hidden');
        document.getElementById('grant_prior_debt').textContent = '₦' + priorDebt.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    } else {
        priorDebtRow.classList.add('hidden');
    }
    document.getElementById('grant-waiver-modal').classList.remove('hidden');
}

function closeGrantModal() {
    document.getElementById('grant-waiver-modal').classList.add('hidden');
}

function openRevokeModal(studentId, studentName) {
    document.getElementById('revoke_student_id').value = studentId;
    document.getElementById('revoke_student_name').textContent = studentName;
    document.getElementById('revoke-waiver-modal').classList.remove('hidden');
}

function closeRevokeModal() {
    document.getElementById('revoke-waiver-modal').classList.add('hidden');
}
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
