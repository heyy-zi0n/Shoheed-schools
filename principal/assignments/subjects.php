<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_role([ROLE_PRINCIPAL]);

$pageTitle = 'Subject Teacher Assignments';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
if (!$activeSession) {
    die("No active session found.");
}

$classId = filter_input(INPUT_GET, 'class', FILTER_VALIDATE_INT);

// Fetch all classes for the selector
$classes = $pdo->query("SELECT * FROM classes ORDER BY level")->fetchAll();

// Fetch staff eligible to teach subjects
$staff = $pdo->query("
    SELECT s.id, s.first_name, s.last_name 
    FROM staff s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.status = 'ACTIVE' 
      AND s.can_teach_subjects = 1
    ORDER BY s.last_name
")->fetchAll();

$subjects = [];
$assignments = [];

if ($classId) {
    // Fetch subjects for this class
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM subjects s
        JOIN class_subjects cs ON s.id = cs.subject_id
        WHERE cs.class_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$classId]);
    $subjects = $stmt->fetchAll();
    
    // Fetch current assignments for this class in active session
    $stmt = $pdo->prepare("
        SELECT sta.*, st.first_name, st.last_name 
        FROM subject_teacher_assignments sta 
        JOIN staff st ON sta.staff_id = st.id 
        WHERE sta.class_id = ? AND sta.academic_session_id = ?
    ");
    $stmt->execute([$classId, $activeSession['id']]);
    $assignmentsRaw = $stmt->fetchAll();
    
    foreach ($assignmentsRaw as $a) {
        $assignments[$a['subject_id']] = $a;
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
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Subject Teacher Assignments</h1>
                <p class="text-sm text-slate-gray">Assign teachers to subjects per class for the active session (<?= e($activeSession['name']) ?>).</p>
            </div>
            
            <div class="bg-paper p-4 rounded-lg shadow-sm border border-gray-100 mb-6 max-w-sm">
                <form method="GET" action="<?= url('principal/assignments/subjects.php') ?>">
                    <label class="block text-sm font-bold text-slate-gray mb-2">Select Class</label>
                    <div class="flex gap-2">
                        <select name="class" class="flex-1 border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue text-sm">
                            <option value="">-- Choose a class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $classId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">Load</button>
                    </div>
                </form>
            </div>
            
            <?php if ($classId): ?>
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Subject</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Current Subject Teacher</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($subjects as $subject): ?>
                        <?php $current = $assignments[$subject['id']] ?? null; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal">
                                <?= e($subject['name']) ?> (<?= e($subject['code']) ?>)
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                <?php if ($current): ?>
                                    <?= e($current['first_name'] . ' ' . $current['last_name']) ?>
                                <?php else: ?>
                                    <span class="text-school-red font-medium italic">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php if ($current): ?>
                                    <form action="<?= url('actions/assignments/unassign_subject_teacher.php') ?>" method="POST" class="inline-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="class_id" value="<?= $classId ?>">
                                        <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                        <input type="hidden" name="staff_id" value="<?= $current['staff_id'] ?>">
                                        <button type="submit" onclick="return confirm('Are you sure you want to unassign this teacher?');" class="bg-school-red/10 text-school-red px-3 py-1 rounded text-xs font-semibold hover:bg-school-red hover:text-white transition-colors border border-school-red/20">Unassign</button>
                                    </form>
                                <?php else: ?>
                                    <form action="<?= url('actions/assignments/assign_subject_teacher.php') ?>" method="POST" class="flex items-center justify-end gap-2">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="class_id" value="<?= $classId ?>">
                                        <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                                        <select name="staff_id" required class="border border-gray-300 rounded p-1 text-sm focus:ring-school-blue focus:border-school-blue">
                                            <option value="">-- Select Teacher --</option>
                                            <?php foreach ($staff as $st): ?>
                                                <option value="<?= $st['id'] ?>">
                                                    <?= e($st['last_name'] . ', ' . $st['first_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="bg-school-blue text-white px-3 py-1 rounded text-xs hover:bg-school-navy transition-colors">Assign</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-4 text-center text-slate-gray text-sm">No subjects are associated with this class yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
