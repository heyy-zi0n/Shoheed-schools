<?php
$topbarPdo = get_db_connection();
$topbarUserId = current_user_id();
$topbarUserRole = current_user_role();
$topbarDisplayName = $topbarUserId && $topbarUserRole ? get_user_display_name($topbarUserId, $topbarUserRole) : 'User';
$topbarInitials = get_user_initials($topbarDisplayName);
$topbarInitials = get_user_initials($topbarDisplayName);

$topbarProfileImageUrl = null;
if ($topbarUserId && $topbarUserRole) {
    if (in_array($topbarUserRole, ['ROLE_ADMIN', 'ROLE_PRINCIPAL', 'ROLE_CLASS_TEACHER', 'ROLE_SUBJECT_TEACHER', 'ROLE_BURSAR'])) {
        $stmt = $topbarPdo->prepare("SELECT id, profile_image FROM staff WHERE user_id = ?");
        $stmt->execute([$topbarUserId]);
        $staffRec = $stmt->fetch();
        if ($staffRec && !empty($staffRec['profile_image'])) {
            $topbarProfileImageUrl = url('media/staff-profile.php?id=' . $staffRec['id']);
        }
    } elseif ($topbarUserRole === 'ROLE_STUDENT') {
        $stmt = $topbarPdo->prepare("SELECT profile_image FROM students WHERE user_id = ?");
        $stmt->execute([$topbarUserId]);
        $studentImage = $stmt->fetchColumn();
        if ($studentImage) {
            // Students might not have a secure media endpoint yet, so fallback to direct if that's what exists, or we leave it.
            // Let's assume student images are in storage/profiles/ or uploads/profiles/
            $topbarProfileImageUrl = url($studentImage); 
        }
    }
}

$unreadNotifCount = 0;
if ($topbarUserId) {
    $stmt = $topbarPdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$topbarUserId]);
    $unreadNotifCount = (int)$stmt->fetchColumn();
}

$topbarActiveSession = get_active_academic_session();
$topbarActiveTerm = $topbarPdo->query("SELECT name FROM terms WHERE is_active = 1 LIMIT 1")->fetchColumn();
?>
<header class="bg-paper border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
    <div class="flex items-center space-x-6">
        <h2 class="text-xl font-semibold text-school-navy"><?= e($pageTitle ?? 'Dashboard') ?></h2>
        <?php if ($topbarActiveSession): ?>
        <div class="hidden md:flex items-center space-x-2 text-sm text-slate-gray bg-ivory px-3 py-1 rounded-full border border-gray-200">
            <span class="font-bold">Session:</span> <span><?= e($topbarActiveSession['name']) ?></span>
            <?php if ($topbarActiveTerm): ?>
                <span>&bull;</span>
                <span><?= e($topbarActiveTerm) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="flex items-center space-x-6">
        <!-- Notifications -->
        <a href="<?= url('notifications.php') ?>" class="relative text-slate-gray hover:text-school-blue transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="absolute -top-1 -right-1 bg-school-red text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center">
                <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
            </span>
            <?php endif; ?>
        </a>

        <!-- Profile Dropdown -->
        <div class="relative" id="profile-dropdown-container">
            <button id="profile-dropdown-btn" class="flex items-center space-x-3 focus:outline-none group">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-school-navy leading-tight group-hover:text-school-blue transition-colors"><?= e($topbarDisplayName) ?></p>
                    <p class="text-xs text-slate-gray"><?= e(format_role_name($topbarUserRole)) ?></p>
                </div>
                <?php if ($topbarProfileImageUrl): ?>
                    <img src="<?= $topbarProfileImageUrl ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-transparent group-hover:border-school-blue transition-colors">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-school-gold text-school-navy font-bold flex items-center justify-center border-2 border-transparent group-hover:border-school-blue transition-colors">
                        <?= e($topbarInitials) ?>
                    </div>
                <?php endif; ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-gray group-hover:text-school-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            
            <div id="profile-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-gray-100 py-1 z-50">
                <div class="px-4 py-3 border-b border-gray-100 sm:hidden">
                    <p class="text-sm font-bold text-school-navy"><?= e($topbarDisplayName) ?></p>
                    <p class="text-xs text-slate-gray truncate"><?= e(format_role_name($topbarUserRole)) ?></p>
                </div>
                <a href="<?= e(get_dashboard_url_for_role($topbarUserRole)) ?>" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">My Dashboard</a>
                <a href="<?= url('profile/index.php') ?>" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">Profile & Security</a>
                <a href="<?= url('notifications.php') ?>" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">Notifications</a>
                <div class="border-t border-gray-100 my-1"></div>
                <form action="<?= url('logout.php') ?>" method="POST" class="block w-full text-left">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-school-red hover:bg-red-50 font-medium">Sign Out</button>
                </form>
            </div>
        </div>
    </div>
</header>
