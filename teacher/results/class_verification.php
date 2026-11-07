<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_auth();

$staffId = current_staff_id();
if (!$staffId) {
    set_flash('error', 'Access denied. You do not have a staff profile.');
    redirect('');
}

$pageTitle = 'Class Verification';
$pdo = get_db_connection();

// Get active session and term
$activeSession = get_active_academic_session();
$activeTerm = $activeSession ? get_active_term($activeSession['id']) : null;

if (!$activeSession || !$activeTerm) {
    set_flash('error', 'No active academic session or term is configured.');
    redirect('teacher/dashboard.php');
}

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);

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
    set_flash('error', 'You are not assigned as a Class Teacher for the current session.');
    redirect('teacher/dashboard.php');
}

if (!$classId) {
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
    set_flash('error', 'You are not the assigned Class Teacher for this class.');
    redirect('teacher/dashboard.php');
}

// Get batch for this class/session/term (do not mutate DB on GET request)
$stmt = $pdo->prepare("SELECT * FROM result_batches WHERE class_id = :cid AND academic_session_id = :sid AND term_id = :tid");
$stmt->execute(['cid' => $classId, 'sid' => $activeSession['id'], 'tid' => $activeTerm['id']]);
$batch = $stmt->fetch();

if (!$batch) {
    $batch = [
        'id' => 0,
        'class_id' => $classId,
        'academic_session_id' => $activeSession['id'],
        'term_id' => $activeTerm['id'],
        'status' => 'DRAFT',
        'term_begins' => null,
        'term_ends' => null,
        'next_term_begins' => null,
        'duration_weeks' => 14,
    ];
}

$batchStatus = $batch['status'];
$batchId = (int)$batch['id'];
$isBatchEditable = in_array($batchStatus, ['DRAFT', 'SUBMITTED', 'REVISION_REQUIRED'], true);

// Get expected subjects (based on class_subjects)
$stmt = $pdo->prepare("
    SELECT s.id, s.name, ss.status, ss.comments, st.first_name, st.last_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.id
    LEFT JOIN subject_submissions ss ON ss.subject_id = s.id AND ss.result_batch_id = :batch_id
    LEFT JOIN subject_teacher_assignments sta ON sta.subject_id = s.id AND sta.class_id = :class_id_1 AND sta.academic_session_id = :sess_id
    LEFT JOIN staff st ON sta.staff_id = st.id
    LEFT JOIN users u ON st.user_id = u.id
    WHERE cs.class_id = :class_id_2
    ORDER BY s.name ASC
");
$stmt->execute([
    'batch_id'   => $batchId,
    'class_id_1' => $classId,
    'class_id_2' => $classId,
    'sess_id'    => $activeSession['id']
]);
$subjects = $stmt->fetchAll();

$allSubmitted = true;
$anySubject = false;
$submittedCount = 0;
foreach ($subjects as $sub) {
    $anySubject = true;
    if (($sub['status'] ?? 'NOT STARTED') === 'SUBMITTED') {
        $submittedCount++;
    } else {
        $allSubmitted = false;
    }
}

$canVerify = ($allSubmitted && $anySubject);

// Fetch active students in this class + their average score & existing evaluation
$stuStmt = $pdo->prepare("
    SELECT st.id, st.admission_number, st.first_name, st.middle_name, st.last_name, st.gender
    FROM students st
    JOIN student_enrollments se ON st.id = se.student_id
    WHERE se.class_id = :cid AND se.academic_session_id = :sid AND se.status = 'ACTIVE'
    ORDER BY st.last_name ASC, st.first_name ASC
");
$stuStmt->execute(['cid' => $classId, 'sid' => $activeSession['id']]);
$classStudents = $stuStmt->fetchAll();

$evalMap = [];
$evalStmt = $pdo->prepare("SELECT * FROM student_result_evaluations WHERE result_batch_id = ?");
$evalStmt->execute([$batchId]);
foreach ($evalStmt->fetchAll() as $ev) {
    $evalMap[(int)$ev['student_id']] = $ev;
}

$avgMap = [];
$avgStmt = $pdo->prepare("
    SELECT student_id, AVG(COALESCE(ca_score, 0) + COALESCE(exam_score, 0)) AS avg_score
    FROM scores
    WHERE result_batch_id = ?
    GROUP BY student_id
");
$avgStmt->execute([$batchId]);
foreach ($avgStmt->fetchAll() as $ar) {
    $avgMap[(int)$ar['student_id']] = (float)$ar['avg_score'];
}

// Fetch live attendance counts from the Class Attendance register (attendance table)
$openedStmt = $pdo->prepare("
    SELECT COUNT(DISTINCT attendance_date)
    FROM attendance
    WHERE class_id = ? AND academic_session_id = ? AND term_id = ?
");
$openedStmt->execute([$classId, $activeSession['id'], $activeTerm['id']]);
$classDaysOpened = (int)$openedStmt->fetchColumn();

$stuAttMap = [];
$stuAttStmt = $pdo->prepare("
    SELECT student_id, status, COUNT(*) AS cnt
    FROM attendance
    WHERE class_id = ? AND academic_session_id = ? AND term_id = ?
    GROUP BY student_id, status
");
$stuAttStmt->execute([$classId, $activeSession['id'], $activeTerm['id']]);
foreach ($stuAttStmt->fetchAll() as $row) {
    $sid = (int)$row['student_id'];
    $stKey = strtoupper(trim($row['status']));
    if (!isset($stuAttMap[$sid])) {
        $stuAttMap[$sid] = ['PRESENT' => 0, 'LATE' => 0, 'EXCUSED' => 0, 'ABSENT' => 0];
    }
    if (isset($stuAttMap[$sid][$stKey])) {
        $stuAttMap[$sid][$stKey] += (int)$row['cnt'];
    }
}

$sdbaCategories = get_sdba_categories();

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
                        <?= e($className) ?> &bull; <?= e($activeTerm['name']) ?> (<?= e($activeSession['name']) ?>)
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
                
                <!-- Main Content -->
                <div class="md:w-3/4 space-y-6">
                    <!-- Subject Submissions Table -->
                    <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
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
                                            <?php if (in_array($subStatus, ['SUBMITTED', 'REVISION_REQUIRED'])): ?>
                                                <a href="<?= url("teacher/results/review_subject.php?class={$classId}&subject={$sub['id']}") ?>" class="text-school-blue hover:text-school-navy">Review</a>
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

                    <!-- Report Card Settings & Student Evaluations (SDBA & Comments) -->
                    <form action="<?= url('actions/results/save_evaluations.php') ?>" method="POST" class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                        <?= csrf_field() ?>
                        <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                        <input type="hidden" name="class_id" value="<?= $classId ?>">
                        <input type="hidden" name="return_url" value="<?= e("teacher/results/class_verification.php?class={$classId}") ?>">

                        <div class="flex flex-wrap justify-between items-center gap-4 border-b border-gray-200 pb-4 mb-6">
                            <div>
                                <h3 class="text-lg font-bold text-school-navy">Report Card Settings, SDBA &amp; Comments</h3>
                                <p class="text-xs text-slate-gray">Configure terminal duration, attendance counts, behavioural ratings (1–5), and class teacher comments for the report sheet.</p>
                            </div>
                            <?php if ($isBatchEditable && !empty($classStudents)): ?>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" onclick="autoFillEvaluations()" class="bg-gray-100 hover:bg-gray-200 text-charcoal border border-gray-300 px-3 py-1.5 rounded text-xs font-semibold transition-colors">
                                    Auto-Fill Empty Ratings &amp; Comments
                                </button>
                                <button type="submit" class="bg-school-blue hover:bg-school-navy text-white px-5 py-1.5 rounded text-xs font-semibold transition-colors shadow-sm">
                                    Save Report Card Evaluations
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Batch Terminal Duration & Attendance -->
                        <fieldset class="mb-6 bg-gray-50 p-4 rounded-md border border-gray-200" <?= !$isBatchEditable ? 'disabled' : '' ?>>
                            <legend class="text-xs font-bold uppercase tracking-wider text-school-navy px-1">Terminal Duration &amp; School Attendance</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-2 items-end">
                                <div>
                                    <label for="term_begins" class="block text-xs font-semibold text-charcoal mb-1">Term Begins</label>
                                    <input type="date" id="term_begins" name="term_begins" value="<?= e($batch['term_begins'] ?: ($activeTerm['start_date'] ?? '')) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label for="term_ends" class="block text-xs font-semibold text-charcoal mb-1">Term Ends</label>
                                    <input type="date" id="term_ends" name="term_ends" value="<?= e($batch['term_ends'] ?: ($activeTerm['end_date'] ?? '')) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label for="next_term_begins" class="block text-xs font-semibold text-charcoal mb-1">Next Term Begins</label>
                                    <input type="date" id="next_term_begins" name="next_term_begins" value="<?= e($batch['next_term_begins'] ?? '') ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label for="duration_weeks" class="block text-xs font-semibold text-charcoal mb-1">Duration (Weeks)</label>
                                    <input type="number" id="duration_weeks" name="duration_weeks" min="1" max="52" value="<?= (int)($batch['duration_weeks'] ?: 14) ?>" class="w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                </div>
                                <div class="bg-white border border-gray-200 rounded px-3 py-1.5 text-xs">
                                    <div class="text-[11px] font-semibold text-slate-gray">Times Sch. Opened (Marked)</div>
                                    <div class="flex items-center justify-between mt-0.5">
                                        <span class="font-bold text-school-navy text-sm"><?= $classDaysOpened ?></span>
                                        <a href="<?= url("teacher/attendance/index.php?class={$classId}") ?>" class="text-[11px] text-school-blue hover:underline font-medium">Mark Attendance &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <!-- Student Evaluation Cards -->
                        <?php if (empty($classStudents)): ?>
                            <p class="text-sm text-slate-gray text-center py-4">No active students enrolled in this class.</p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($classStudents as $stu):
                                    $sid = (int)$stu['id'];
                                    $ev = $evalMap[$sid] ?? [];
                                    $stuAvg = $avgMap[$sid] ?? 0.0;
                                    $defaultTeacherComment = get_default_teacher_comment($stuAvg);
                                    $att = $stuAttMap[$sid] ?? ['PRESENT' => 0, 'LATE' => 0, 'EXCUSED' => 0, 'ABSENT' => 0];
                                    $stuPresent = $att['PRESENT'] + $att['LATE'];
                                    $stuAbsent = $att['ABSENT'];
                                ?>
                                <details class="border border-gray-200 rounded-md bg-white group" open>
                                    <summary class="px-4 py-3 bg-gray-50 cursor-pointer flex flex-wrap justify-between items-center gap-2 font-medium text-sm text-charcoal">
                                        <div>
                                            <span class="font-bold text-school-navy"><?= e($stu['last_name'] . ' ' . $stu['first_name'] . ' ' . ($stu['middle_name'] ?? '')) ?></span>
                                            <span class="text-xs text-slate-gray ml-2">(<?= e($stu['admission_number']) ?> &bull; Avg: <?= number_format($stuAvg, 1) ?>%)</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <a href="<?= url("teacher/results/report_preview.php?batch_id={$batchId}&student_id={$sid}") ?>" class="text-xs font-semibold text-school-blue hover:underline" onclick="event.stopPropagation();">
                                                Preview Report Card &rarr;
                                            </a>
                                        </div>
                                    </summary>

                                    <div class="p-4 space-y-4 border-t border-gray-200">
                                        <!-- Marked Attendance Summary & Teacher Comment Row -->
                                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                                            <div class="md:col-span-4 bg-gray-50 border border-gray-200 rounded px-3 py-2 text-xs flex items-center justify-between">
                                                <div>
                                                    <span class="text-slate-gray font-semibold">Marked Attendance:</span>
                                                    <span class="ml-1 font-bold text-school-blue">Present: <?= $stuPresent ?></span>
                                                    <span class="mx-1 text-gray-300">|</span>
                                                    <span class="font-bold text-school-red">Absent: <?= $stuAbsent === 0 ? 'NIL (0)' : $stuAbsent ?></span>
                                                </div>
                                            </div>
                                            <div class="md:col-span-8">
                                                <label for="tc_<?= $sid ?>" class="block text-xs font-semibold text-charcoal mb-1">Class Teacher's Comment</label>
                                                <input type="text" id="tc_<?= $sid ?>" name="evaluations[<?= $sid ?>][class_teacher_comment]" maxlength="500" value="<?= e($ev['class_teacher_comment'] ?? '') ?>" data-default-comment="<?= e($defaultTeacherComment) ?>" <?= !$isBatchEditable ? 'disabled' : '' ?> placeholder="<?= e($defaultTeacherComment) ?>" class="teacher-comment-input w-full border border-gray-300 rounded px-2.5 py-1.5 text-xs">
                                            </div>
                                        </div>

                                        <!-- SDBA Ratings (1 to 5) -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-gray-100">
                                            <?php foreach ($sdbaCategories as $catName => $catFields): ?>
                                                <div class="bg-gray-50 p-3 rounded border border-gray-200">
                                                    <div class="text-[11px] font-bold uppercase tracking-wider text-school-navy mb-2"><?= e($catName) ?> (1–5)</div>
                                                    <div class="space-y-1.5">
                                                        <?php foreach ($catFields as $col => $label):
                                                            $curVal = $ev[$col] ?? '';
                                                            $inputId = "sdba_{$sid}_{$col}";
                                                        ?>
                                                            <div class="flex items-center justify-between gap-2 text-xs">
                                                                <label for="<?= e($inputId) ?>" class="text-[11px] font-medium text-charcoal"><?= e($label) ?></label>
                                                                <select id="<?= e($inputId) ?>" name="evaluations[<?= $sid ?>][<?= e($col) ?>]" <?= !$isBatchEditable ? 'disabled' : '' ?> class="sdba-select border border-gray-300 rounded px-2 py-0.5 text-xs bg-white">
                                                                    <option value="">-</option>
                                                                    <?php for ($r = 5; $r >= 1; $r--): ?>
                                                                        <option value="<?= $r ?>" <?= (string)$curVal === (string)$r ? 'selected' : '' ?>><?= $r ?></option>
                                                                    <?php endfor; ?>
                                                                </select>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </details>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($isBatchEditable): ?>
                            <div class="mt-6 flex justify-end">
                                <button type="submit" class="bg-school-blue hover:bg-school-navy text-white px-6 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                                    Save Report Card Evaluations
                                </button>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </form>
                    
                    <?php if ($isBatchEditable): ?>
                    <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                        <h3 class="text-lg font-bold text-school-navy mb-2">Class Verification</h3>
                        <?php if ($canVerify): ?>
                            <p class="text-sm text-slate-gray mb-4">All expected subjects have been submitted. Ensure you have saved the Report Card Evaluations above before forwarding to the Principal.</p>
                            <form action="<?= url('actions/results/class_verify.php') ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="class_id" value="<?= $classId ?>">
                                <input type="hidden" name="batch_id" value="<?= $batchId ?>">
                                <button type="submit" class="bg-sage text-white px-6 py-2 rounded font-medium text-sm hover:bg-green-700 transition-colors shadow-sm">
                                    Verify Class Results
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="text-sm text-red-600 mb-4 font-medium"><?= $submittedCount ?> of <?= count($subjects) ?> subjects submitted. All subjects must be submitted before class verification.</p>
                            <button type="button" disabled class="bg-gray-300 text-gray-500 px-6 py-2 rounded font-medium text-sm cursor-not-allowed">
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

<script>
function autoFillEvaluations() {
    document.querySelectorAll('.sdba-select').forEach(function (sel) {
        if (sel.value === '') {
            sel.value = '4';
        }
    });
    document.querySelectorAll('.teacher-comment-input').forEach(function (inp) {
        if (inp.value.trim() === '' && inp.dataset.defaultComment) {
            inp.value = inp.dataset.defaultComment;
        }
    });
}
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
