<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Manage Students';
$pdo = get_db_connection();

$stmt = $pdo->query("
    SELECT s.*, 
           (SELECT c.name FROM student_enrollments se JOIN classes c ON se.class_id = c.id WHERE se.student_id = s.id AND se.status = 'ACTIVE' ORDER BY se.id DESC LIMIT 1) as current_class
    FROM students s 
    ORDER BY s.last_name ASC
");
$students = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Student Directory</h1>
                    <p class="text-sm text-slate-gray">Manage student records and enrollments.</p>
                </div>
                <div>
                    <a href="<?= url('admin/students/create.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">+ Add Student</a>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Admission No.</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Gender</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Current Class</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal">
                                    <?= e($student['admission_number']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 font-bold text-xs">
                                            <?= e(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)) ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . $student['middle_name']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                    <?= e($student['gender']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal font-medium">
                                    <?= e($student['current_class'] ?? 'Unassigned') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($student['status'] === 'ACTIVE'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sage text-white">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-dusty-red text-white"><?= e($student['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="<?= url('admin/students/view.php?id=' . $student['id']) ?>" class="text-school-blue hover:text-school-navy">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray text-center">No students found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
