<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_BURSAR]);

$pdo = get_db_connection();

$structures = $pdo->query("
    SELECT fs.*, 
           sess.name AS session_name,
           t.name   AS term_name,
           c.name   AS class_name
    FROM fee_structures fs
    JOIN academic_sessions sess ON sess.id = fs.academic_session_id
    JOIN terms t ON t.id = fs.term_id
    JOIN classes c ON c.id = fs.class_id
    WHERE fs.is_active = 1
    ORDER BY sess.start_date DESC, t.start_date DESC, c.level ASC
")->fetchAll();

$pageTitle = 'Generate Bulk Invoices';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Generate Bulk Invoices</h1>
                <p class="text-sm text-slate-gray">Generate invoices for all ACTIVE students enrolled in a specific class for the selected fee structure.</p>
            </div>

            <div class="max-w-2xl bg-paper rounded-lg shadow-sm border border-gray-100 p-8">
                <form action="<?= url('actions/finance/generate_bulk_invoices.php') ?>" method="POST" data-confirm="Generate invoices? This will process all active students matching the structure's session, term, and class. Existing invoices will be skipped.">
                    <?= csrf_field() ?>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-charcoal mb-1">Select Fee Structure *</label>
                        <select name="fee_structure_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                            <option value="">Select a Fee Structure...</option>
                            <?php foreach ($structures as $s): ?>
                                <option value="<?= (int)$s['id'] ?>">
                                    <?= e($s['name']) ?> (<?= e($s['session_name'] . ' - ' . $s['term_name'] . ' - ' . $s['class_name']) ?>) - ₦<?= number_format((float)$s['total_amount'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-2">Only active fee structures are shown. The system will determine eligible students based on their enrollment records.</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-charcoal mb-1">Due Date</label>
                        <input type="date" name="due_date" class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                        <p class="text-xs text-gray-500 mt-1">Optional. Leave blank if no strict due date.</p>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="bg-school-blue text-white px-6 py-2.5 rounded font-bold hover:bg-school-navy transition-colors">Generate Invoices</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
