<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role([ROLE_STUDENT]);

$pageTitle = 'Profile & Security';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <h1 class="text-2xl font-serif font-bold text-school-navy mb-6">Profile & Security</h1>
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>

            <div class="max-w-md bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-bold text-charcoal mb-4">Change Password</h2>
                <form action="<?= url('actions/students/update_password.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-gray mb-1">Current Password</label>
                        <input type="password" name="current_password" required class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-slate-gray mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="8" class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-slate-gray mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="8" class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">
                        Update Password
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
