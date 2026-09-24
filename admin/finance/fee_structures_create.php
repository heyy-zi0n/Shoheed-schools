<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_BURSAR]);

$pdo = get_db_connection();

$sessions = get_all_academic_sessions();
$terms = $pdo->query("SELECT * FROM terms ORDER BY name ASC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes ORDER BY level ASC")->fetchAll();

$pageTitle = 'Create Fee Structure';
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
                    <h2 class="text-2xl font-serif font-bold text-school-navy mb-6">Create Fee Structure</h2>

                    <form action="<?= url('actions/finance/save_fee_structure.php') ?>" method="POST" id="fee-structure-form" data-confirm="Are you sure you want to create this fee structure?">
                        <?= csrf_field() ?>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Structure Name *</label>
                                <input type="text" name="name" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue" placeholder="e.g. JSS1 First Term Fees">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Academic Session *</label>
                                <select name="academic_session_id" id="session-select" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                    <option value="">Select Session...</option>
                                    <?php foreach ($sessions as $s): ?>
                                        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Term *</label>
                                <select name="term_id" id="term-select" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                    <option value="">Select Session First...</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-charcoal mb-1">Applicable Class *</label>
                                <select name="class_id" required class="w-full border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue">
                                    <option value="">Select Class...</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-8">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-serif font-bold text-school-navy">Fee Items</h3>
                                <button type="button" id="add-item-btn" class="text-sm bg-gray-100 hover:bg-gray-200 text-charcoal px-3 py-1 rounded font-medium transition-colors">+ Add Item</button>
                            </div>

                            <div id="fee-items-container" class="space-y-3">
                                <!-- Initial Item Row -->
                                <div class="fee-item-row flex gap-3 items-start">
                                    <div class="flex-1">
                                        <input type="text" name="items[0][name]" required class="w-full border border-gray-300 rounded p-2 text-sm" placeholder="Item Name (e.g. Tuition)">
                                    </div>
                                    <div class="w-1/3">
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">₦</span>
                                            </div>
                                            <input type="number" name="items[0][amount]" step="0.01" min="0" required class="w-full border border-gray-300 rounded p-2 pl-7 text-sm item-amount" placeholder="0.00">
                                        </div>
                                    </div>
                                    <button type="button" class="remove-item-btn text-red-500 hover:text-red-700 p-2 opacity-50 cursor-not-allowed" disabled>&times;</button>
                                </div>
                            </div>

                            <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                                <span class="font-bold text-slate-gray">Total Amount:</span>
                                <span class="text-2xl font-bold text-school-navy">₦<span id="grand-total">0.00</span></span>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="bg-school-blue text-white px-6 py-2.5 rounded font-bold hover:bg-school-navy transition-colors">Save Structure</button>
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
    let rowCount = 1;

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.item-amount').forEach(input => {
            const val = parseFloat(input.value) || 0;
            total += val;
        });
        totalDisplay.textContent = total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.fee-item-row');
        rows.forEach(row => {
            const btn = row.querySelector('.remove-item-btn');
            if (rows.length > 1) {
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
        if (e.target.classList.contains('remove-item-btn')) {
            const rows = document.querySelectorAll('.fee-item-row');
            if (rows.length > 1) {
                e.target.closest('.fee-item-row').remove();
                updateRemoveButtons();
                updateTotal();
            }
        }
    });

    const sessionSelect = document.getElementById('session-select');
    const termSelect = document.getElementById('term-select');

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
});
</script>

<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
