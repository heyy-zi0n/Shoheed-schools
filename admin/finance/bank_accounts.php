<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Manage School Bank Accounts';
$pdo = get_db_connection();

$stmt = $pdo->query("SELECT * FROM school_bank_accounts ORDER BY display_order ASC, bank_name ASC");
$accounts = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">School Bank Accounts</h1>
                    <p class="text-sm text-slate-gray">Manage bank details displayed to families for fee payments.</p>
                </div>
                <button onclick="document.getElementById('add-account-modal').classList.remove('hidden')" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm inline-flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    Add Bank Account
                </button>
            </div>
            
            <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <?php if (empty($accounts)): ?>
                    <div class="p-8 text-center text-slate-gray">
                        <p>No school bank accounts have been configured yet.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Bank Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Account Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Account Number</th>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-school-navy uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-bold text-school-navy uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($accounts as $acc): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal"><?= e($acc['bank_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray"><?= e($acc['account_name']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-school-navy font-bold tracking-wider"><?= e($acc['account_number']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <?php if ($acc['is_active']): ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                            <?php else: ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick="editAccount(<?= htmlspecialchars(json_encode($acc)) ?>)" class="text-school-blue hover:text-school-navy">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="add-account-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50">
    <div class="relative w-full max-w-md mx-auto bg-white rounded-xl shadow-2xl p-8 border border-gray-100">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-school-navy" id="modal-title">Add Bank Account</h3>
            <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form action="<?= url('actions/finance/save_bank_account.php') ?>" method="POST" id="account-form" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="account_id" value="">
            
            <div>
                <label for="bank_name" class="block text-sm font-medium text-charcoal mb-1">Bank Name *</label>
                <input type="text" name="bank_name" id="bank_name" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
            </div>
            
            <div>
                <label for="account_name" class="block text-sm font-medium text-charcoal mb-1">Account Name *</label>
                <input type="text" name="account_name" id="account_name" required class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
            </div>
            
            <div>
                <label for="account_number" class="block text-sm font-medium text-charcoal mb-1">Account Number *</label>
                <input type="text" name="account_number" id="account_number" required pattern="\d+" title="Only digits are allowed" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
            </div>
            
            <div class="flex items-center mt-4">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" id="is_active" value="1" checked class="h-4 w-4 text-school-blue focus:ring-school-blue border-gray-300 rounded">
                <label for="is_active" class="ml-2 block text-sm text-charcoal">Active (Displayed to families)</label>
            </div>
            
            <div class="pt-4 flex justify-end">
                <button type="button" onclick="closeModal()" class="mr-3 bg-white text-slate-gray px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium hover:bg-gray-50 focus:outline-none">Cancel</button>
                <button type="submit" class="bg-school-blue text-white px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium hover:bg-school-navy focus:outline-none">Save Account</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById('add-account-modal').classList.add('hidden');
    document.getElementById('account-form').reset();
    document.getElementById('account_id').value = '';
    document.getElementById('modal-title').textContent = 'Add Bank Account';
    document.getElementById('is_active').checked = true;
}

function editAccount(acc) {
    document.getElementById('account_id').value = acc.id;
    document.getElementById('bank_name').value = acc.bank_name;
    document.getElementById('account_name').value = acc.account_name;
    document.getElementById('account_number').value = acc.account_number;
    document.getElementById('is_active').checked = acc.is_active == 1;
    document.getElementById('modal-title').textContent = 'Edit Bank Account';
    document.getElementById('add-account-modal').classList.remove('hidden');
}
</script>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
