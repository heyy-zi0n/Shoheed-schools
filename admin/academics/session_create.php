<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Create Academic Session';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-6 flex items-center gap-4">
                <a href="<?= url('admin/academics/sessions.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Sessions</a>
            </div>

            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Create Academic Session</h1>
                <p class="text-sm text-slate-gray">Define a new academic session. Sessions are created as Inactive by default.</p>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 max-w-2xl">
                <form action="<?= url('actions/academics/create_session.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-bold text-slate-gray mb-1" for="name">Session Name</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., 2027/2028"
                               class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        <p class="text-xs text-gray-500 mt-1">Must be unique.</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" required
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" required
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-8">
                        <a href="<?= url('admin/academics/sessions.php') ?>" class="px-4 py-2 border border-gray-300 rounded text-charcoal hover:bg-gray-50 transition-colors">Cancel</a>
                        <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded hover:bg-school-navy transition-colors shadow">Create Session</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
