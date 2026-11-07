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
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
