<?php
require_once __DIR__ . '/../config/bootstrap.php';

require_role([ROLE_PARENT]);

$pageTitle = 'Children\'s Results';
$pdo = get_db_connection();
$userId = current_user_id();

// Strict guardian lookup
$stmt = $pdo->prepare("SELECT id FROM guardians WHERE user_id = :uid");
$stmt->execute(['uid' => $userId]);
$guardianId = $stmt->fetchColumn();

if (!$guardianId) {
    die("Guardian profile not found.");
}

// Get child ID from URL — then verify ownership
$childId = filter_input(INPUT_GET, 'child', FILTER_VALIDATE_INT);

// Load all linked children for sidebar
$stmt = $pdo->prepare("
    SELECT s.id, s.first_name, s.last_name, s.admission_number
    FROM students s
    JOIN student_guardians sg ON sg.student_id = s.id
    WHERE sg.guardian_id = :gid
");
$stmt->execute(['gid' => $guardianId]);
$children = $stmt->fetchAll();

// If childId given, verify it belongs to this guardian
if ($childId) {
    $owned = false;
    foreach ($children as $c) {
        if ($c['id'] == $childId) { $owned = true; break; }
    }
    if (!$owned) {
        http_response_code(403);
        die("You are not authorized to view results for this student.");
    }
} elseif (!empty($children)) {
    redirect("parent/results.php?child=" . $children[0]['id']);
}

$scores = [];
$termDetails = null;
$availableTerms = [];

if ($childId) {
    // Fetch available PUBLISHED terms for this child
    $stmt = $pdo->prepare("
        SELECT DISTINCT rb.academic_session_id, rb.term_id, rb.class_id,
               a.name as session_name, t.name as term_name, c.name as class_name
        FROM scores s
        JOIN result_batches rb ON s.result_batch_id = rb.id
        JOIN academic_sessions a ON rb.academic_session_id = a.id
        JOIN terms t ON rb.term_id = t.id
        JOIN classes c ON rb.class_id = c.id
        WHERE s.student_id = :sid AND rb.status = 'PUBLISHED'
        ORDER BY a.start_date DESC, t.start_date DESC
    ");
    $stmt->execute(['sid' => $childId]);
    $availableTerms = $stmt->fetchAll();

    $selectedSession = filter_input(INPUT_GET, 'session', FILTER_VALIDATE_INT);
    $selectedTerm    = filter_input(INPUT_GET, 'term',    FILTER_VALIDATE_INT);
    $selectedClass   = filter_input(INPUT_GET, 'class',   FILTER_VALIDATE_INT);

    if ($selectedSession && $selectedTerm && $selectedClass) {
        foreach ($availableTerms as $t) {
            if ($t['academic_session_id']==$selectedSession && $t['term_id']==$selectedTerm) {
                $termDetails = $t; break;
            }
        }
        if ($termDetails) {
            $stmt = $pdo->prepare("
                SELECT sub.name as subject_name, sc.ca_score, sc.exam_score, sc.total_score
                FROM scores sc
                JOIN result_batches rb ON sc.result_batch_id = rb.id
                JOIN subjects sub ON sc.subject_id = sub.id
                WHERE sc.student_id = :sid AND rb.academic_session_id = :sess
                  AND rb.term_id = :term AND rb.class_id = :cls AND rb.status = 'PUBLISHED'
                ORDER BY sub.name
            ");
            $stmt->execute(['sid'=>$childId,'sess'=>$selectedSession,'term'=>$selectedTerm,'cls'=>$selectedClass]);
            $scores = $stmt->fetchAll();
        }
    } elseif (!empty($availableTerms)) {
        $d = $availableTerms[0];
        redirect("parent/results.php?child={$childId}&session={$d['academic_session_id']}&term={$d['term_id']}&class={$d['class_id']}");
    }
}

// Get child name for display
$childName = '';
foreach ($children as $c) {
    if ($c['id'] == $childId) {
        $childName = $c['last_name'] . ' ' . $c['first_name'];
    }
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Children's Results</h1>
                <p class="text-sm text-slate-gray">Published terminal reports for your linked wards.</p>
            </div>

            <div class="flex flex-col md:flex-row gap-8">
                <!-- Child / Term Selector -->
                <div class="md:w-1/4 space-y-4">
                    <!-- Children -->
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-xs font-bold text-school-navy uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">My Children</h3>
                        <ul class="space-y-2">
                            <?php foreach ($children as $c): ?>
                            <li>
                                <a href="<?= url("parent/results.php?child={$c['id']}") ?>"
                                   class="block p-2 rounded text-sm transition-colors <?= $c['id']==$childId ? 'bg-school-blue text-white font-medium' : 'bg-gray-50 text-charcoal hover:bg-gray-100 border border-gray-200' ?>">
                                    <?= e($c['last_name'] . ', ' . $c['first_name']) ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Available Terms -->
                    <?php if (!empty($availableTerms)): ?>
                    <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100">
                        <h3 class="text-xs font-bold text-school-navy uppercase tracking-wider mb-3 border-b border-gray-100 pb-2">Available Results</h3>
                        <ul class="space-y-2">
                            <?php foreach ($availableTerms as $t): 
                                $isActive = $termDetails && $t['term_id']==$termDetails['term_id'] && $t['academic_session_id']==$termDetails['academic_session_id'];
                            ?>
                            <li>
                                <a href="<?= url("parent/results.php?child={$childId}&session={$t['academic_session_id']}&term={$t['term_id']}&class={$t['class_id']}") ?>"
                                   class="block p-2 rounded text-sm transition-colors <?= $isActive ? 'bg-school-blue text-white font-medium' : 'bg-gray-50 text-charcoal hover:bg-gray-100 border border-gray-200' ?>">
                                    <div class="font-bold"><?= e($t['term_name']) ?></div>
                                    <div class="text-xs <?= $isActive ? 'text-blue-200' : 'text-slate-gray' ?>"><?= e($t['session_name']) ?></div>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Result Card -->
                <div class="md:w-3/4">
                    <?php if ($termDetails && !empty($scores)): 
                        $totalScore = array_sum(array_column($scores, 'total_score'));
                        $avg = count($scores) > 0 ? $totalScore / count($scores) : 0;
                    ?>
                    <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100">
                        <div class="text-center border-b-4 border-school-blue pb-6 mb-6">
                            <h2 class="text-2xl font-serif font-bold text-school-navy uppercase">Shoheed Schools</h2>
                            <p class="text-xs text-school-red uppercase tracking-widest font-bold">Terminal Academic Report</p>
                        </div>
                        <div class="flex justify-between text-sm mb-6 bg-gray-50 p-4 rounded">
                            <div>
                                <p><span class="text-slate-gray">Name:</span> <span class="font-bold"><?= e($childName) ?></span></p>
                                <p><span class="text-slate-gray">Class:</span> <span class="font-bold"><?= e($termDetails['class_name']) ?></span></p>
                            </div>
                            <div>
                                <p><span class="text-slate-gray">Term:</span> <span class="font-bold"><?= e($termDetails['term_name']) ?></span></p>
                                <p><span class="text-slate-gray">Session:</span> <span class="font-bold"><?= e($termDetails['session_name']) ?></span></p>
                            </div>
                        </div>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                            <thead class="bg-school-navy text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase">Subject</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase">CA (40)</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase">Exam (60)</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase">Total</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($scores as $sc): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-charcoal"><?= e($sc['subject_name']) ?></td>
                                    <td class="px-4 py-3 text-sm text-center text-slate-gray"><?= $sc['ca_score'] !== null ? number_format((float)$sc['ca_score'],1) : '-' ?></td>
                                    <td class="px-4 py-3 text-sm text-center text-slate-gray"><?= $sc['exam_score'] !== null ? number_format((float)$sc['exam_score'],1) : '-' ?></td>
                                    <td class="px-4 py-3 text-sm text-center font-bold text-school-navy"><?= $sc['total_score'] !== null ? number_format((float)$sc['total_score'],1) : '-' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-sm font-bold uppercase">Average:</td>
                                    <td class="px-4 py-3 text-center font-bold text-lg text-school-blue"><?= number_format($avg,1) ?>%</td>
                                </tr>
                            </tfoot>
                        </table>
                        <div class="mt-6 text-center">
                            <button onclick="window.print()" class="bg-gray-200 hover:bg-gray-300 text-charcoal px-6 py-2 rounded text-sm font-medium">Print Result</button>
                        </div>
                    </div>
                    <?php elseif ($childId): ?>
                    <div class="bg-paper p-12 rounded-lg shadow-sm border border-gray-100 flex flex-col items-center text-center">
                        <p class="text-slate-gray">
                            <?= empty($availableTerms) ? 'No published results available for this student yet.' : 'Select a term from the sidebar to view the report.' ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
