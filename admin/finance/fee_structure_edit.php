<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN, ROLE_BURSAR]);

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    set_flash('error', 'Invalid Fee Structure ID.');
    redirect('admin/finance/fee_structures.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT * FROM fee_structures WHERE id = ?");
$stmt->execute([$id]);
$structure = $stmt->fetch();

if (!$structure) {
    set_flash('error', 'Fee structure not found.');
    redirect('admin/finance/fee_structures.php');
}

// Check if used
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_invoices WHERE fee_structure_id = ?");
$stmt->execute([$id]);
$isUsed = $stmt->fetchColumn() > 0;

$stmt = $pdo->prepare("SELECT * FROM fee_items WHERE fee_structure_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Determine which items have been billed
$billedItemIds = [];
if (!empty($items)) {
    $itemIds = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT source_fee_item_id FROM invoice_items WHERE source_fee_item_id IN ($placeholders)");
    $stmt->execute($itemIds);
    $billedItemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$sessions = get_all_academic_sessions();
$stmt = $pdo->prepare("SELECT * FROM terms WHERE academic_session_id = ? ORDER BY name ASC");
$stmt->execute([$structure['academic_session_id']]);
$terms = $stmt->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY level ASC")->fetchAll();

$pageTitle = current_user_role() === ROLE_BURSAR ? 'Edit Fee Structure' : 'View Fee Structure';
$isReadOnly = current_user_role() !== ROLE_BURSAR;
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-6 flex items-center gap-4">
                <a href="<?= url('admin/finance/fee_structures.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Structures</a>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-8">
                    <h2 class="text-2xl font-serif font-bold text-school-navy mb-6"><?= $isReadOnly ? 'View Fee Structure' : 'Edit Fee Structure' ?></h2>

                    <?php if ($isUsed): ?>
                        <div class="mb-6 bg-yellow-50 border-l-4 border-school-gold p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-school-gold" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        <strong>Historical Integrity Protection:</strong> This fee structure has generated invoices. The Name, Class, Academic Session, and Term fields are locked to preserve historical accuracy. Existing invoices are never rewritten. Changes to previously billed items apply only to future students/invoices. New fee items can be billed to already-invoiced students by running Generate Invoices again, which creates a separate supplementary invoice.
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="<?= url('actions/finance/fee_structure_edit.php') ?>" method="POST" id="fee-structure-form" data-confirm="Save changes to this fee structure?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Structure Name *</label>
                                <input type="text" name="name" value="<?= e($structure['name']) ?>" required <?= ($isUsed || $isReadOnly) ? 'readonly class="w-full border border-gray-300 rounded p-2 text-sm bg-gray-100"' : 'class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue"' ?>>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Academic Session *</label>
                                <?php if ($isUsed || $isReadOnly): ?>
                                    <?php if (!$isReadOnly): ?><input type="hidden" name="academic_session_id" value="<?= $structure['academic_session_id'] ?>"><?php endif; ?>
                                    <select disabled class="w-full border border-gray-300 rounded p-2 text-sm bg-gray-100">
                                        <?php foreach ($sessions as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $structure['academic_session_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <select name="academic_session_id" id="session-select" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                        <option value="">Select Session...</option>
                                        <?php foreach ($sessions as $s): ?>
                                            <option value="<?= (int)$s['id'] ?>" <?= $s['id'] == $structure['academic_session_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Term *</label>
                                <?php if ($isUsed || $isReadOnly): ?>
                                    <?php if (!$isReadOnly): ?><input type="hidden" name="term_id" value="<?= $structure['term_id'] ?>"><?php endif; ?>
                                    <select disabled class="w-full border border-gray-300 rounded p-2 text-sm bg-gray-100">
                                        <?php foreach ($terms as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>" <?= $t['id'] == $structure['term_id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <select name="term_id" id="term-select" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                        <option value="">Select Term...</option>
                                        <?php foreach ($terms as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>" <?= $t['id'] == $structure['term_id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Applicable Class *</label>
                                <?php if ($isUsed || $isReadOnly): ?>
                                    <?php if (!$isReadOnly): ?><input type="hidden" name="class_id" value="<?= $structure['class_id'] ?>"><?php endif; ?>
                                    <select disabled class="w-full border border-gray-300 rounded p-2 text-sm bg-gray-100">
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $structure['class_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <select name="class_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                        <option value="">Select Class...</option>
                                        <?php foreach ($classes as $c): ?>
                                            <option value="<?= (int)$c['id'] ?>" <?= $c['id'] == $structure['class_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-8">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-serif font-bold text-school-navy">Fee Items</h3>
                                <?php if (!$isReadOnly): ?>
                                <button type="button" id="add-item-btn" class="text-sm bg-gray-100 hover:bg-gray-200 text-charcoal px-3 py-1 rounded font-medium transition-colors">+ Add Item</button>
                                <?php endif; ?>
                            </div>

                            <div id="fee-items-container" class="space-y-3">
                                <?php foreach ($items as $index => $item): ?>
                                <?php
                                    $isBilled = in_array($item['id'], $billedItemIds);
                                    $isInactive = (int)$item['is_active'] === 0;
                                ?>
                                <div class="fee-item-row flex gap-3 items-start <?= $isInactive ? 'opacity-60' : '' ?>">
                                    <input type="hidden" name="items[<?= $index ?>][id]" value="<?= $item['id'] ?>" <?= $isInactive ? 'disabled' : '' ?> class="item-id-input">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <input type="text" name="items[<?= $index ?>][name]" value="<?= e($item['name']) ?>" required <?= $isReadOnly || $isInactive ? 'readonly class="w-full border border-gray-300 rounded p-2 text-sm bg-gray-100"' : 'class="w-full border border-gray-300 rounded p-2 text-sm"' ?> placeholder="Item Name (e.g. Tuition)" <?= $isInactive ? 'disabled' : '' ?>>
                                            <?php if ($isInactive): ?>
                                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded font-bold">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="w-1/3">
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">₦</span>
                                            </div>
                                            <input type="number" name="items[<?= $index ?>][amount]" value="<?= e($item['amount']) ?>" step="0.01" min="0" required <?= $isReadOnly || $isInactive ? 'readonly class="w-full border border-gray-300 rounded p-2 pl-7 text-sm item-amount bg-gray-100"' : 'class="w-full border border-gray-300 rounded p-2 pl-7 text-sm item-amount"' ?> placeholder="0.00" <?= $isInactive ? 'disabled' : '' ?>>
                                        </div>
                                    </div>
                                    <?php if (!$isReadOnly): ?>
                                        <?php if ($isInactive): ?>
                                            <button type="button" class="reactivate-item-btn text-school-blue hover:text-school-navy text-sm px-2 py-2 font-medium">Reactivate</button>
                                        <?php elseif ($isBilled): ?>
                                            <button type="button" class="archive-item-btn text-yellow-600 hover:text-yellow-700 text-sm px-2 py-2 font-medium <?= count($items) > 1 ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= count($items) > 1 ? '' : 'disabled' ?>>Archive</button>
                                        <?php else: ?>
                                            <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 p-2 <?= count($items) > 1 ? '' : 'opacity-50 cursor-not-allowed' ?>" <?= count($items) > 1 ? '' : 'disabled' ?>>&times;</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                                <span class="font-bold text-slate-gray">Total Amount:</span>
                                <span class="text-2xl font-bold text-school-navy">₦<span id="grand-total"><?= number_format((float)$structure['total_amount'], 2) ?></span></span>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <?php if (!$isReadOnly): ?>
                            <button type="submit" class="bg-school-blue text-white px-6 py-2.5 rounded font-bold hover:bg-school-navy transition-colors">Save Changes</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('fee-items-container');
    const addBtn = document.getElementById('add-item-btn');
    const totalDisplay = document.getElementById('grand-total');
    let rowCount = <?= count($items) ?>;

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.item-amount').forEach(input => {
            const val = parseFloat(input.value) || 0;
            total += val;
        });
        totalDisplay.textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function updateRemoveButtons() {
        const activeRows = document.querySelectorAll('.fee-item-row:not(.hidden-archived)');
        const rows = document.querySelectorAll('.fee-item-row');
        rows.forEach(row => {
            const btn = row.querySelector('.remove-item-btn') || row.querySelector('.archive-item-btn');
            if (!btn) return;
            if (activeRows.length > 1) {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }

    container.addEventListener('input', function(e) {
        if (e.target.classList.contains('item-amount')) {
            updateTotal();
        }
    });

    addBtn.addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'fee-item-row flex gap-3 items-start mt-3';
        row.innerHTML = `
            <input type="hidden" name="items[${rowCount}][id]" value="">
            <div class="flex-1">
                <input type="text" name="items[${rowCount}][name]" required class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Item Name">
            </div>
            <div class="w-1/3">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <span class="text-gray-500 sm:text-sm">₦</span>
                    </div>
                    <input type="number" name="items[${rowCount}][amount]" step="0.01" min="0" required class="w-full border border-gray-300 rounded p-2 pl-7 text-sm item-amount" placeholder="0.00">
                </div>
            </div>
            <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 p-2">&times;</button>
        `;
        container.appendChild(row);
        rowCount++;
        updateRemoveButtons();
    });

    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item-btn') || e.target.classList.contains('archive-item-btn')) {
            const rows = document.querySelectorAll('.fee-item-row:not(.hidden-archived)');
            if (rows.length > 1) {
                const row = e.target.closest('.fee-item-row');

                if (e.target.classList.contains('archive-item-btn')) {
                    // For archive, we just disable the inputs so they don't submit, hiding the row.
                    row.classList.add('hidden', 'hidden-archived');
                    row.querySelectorAll('input').forEach(input => input.disabled = true);
                } else {
                    row.remove();
                }

                updateRemoveButtons();
                updateTotal();
            }
        }

        if (e.target.classList.contains('reactivate-item-btn')) {
            const row = e.target.closest('.fee-item-row');
            row.classList.remove('opacity-60');
            row.querySelectorAll('input').forEach(input => {
                input.disabled = false;
                if (input.type === 'text' || input.type === 'number') {
                    input.classList.remove('bg-gray-100');
                    input.removeAttribute('readonly');
                }
            });
            e.target.outerHTML = '<button type="button" class="archive-item-btn text-yellow-600 hover:text-yellow-700 text-sm px-2 py-2 font-medium">Archive</button>';
            const badge = row.querySelector('.text-gray-700');
            if (badge) badge.remove();

            updateRemoveButtons();
            updateTotal();
        }
    });

    const sessionSelect = document.getElementById('session-select');
    const termSelect = document.getElementById('term-select');

    if (sessionSelect && termSelect) {
        sessionSelect.addEventListener('change', function() {
            const sessionId = this.value;
            termSelect.innerHTML = '<option value="">Loading...</option>';

            if (!sessionId) {
                termSelect.innerHTML = '<option value="">Select Session First...</option>';
                return;
            }

            fetch('<?= url('actions/academics/get_terms.php') ?>?session_id=' + sessionId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        termSelect.innerHTML = '<option value="">Error loading terms</option>';
                        return;
                    }

                    termSelect.innerHTML = '<option value="">Select Term...</option>';
                    data.terms.forEach(term => {
                        const option = document.createElement('option');
                        option.value = term.id;
                        option.textContent = term.name;
                        termSelect.appendChild(option);
                    });
                })
                .catch(() => {
                    termSelect.innerHTML = '<option value="">Error loading terms</option>';
                });
        });
    }
});
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
