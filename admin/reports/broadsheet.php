<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_PRINCIPAL]);

$pdo = get_db_connection();

$sessions = get_all_academic_sessions();
$classes = $pdo->query("SELECT * FROM classes ORDER BY level ASC")->fetchAll();

$sessionId = filter_var($_GET['academic_session_id'] ?? null, FILTER_VALIDATE_INT);
$termId = filter_var($_GET['term_id'] ?? null, FILTER_VALIDATE_INT);
$classId = filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT);

$students = [];
$subjects = [];
$scores = [];

$terms = [];
if ($sessionId) {
    $termStmt = $pdo->prepare("SELECT * FROM terms WHERE academic_session_id = ? ORDER BY id ASC");
    $termStmt->execute([$sessionId]);
    $terms = $termStmt->fetchAll();
}

if ($sessionId && $termId && $classId) {
    // Validate session and term match
    $termCheck = $pdo->prepare("SELECT id FROM terms WHERE id = ? AND academic_session_id = ?");
    $termCheck->execute([$termId, $sessionId]);
    if (!$termCheck->fetchColumn()) {
        set_flash('error', 'The selected Term does not belong to the selected Academic Session.');
        redirect('admin/reports/broadsheet.php');
    }

    // 1. Get dynamically assigned subjects for this class
    $subStmt = $pdo->prepare("
        SELECT s.id, s.name, s.code 
        FROM subjects s
        JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = ?
        ORDER BY s.name ASC
    ");
    $subStmt->execute([$classId]);
    $subjects = $subStmt->fetchAll();
    
    // 2. Get students enrolled in this class for this session
    $stuStmt = $pdo->prepare("
        SELECT st.id, st.admission_number, st.first_name, st.middle_name, st.last_name
        FROM students st
        JOIN student_enrollments se ON st.id = se.student_id
        WHERE se.class_id = ? AND se.academic_session_id = ? AND se.status IN ('ACTIVE', 'PROMOTED', 'REPEATED')
        ORDER BY st.last_name ASC, st.first_name ASC
    ");
    $stuStmt->execute([$classId, $sessionId]);
    $students = $stuStmt->fetchAll();
    
    // 3. Get all published scores for these students/subjects
    // "Only include results that meet the authorized visibility/state rules."
    $scoreStmt = $pdo->prepare("
        SELECT sc.student_id, sc.subject_id, sc.total_score
        FROM scores sc
        JOIN result_batches rb ON sc.result_batch_id = rb.id
        WHERE rb.class_id = ? AND rb.academic_session_id = ? AND rb.term_id = ?
          AND rb.status = 'PUBLISHED'
    ");
    $scoreStmt->execute([$classId, $sessionId, $termId]);
    $rawScores = $scoreStmt->fetchAll();
    
    foreach ($rawScores as $row) {
        $scores[$row['student_id']][$row['subject_id']] = $row['total_score'];
    }
}

$pageTitle = 'Broadsheet Report';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden no-print">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            
            <div class="mb-8 flex justify-between items-end">
                <div>
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Broadsheet Report</h1>
                    <p class="text-sm text-slate-gray">Comprehensive class performance overview (Published results only).</p>
                </div>
                <?php if ($sessionId && $termId && $classId && !empty($students)): ?>
                <button onclick="window.print()" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Print Broadsheet
                </button>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 mb-6">
                <form action="<?= url('admin/reports/broadsheet.php') ?>" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Session</label>
                        <select name="academic_session_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Session...</option>
                            <?php foreach ($sessions as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $s['id'] == $sessionId ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Term</label>
                        <select name="term_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Term...</option>
                            <?php foreach ($terms as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $t['id'] == $termId ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-1/4">
                        <label class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-2">Class</label>
                        <select name="class_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue">
                            <option value="">Select Class...</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $classId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="w-full md:w-auto">
                        <button type="submit" class="w-full bg-charcoal text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors">Load Broadsheet</button>
                    </div>
                </form>
            </div>
            
            <?php if ($sessionId && $termId && $classId): ?>
                <?php if (empty($students)): ?>
                    <div class="bg-paper p-8 text-center rounded-lg border border-gray-200">
                        <p class="text-slate-gray">No students enrolled in this class for the selected session.</p>
                    </div>
                <?php elseif (empty($subjects)): ?>
                    <div class="bg-paper p-8 text-center rounded-lg border border-gray-200">
                        <p class="text-slate-gray">No subjects configured for this class.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border-collapse">
                            <thead class="bg-school-navy text-white text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left border border-school-navy/80 sticky left-0 bg-school-navy z-10">Student Name</th>
                                    <th class="px-2 py-3 text-center border border-school-navy/80">Adm No</th>
                                    <?php foreach ($subjects as $subject): ?>
                                        <th class="px-2 py-3 text-center border border-school-navy/80 w-16" title="<?= e($subject['name']) ?>">
                                            <div class="-rotate-90 sm:rotate-0 truncate overflow-hidden max-w-[4rem]" style="writing-mode: vertical-rl; text-orientation: mixed; height: 100px; sm:height: auto; sm:writing-mode: horizontal-tb;"><?= e($subject['code'] ?? substr($subject['name'], 0, 3)) ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="px-3 py-3 text-center border border-school-navy/80 font-bold bg-school-blue">Total</th>
                                    <th class="px-3 py-3 text-center border border-school-navy/80 font-bold bg-school-blue">Average</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 text-sm">
                                <?php 
                                $classTotalAcc = 0;
                                $classCount = 0;
                                $subjectTotals = array_fill_keys(array_column($subjects, 'id'), 0);
                                $subjectCounts = array_fill_keys(array_column($subjects, 'id'), 0);
                                
                                foreach ($students as $student): 
                                    $stuTotal = 0;
                                    $stuSubjCount = 0;
                                ?>
                                    <tr class="hover:bg-ivory transition-colors">
                                        <td class="px-4 py-2 font-medium text-charcoal border border-gray-200 sticky left-0 bg-white">
                                            <?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']) ?>
                                        </td>
                                        <td class="px-2 py-2 text-center text-xs text-slate-gray border border-gray-200">
                                            <?= e($student['admission_number']) ?>
                                        </td>
                                        
                                        <?php foreach ($subjects as $subject): 
                                            $sid = $subject['id'];
                                            $hasScore = isset($scores[$student['id']][$sid]);
                                            $score = $hasScore ? (float)$scores[$student['id']][$sid] : null;
                                            
                                            if ($hasScore) {
                                                $stuTotal += $score;
                                                $stuSubjCount++;
                                                $subjectTotals[$sid] += $score;
                                                $subjectCounts[$sid]++;
                                            }
                                        ?>
                                            <td class="px-2 py-2 text-center border border-gray-200 <?= $hasScore ? ($score < 50 ? 'text-school-red' : 'text-charcoal') : 'text-gray-300' ?>">
                                                <?= $hasScore ? number_format($score, 1) : '-' ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <?php 
                                        $stuAvg = $stuSubjCount > 0 ? $stuTotal / count($subjects) : 0; // Average over ALL class subjects to avoid artificially high averages
                                        if ($stuSubjCount > 0) {
                                            $classTotalAcc += $stuAvg;
                                            $classCount++;
                                        }
                                        ?>
                                        <td class="px-3 py-2 text-center font-bold border border-gray-200 bg-gray-50">
                                            <?= $stuSubjCount > 0 ? number_format($stuTotal, 1) : '-' ?>
                                        </td>
                                        <td class="px-3 py-2 text-center font-bold border border-gray-200 <?= $stuAvg >= 50 ? 'text-sage' : 'text-school-red' ?> bg-gray-50">
                                            <?= $stuSubjCount > 0 ? number_format($stuAvg, 1) . '%' : '-' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-100 font-bold text-sm">
                                <tr>
                                    <td colspan="2" class="px-4 py-3 text-right border border-gray-300 text-charcoal">Class Average</td>
                                    <?php foreach ($subjects as $subject): 
                                        $sid = $subject['id'];
                                        $subjAvg = $subjectCounts[$sid] > 0 ? $subjectTotals[$sid] / $subjectCounts[$sid] : 0;
                                    ?>
                                        <td class="px-2 py-3 text-center border border-gray-300 text-school-navy">
                                            <?= $subjectCounts[$sid] > 0 ? number_format($subjAvg, 1) : '-' ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-3 py-3 border border-gray-300 bg-gray-200"></td>
                                    <td class="px-3 py-3 text-center border border-gray-300 bg-gray-200 text-school-navy">
                                        <?= $classCount > 0 ? number_format($classTotalAcc / $classCount, 1) . '%' : '-' ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>
</div>

<!-- Print-only View -->
<?php if ($sessionId && $termId && $classId && !empty($students) && !empty($subjects)): ?>
<div class="print-only" style="display: none;">
    <div style="text-align: center; margin-bottom: 20px;">
        <h1 style="font-family: serif; font-size: 24px; font-weight: bold; margin: 0;">SHOHEED SCHOOLS</h1>
        <p style="font-size: 10px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; margin: 0 0 10px 0;">God Is Sufficient</p>
        <h2 style="font-size: 18px; margin: 0;">Broadsheet Report</h2>
        <p style="font-size: 14px; color: #555; margin: 5px 0;">
            <?php 
            $sName = current(array_filter($sessions, fn($s) => $s['id'] == $sessionId))['name'] ?? '';
            $tName = current(array_filter($terms, fn($t) => $t['id'] == $termId))['name'] ?? '';
            $cName = current(array_filter($classes, fn($c) => $c['id'] == $classId))['name'] ?? '';
            echo e("$cName &bull; $tName &bull; $sName Session");
            ?>
        </p>
        <p style="font-size: 11px; color: #777;">Printed: <?= date('d M Y, h:i A') ?></p>
    </div>
    
    <table style="width: 100%; border-collapse: collapse; font-size: 10px; text-align: center;">
        <thead>
            <tr>
                <th style="border: 1px solid #000; padding: 4px; text-align: left;">Student Name</th>
                <th style="border: 1px solid #000; padding: 4px;">Adm No</th>
                <?php foreach ($subjects as $subject): ?>
                    <th style="border: 1px solid #000; padding: 4px; width: 30px;">
                        <div style="writing-mode: vertical-rl; text-orientation: mixed; height: 60px;">
                            <?= e($subject['code'] ?? substr($subject['name'], 0, 3)) ?>
                        </div>
                    </th>
                <?php endforeach; ?>
                <th style="border: 1px solid #000; padding: 4px; width: 40px; font-weight: bold;">Total</th>
                <th style="border: 1px solid #000; padding: 4px; width: 40px; font-weight: bold;">Avg</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $classTotalAcc = 0;
            $classCount = 0;
            $subjectTotals = array_fill_keys(array_column($subjects, 'id'), 0);
            $subjectCounts = array_fill_keys(array_column($subjects, 'id'), 0);
            
            foreach ($students as $student): 
                $stuTotal = 0;
                $stuSubjCount = 0;
            ?>
                <tr>
                    <td style="border: 1px solid #000; padding: 4px; text-align: left;">
                        <?= e($student['last_name'] . ', ' . substr($student['first_name'],0,1) . '. ' . substr($student['middle_name'] ?? '',0,1)) ?>
                    </td>
                    <td style="border: 1px solid #000; padding: 4px; color: #555;">
                        <?= e($student['admission_number']) ?>
                    </td>
                    
                    <?php foreach ($subjects as $subject): 
                        $sid = $subject['id'];
                        $hasScore = isset($scores[$student['id']][$sid]);
                        $score = $hasScore ? (float)$scores[$student['id']][$sid] : null;
                        if ($hasScore) {
                            $stuTotal += $score;
                            $stuSubjCount++;
                            $subjectTotals[$sid] += $score;
                            $subjectCounts[$sid]++;
                        }
                    ?>
                        <td style="border: 1px solid #000; padding: 4px;">
                            <?= $hasScore ? number_format($score, 1) : '-' ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <?php 
                    $stuAvg = $stuSubjCount > 0 ? $stuTotal / count($subjects) : 0;
                    if ($stuSubjCount > 0) {
                        $classTotalAcc += $stuAvg;
                        $classCount++;
                    }
                    ?>
                    <td style="border: 1px solid #000; padding: 4px; font-weight: bold; background-color: #eee;">
                        <?= $stuSubjCount > 0 ? number_format($stuTotal, 1) : '-' ?>
                    </td>
                    <td style="border: 1px solid #000; padding: 4px; font-weight: bold; background-color: #eee;">
                        <?= $stuSubjCount > 0 ? number_format($stuAvg, 1) . '%' : '-' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight: bold; background-color: #ddd;">
                <td colspan="2" style="border: 1px solid #000; padding: 4px; text-align: right;">Class Average</td>
                <?php foreach ($subjects as $subject): 
                    $sid = $subject['id'];
                    $subjAvg = $subjectCounts[$sid] > 0 ? $subjectTotals[$sid] / $subjectCounts[$sid] : 0;
                ?>
                    <td style="border: 1px solid #000; padding: 4px;">
                        <?= $subjectCounts[$sid] > 0 ? number_format($subjAvg, 1) : '-' ?>
                    </td>
                <?php endforeach; ?>
                <td style="border: 1px solid #000; padding: 4px;"></td>
                <td style="border: 1px solid #000; padding: 4px;">
                    <?= $classCount > 0 ? number_format($classTotalAcc / $classCount, 1) . '%' : '-' ?>
                </td>
            </tr>
        </tfoot>
    </table>
</div>
<?php endif; ?>

<script>
document.querySelector('select[name="academic_session_id"]').addEventListener('change', function() {
    const sessionId = this.value;
    const termSelect = document.querySelector('select[name="term_id"]');
    
    termSelect.innerHTML = '<option value="">Select Term...</option>';
    
    if (sessionId) {
        fetch('<?= url("actions/academics/get_terms.php") ?>?session_id=' + sessionId)
            .then(res => res.json())
            .then(data => {
                if (data.terms) {
                    data.terms.forEach(term => {
                        const option = document.createElement('option');
                        option.value = term.id;
                        option.textContent = term.name;
                        termSelect.appendChild(option);
                    });
                }
            })
            .catch(err => console.error('Error fetching terms:', err));
    }
});
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
