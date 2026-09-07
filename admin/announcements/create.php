<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Create Announcement';
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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">New Announcement</h1>
                    <p class="text-sm text-slate-gray">Create an announcement and broadcast it to specific audiences.</p>
                </div>
                <div>
                    <a href="<?= url('admin/announcements/index.php') ?>" class="text-slate-gray hover:text-school-navy text-sm font-medium transition-colors">&larr; Back to List</a>
                </div>
            </div>
            
            <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 max-w-3xl">
                <form action="<?= url('actions/announcements/save.php') ?>" method="POST" class="space-y-6">
                    <?= csrf_field() ?>
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-charcoal">Title *</label>
                        <input type="text" name="title" id="title" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                    </div>
                    
                    <div>
                        <label for="target_audience" class="block text-sm font-medium text-charcoal">Target Audience *</label>
                        <select id="target_audience" name="target_audience" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                            <option value="ALL">All Users (Staff, Students, Parents)</option>
                            <option value="STAFF">Staff Only</option>
                            <option value="STUDENTS">Students Only</option>
                            <option value="PARENTS">Parents Only</option>
                            <option value="PUBLIC">Public Website</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-gray">Only users matching the selected audience will see this announcement on their dashboard.</p>
                    </div>
                    
                    <div>
                        <label for="content" class="block text-sm font-medium text-charcoal">Content *</label>
                        <textarea id="content" name="content" rows="6" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm"></textarea>
                    </div>
                    
                    <div class="flex items-center">
                        <input id="is_published" name="is_published" type="checkbox" value="1" checked class="h-4 w-4 text-school-blue focus:ring-school-blue border-gray-300 rounded">
                        <label for="is_published" class="ml-2 block text-sm text-charcoal">
                            Publish immediately
                        </label>
                    </div>
                    
                    <div class="pt-5 border-t border-gray-200 mt-8 flex justify-end">
                        <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm inline-flex items-center">
                            Save Announcement
                        </button>
                    </div>
                </form>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
