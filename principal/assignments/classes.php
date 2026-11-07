<?php
require_once __DIR__ . '/../../config/bootstrap.php';
require_role([ROLE_PRINCIPAL]);

$pageTitle = 'Class Teacher Assignments';
$pdo = get_db_connection();

$activeSession = get_active_academic_session();
if (!$activeSession) {
    set_flash('error', 'No active academic session is configured.');
    redirect('principal/dashboard.php');
}

// Fetch all classes
$classes = $pdo->query("SELECT * FROM classes ORDER BY level")->fetchAll();

// Fetch staff eligible to be Class Teachers
$staff = $pdo->query("
    SELECT s.id, s.first_name, s.last_name 
    FROM staff s 
    JOIN users u ON s.user_id = u.id 
    WHERE u.status = 'ACTIVE' 
      AND s.can_be_class_teacher = 1
    ORDER BY s.last_name
")->fetchAll();

// Fetch current assignments for active session
$stmt = $pdo->prepare("
    SELECT cta.*, s.first_name, s.last_name 
    FROM class_teacher_assignments cta 
    JOIN staff s ON cta.staff_id = s.id 
    WHERE cta.academic_session_id = ?
");
$stmt->execute([$activeSession['id']]);
$assignmentsRaw = $stmt->fetchAll();

$assignments = [];
foreach ($assignmentsRaw as $a) {
    $assignments[$a['class_id']] = $a;
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
                <h1 class="text-2xl font-serif font-bold text-school-navy">Class Teacher Assignments</h1>
                <p class="text-sm text-slate-gray">Assign teachers to classes for the active session (<?= e($activeSession['name']) ?>).</p>
            </div>
            
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Class</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Current Class Teacher</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($classes as $class): ?>
                        <?php $current = $assignments[$class['id']] ?? null; ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal">
                                <?= e($class['name']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                <?php if ($current): ?>
                                    <?= e($current['first_name'] . ' ' . $current['last_name']) ?>
                                <?php else: ?>
                                    <span class="text-school-red font-medium italic">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <form action="<?= url('actions/assignments/assign_class_teacher.php') ?>" method="POST" class="flex items-center justify-end gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="class_id" value="<?= $class['id'] ?>">
                                    <select name="staff_id" aria-label="Select Class Teacher for <?= e($class['name']) ?>" class="border border-gray-300 rounded p-1 text-sm focus:ring-school-blue focus:border-school-blue">
                                        <option value="">-- Select Teacher --</option>
                                        <?php foreach ($staff as $st): ?>
                                            <?php
                                            $isAssignedElsewhere = false;
                                            foreach ($assignmentsRaw as $a) {
                                                if ($a['staff_id'] == $st['id'] && $a['class_id'] != $class['id']) {
                                                    $isAssignedElsewhere = true;
                                                    break;
                                                }
                                            }
                                            if ($isAssignedElsewhere) continue;
                                            ?>
                                            <option value="<?= $st['id'] ?>" <?= ($current && $current['staff_id'] == $st['id']) ? 'selected' : '' ?>>
                                                <?= e($st['last_name'] . ', ' . $st['first_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="bg-school-blue text-white px-3 py-1 rounded text-xs hover:bg-school-navy transition-colors">Save</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
