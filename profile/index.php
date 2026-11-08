<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_auth();

$pageTitle = 'Profile & Security';
$pdo = get_db_connection();
$userId = current_user_id();
$userRole = current_user_role();
$displayName = get_user_display_name($userId, $userRole);

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$staffId = null;
$phone = null;
$email = $user['email'] ?? null;
$profileImage = null;
$shortBio = null;
$isStaff = false;

// Fetch entity-specific data
if (in_array($userRole, [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR], true)) {
    $stmt = $pdo->prepare("SELECT id, phone, public_email, profile_image, short_bio FROM staff WHERE user_id = ?");
    $stmt->execute([$userId]);
    $entity = $stmt->fetch();
    if ($entity) {
        $staffId = $entity['id'];
        $phone = $entity['phone'] ?? null;
        if (empty($email) && !empty($entity['public_email'])) {
            $email = $entity['public_email'];
        }
        $profileImage = $entity['profile_image'] ?? null;
        $shortBio = $entity['short_bio'] ?? null;
        $isStaff = true;
    }
} elseif ($userRole === ROLE_STUDENT) {
    $stmt = $pdo->prepare("SELECT id, profile_image FROM students WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stuEntity = $stmt->fetch();
    if ($stuEntity && !empty($stuEntity['profile_image'])) {
        $profileImage = $stuEntity['profile_image'];
    }
}

require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>
            
            <div class="mb-8">
                <h1 class="text-2xl font-serif font-bold text-school-navy">Profile & Security</h1>
                <p class="text-sm text-slate-gray">Manage your account information and password.</p>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Profile Info -->
                <div class="lg:col-span-1">
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 text-center border-b border-gray-100 bg-gray-50/50">
                            <?php if (!empty($profileImage)): ?>
                                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-white shadow-sm mb-4">
                                    <?php if ($isStaff && $staffId): ?>
                                        <img src="<?= e(url('media/staff-profile.php?id=' . (int)$staffId)) ?>" alt="<?= e($displayName) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <img src="<?= e(url($profileImage)) ?>" alt="<?= e($displayName) ?>" class="w-full h-full object-cover">
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="w-20 h-20 mx-auto rounded-full bg-school-gold text-school-navy font-bold text-2xl flex items-center justify-center border-4 border-white shadow-sm mb-4">
                                    <?= e(get_user_initials($displayName)) ?>
                                </div>
                            <?php endif; ?>
                            <h2 class="text-lg font-bold text-school-navy"><?= e($displayName) ?></h2>
                            <p class="text-sm text-school-blue font-medium mt-1"><?= e(format_role_name($userRole)) ?></p>
                        </div>
                        <div class="p-6">
                            <dl class="space-y-4">
                                <div>
                                    <dt class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Username</dt>
                                    <dd class="text-sm text-charcoal font-medium"><?= e($user['username'] ?? 'N/A') ?></dd>
                                </div>
                                <?php if ($email): ?>
                                <div>
                                    <dt class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Email</dt>
                                    <dd class="text-sm text-charcoal"><?= e($email) ?></dd>
                                </div>
                                <?php endif; ?>
                                <?php if ($phone): ?>
                                <div>
                                    <dt class="text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Phone</dt>
                                    <dd class="text-sm text-charcoal"><?= e($phone) ?></dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                            <p class="text-xs text-slate-gray italic mt-6 border-t border-gray-100 pt-4">To update your role or core profile details, please contact an Administrator.</p>
                        </div>
                    </div>
                </div>
                
                <!-- Forms -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <?php if (isset($isStaff) && $isStaff): ?>
                    <!-- Profile Update Form (Staff Only) -->
                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Update Profile Information</h3>
                        </div>
                        <div class="p-6">
                            <form action="<?= url('actions/profile/update_info.php') ?>" method="POST" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <div class="space-y-4 max-w-md">
                                    <div>
                                        <label for="profile_image" class="block text-sm font-bold text-charcoal mb-1">Profile Image</label>
                                        <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/webp" class="w-full border-gray-300 rounded-md shadow-sm text-sm">
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-sm font-bold text-charcoal mb-1">Phone Number</label>
                                        <input type="text" name="phone" id="phone" value="<?= e($phone) ?>" class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:border-school-blue focus:ring-school-blue sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="short_bio" class="block text-sm font-bold text-charcoal mb-1">Public Bio (Optional)</label>
                                        <textarea name="short_bio" id="short_bio" rows="3" class="w-full border border-gray-300 rounded-md shadow-sm p-2 focus:border-school-blue focus:ring-school-blue sm:text-sm"><?= e($shortBio ?? '') ?></textarea>
                                    </div>
                                    <div class="pt-4">
                                        <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded shadow hover:bg-school-navy transition-colors font-medium">Update Information</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="bg-paper rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-sm font-bold text-school-navy uppercase tracking-wider">Change Password</h3>
                        </div>
                        <div class="p-6">
                            <form action="<?= url('actions/profile/update_password.php') ?>" method="POST">
                                <?= csrf_field() ?>
                                <div class="space-y-4 max-w-md">
                                    <div>
                                        <label for="current_password" class="block text-sm font-bold text-charcoal mb-1">Current Password</label>
                                        <div class="relative">
                                            <input type="password" name="current_password" id="current_password" required autocomplete="current-password" class="w-full border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 focus:border-school-blue focus:ring-school-blue sm:text-sm">
                                            <button type="button" data-password-toggle="current_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r-md">
                                                <svg data-eye-open class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg data-eye-closed class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="new_password" class="block text-sm font-bold text-charcoal mb-1">New Password</label>
                                        <div class="relative">
                                            <input type="password" name="new_password" id="new_password" required minlength="6" autocomplete="new-password" class="w-full border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 focus:border-school-blue focus:ring-school-blue sm:text-sm">
                                            <button type="button" data-password-toggle="new_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r-md">
                                                <svg data-eye-open class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg data-eye-closed class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-bold text-charcoal mb-1">Confirm New Password</label>
                                        <div class="relative">
                                            <input type="password" name="confirm_password" id="confirm_password" required minlength="6" autocomplete="new-password" class="w-full border border-gray-300 rounded-md shadow-sm pl-3 pr-10 py-2 focus:border-school-blue focus:ring-school-blue sm:text-sm">
                                            <button type="button" data-password-toggle="confirm_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r-md">
                                                <svg data-eye-open class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <svg data-eye-closed class="h-4 w-4 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="pt-4">
                                        <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded shadow hover:bg-school-navy transition-colors font-medium">Update Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
