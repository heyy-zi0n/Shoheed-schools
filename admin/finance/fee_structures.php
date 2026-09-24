<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_BURSAR]);

$pdo = get_db_connection();

$structures = $pdo->query("
    SELECT fs.*,
           sess.name AS session_name,
           t.name   AS term_name,
           c.name   AS class_name,
           COALESCE((SELECT SUM(fi.amount) FROM fee_items fi WHERE fi.fee_structure_id = fs.id), 0) AS computed_total,
           (SELECT COUNT(*) FROM student_invoices si WHERE si.fee_structure_id = fs.id) AS invoice_count
    FROM fee_structures fs
    JOIN academic_sessions sess ON sess.id = fs.academic_session_id
    JOIN terms t ON t.id = fs.term_id
    JOIN classes c ON c.id = fs.class_id
    ORDER BY sess.name DESC, t.name, c.level
")->fetchAll();

$pageTitle = 'Fee Structures';
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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Fee Structures</h1>
                    <?php if (current_user_role() === ROLE_BURSAR): ?>
                        <p class="text-sm text-slate-gray">Manage fee structures and associated items.</p>
                    <?php else: ?>
                        <p class="text-sm text-slate-gray">View school fee configurations and historical structures.</p>
                    <?php endif; ?>
                </div>
                <?php if (current_user_role() === ROLE_BURSAR): ?>
                <a href="<?= url('admin/finance/fee_structures_create.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">+ New Structure</a>
                <?php endif; ?>
            </div>

            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Session</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Term</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Class</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Total (₦)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-slate-gray uppercase tracking-wider">Invoices</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($structures as $fs): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-medium text-charcoal"><?= e($fs['name']) ?></td>
                            <td class="px-6 py-4 text-sm text-slate-gray"><?= e($fs['session_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-slate-gray"><?= e($fs['term_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-slate-gray"><?= e($fs['class_name']) ?></td>
                            <td class="px-6 py-4 text-sm text-right font-medium text-charcoal">₦<?= number_format((float)$fs['computed_total'], 2) ?></td>
                            <td class="px-6 py-4 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $fs['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                                    <?= $fs['is_active'] ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center text-sm text-slate-gray"><?= (int)$fs['invoice_count'] ?></td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                <?php if (current_user_role() === ROLE_BURSAR): ?>
                                    <a href="<?= url('admin/finance/fee_structure_edit.php?id=' . $fs['id']) ?>" class="text-school-blue hover:text-school-navy">Edit</a>

                                    <?php if ($fs['invoice_count'] == 0): ?>
                                        <form action="<?= url('actions/finance/fee_structure_delete.php') ?>" method="POST" class="inline" onsubmit="return confirm('Delete this fee structure? This action can only permanently delete structures that have not been used to generate invoices.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $fs['id'] ?>">
                                            <button type="submit" class="text-school-red hover:text-red-800">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="<?= url('actions/finance/fee_structure_toggle_status.php') ?>" method="POST" class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $fs['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $fs['is_active'] ? '0' : '1' ?>">
                                            <?php if ($fs['is_active']): ?>
                                                <button type="submit" class="text-school-gold hover:text-yellow-700" onclick="return confirm('Archive this fee structure? It will no longer be available for new invoices.');">Archive</button>
                                            <?php else: ?>
                                                <button type="submit" class="text-sage hover:text-green-800" onclick="return confirm('Reactivate this fee structure?');">Reactivate</button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="<?= url('admin/finance/fee_structure_edit.php?id=' . $fs['id']) ?>" class="text-school-blue hover:text-school-navy">View</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($structures)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-gray">
                                No fee structures have been configured yet.
                                <?php if (current_user_role() === ROLE_BURSAR): ?>
                                    <br><a href="<?= url('admin/finance/fee_structures_create.php') ?>" class="text-school-blue hover:underline mt-2 inline-block">Add Fee Structure</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
