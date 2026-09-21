<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$staffId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$staffId) {
    set_flash('error', 'Invalid staff ID.');
    redirect('admin/staff/index.php');
}

$pdo = get_db_connection();

$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.email, u.status 
    FROM staff s 
    JOIN users u ON s.user_id = u.id 
    WHERE s.id = ?
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash('error', 'Staff member not found.');
    redirect('admin/staff/index.php');
}

$pageTitle = 'Edit Staff';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>
            
            <div class="mb-6 flex items-center gap-4">
                <a href="<?= url('admin/staff/index.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Staff</a>
            </div>

            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Edit Staff Member</h1>
                <p class="text-sm text-slate-gray">Update biological and account details for <?= e($staff['first_name'] . ' ' . $staff['last_name']) ?>.</p>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 max-w-2xl">
                <form action="<?= url('actions/staff/edit.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$staff['id'] ?>">
                    
                    <h3 class="text-lg font-bold text-school-navy mb-4 border-b pb-2">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= e($staff['first_name']) ?>" required
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="middle_name">Middle Name (Optional)</label>
                            <input type="text" id="middle_name" name="middle_name" value="<?= e($staff['middle_name']) ?>"
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?= e($staff['last_name']) ?>" required
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="gender">Gender</label>
                            <select id="gender" name="gender" required
                                    class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                                <option value="Male" <?= ($staff['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($staff['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($staff['phone']) ?>"
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= e($staff['email']) ?>"
                                   class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-school-navy mb-4 border-b pb-2 mt-8">Teaching Eligibility</h3>
                    <p class="text-xs text-slate-gray mb-4">These settings determine whether this staff member can be selected for academic teaching assignments. Actual subjects and classes are assigned by the Principal.</p>
                    <div class="space-y-4 mb-6">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="can_teach_subjects" name="can_teach_subjects" type="checkbox" value="1" <?= $staff['can_teach_subjects'] ? 'checked' : '' ?> class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="can_teach_subjects" class="font-bold text-slate-gray">Can teach subjects</label>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="can_be_class_teacher" name="can_be_class_teacher" type="checkbox" value="1" <?= $staff['can_be_class_teacher'] ? 'checked' : '' ?> class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="can_be_class_teacher" class="font-bold text-slate-gray">Can serve as a Class Teacher</label>
                            </div>
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-school-navy mb-4 border-b pb-2 mt-8">Account Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="username">Username</label>
                            <input type="text" id="username" value="<?= e($staff['username']) ?>" disabled
                                   class="w-full border border-gray-200 bg-gray-50 rounded p-2 text-slate-gray">
                            <p class="text-xs text-gray-500 mt-1">Username cannot be changed.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-gray mb-1" for="status">Account Status</label>
                            <select id="status" name="status" required
                                    class="w-full border border-gray-300 rounded p-2 focus:ring-school-blue focus:border-school-blue">
                                <option value="ACTIVE" <?= $staff['status'] === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                                <option value="INACTIVE" <?= $staff['status'] === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-8">
                        <a href="<?= url('admin/staff/index.php') ?>" class="px-4 py-2 border border-gray-300 rounded text-charcoal hover:bg-gray-50 transition-colors">Cancel</a>
                        <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded hover:bg-school-navy transition-colors shadow">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
