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
    SELECT s.*, u.username, u.email, u.status AS user_status, r.name as role_name
    FROM staff s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN roles r ON u.role_id = r.id
    WHERE s.id = ?
");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash('error', 'Staff member not found.');
    redirect('admin/staff/index.php');
}

$isPortalEnabled = !empty($staff['user_id']);
$pageTitle = 'Staff Profile';
require __DIR__ . '/../../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../../templates/components/alert.php'; ?>

            <div class="mb-6 flex items-center justify-between">
                <a href="<?= url('admin/staff/index.php') ?>" class="text-slate-gray hover:text-school-blue text-sm">&larr; Back to Staff</a>
                <a href="<?= url('admin/staff/edit.php?id=' . (int)$staffId) ?>" class="bg-school-blue text-white px-4 py-2 rounded text-sm font-medium hover:bg-school-navy transition-colors shadow-sm">Edit Staff</a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile card -->
                <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6 text-center">
                    <?php
                    $imgPath = $staff['profile_image'] ?? null;
                    $imgUrl  = $imgPath ? url('media/staff-profile.php?id=' . (int)$staffId) : null;
                    ?>
                    <div class="w-28 h-28 mx-auto rounded-full overflow-hidden border-4 border-school-blue/20 mb-4 bg-gray-100 flex items-center justify-center">
                        <?php if ($imgUrl): ?>
                            <img src="<?= e($imgUrl) ?>" alt="<?= e($staff['first_name'] . ' ' . $staff['last_name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-3xl font-bold text-slate-gray"><?= e(substr($staff['first_name'],0,1) . substr($staff['last_name'],0,1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <h2 class="text-xl font-serif font-bold text-school-navy"><?= e(trim($staff['first_name'] . ' ' . ($staff['middle_name'] ?? '') . ' ' . $staff['last_name'])) ?></h2>
                    <p class="text-sm text-slate-gray mt-1"><?= e(!empty($staff['role_name']) ? str_replace('_', ' ', $staff['role_name']) : ($staff['public_designation'] ?: 'Directory Only Staff')) ?></p>
                    <?php if ($isPortalEnabled && !empty($staff['username'])): ?>
                        <p class="text-xs text-gray-500 mt-1">@<?= e($staff['username']) ?></p>
                    <?php else: ?>
                        <p class="text-xs text-amber font-medium mt-1">Directory Only (No Portal Login)</p>
                    <?php endif; ?>

                    <!-- Upload form -->
                    <form action="<?= url('actions/staff/upload_image.php') ?>" method="POST" enctype="multipart/form-data" class="mt-6 text-left">
                        <?= csrf_field() ?>
                        <input type="hidden" name="staff_id" value="<?= (int)$staffId ?>">
                        <label for="profile_image" class="block text-xs font-semibold text-slate-gray mb-2 uppercase tracking-wider">Update Profile Photo</label>
                        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" class="block w-full text-xs text-slate-gray border border-gray-200 rounded mb-2 p-2">
                        <p class="text-xs text-gray-500 mb-3">JPG, PNG, GIF, WebP. Max 2 MB.</p>
                        <button type="submit" class="w-full bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">Upload Photo</button>
                    </form>
                </div>

                <!-- Details -->
                <div class="lg:col-span-2">
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                        <h3 class="text-sm font-bold text-slate-gray uppercase tracking-wider mb-4">Staff Information</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div><dt class="text-slate-gray">Phone</dt><dd class="font-medium text-charcoal"><?= e($staff['phone'] ?: ($staff['public_phone'] ?: '—')) ?></dd></div>
                            <div><dt class="text-slate-gray">Email</dt><dd class="font-medium text-charcoal"><?= e($staff['email'] ?: ($staff['public_email'] ?: '—')) ?></dd></div>
                            <div><dt class="text-slate-gray">Public Designation</dt><dd class="font-medium text-charcoal"><?= e($staff['public_designation'] ?: '—') ?></dd></div>
                            <div><dt class="text-slate-gray">Portal Role</dt><dd class="font-medium text-charcoal"><?= e(!empty($staff['role_name']) ? str_replace('_', ' ', $staff['role_name']) : 'Directory Only') ?></dd></div>
                            <div><dt class="text-slate-gray">Employment Status</dt>
                                <dd>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= ($staff['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= e($staff['status'] ?? 'ACTIVE') ?>
                                    </span>
                                </dd>
                            </div>
                            <?php if ($isPortalEnabled): ?>
                            <div><dt class="text-slate-gray">Portal Account Status</dt>
                                <dd>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold <?= ($staff['user_status'] ?? '') === 'ACTIVE' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= e($staff['user_status'] ?? 'INACTIVE') ?>
                                    </span>
                                </dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
