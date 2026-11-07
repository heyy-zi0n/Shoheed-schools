<?php
$topbarPdo = get_db_connection();
$topbarUserId = current_user_id();
$topbarUserRole = current_user_role();
$topbarDisplayName = $topbarUserId && $topbarUserRole ? get_user_display_name($topbarUserId, $topbarUserRole) : 'User';
$topbarInitials = get_user_initials($topbarDisplayName);

$topbarProfileImageUrl = null;
if ($topbarUserId && $topbarUserRole) {
    if (in_array($topbarUserRole, [ROLE_ADMIN, ROLE_PRINCIPAL, ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER, ROLE_BURSAR], true)) {
        $stmt = $topbarPdo->prepare("SELECT id, profile_image FROM staff WHERE user_id = ?");
        $stmt->execute([$topbarUserId]);
        $staffRec = $stmt->fetch();
        if ($staffRec && !empty($staffRec['profile_image'])) {
            $topbarProfileImageUrl = url('media/staff-profile.php?id=' . (int)$staffRec['id']);
        }
    } elseif ($topbarUserRole === ROLE_STUDENT) {
        $stmt = $topbarPdo->prepare("SELECT profile_image FROM students WHERE user_id = ?");
        $stmt->execute([$topbarUserId]);
        $studentImage = $stmt->fetchColumn();
        if ($studentImage) {
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
$topbarActiveTerm = $topbarActiveSession ? get_active_term($topbarActiveSession['id']) : null;
$profileLinkUrl = ($topbarUserRole === ROLE_STUDENT) ? url('student/profile.php') : url('profile/index.php');
?>
<header class="bg-paper border-b border-gray-200 px-4 sm:px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
    <div class="flex items-center space-x-3 sm:space-x-6 min-w-0">
        <!-- Mobile Sidebar Drawer Toggle -->
        <button type="button" id="sidebar-toggle-btn" aria-label="Toggle navigation menu" aria-controls="portal-sidebar" aria-expanded="false" class="md:hidden p-2 -ml-1 rounded-md text-school-navy hover:bg-ivory focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <h2 class="text-lg sm:text-xl font-semibold text-school-navy truncate"><?= e($pageTitle ?? 'Dashboard') ?></h2>
        <?php if ($topbarActiveSession): ?>
        <div class="hidden md:flex items-center space-x-2 text-sm text-slate-gray bg-ivory px-3 py-1 rounded-full border border-gray-200 flex-shrink-0">
            <span class="font-bold">Session:</span> <span><?= e($topbarActiveSession['name']) ?></span>
            <?php if ($topbarActiveTerm): ?>
                <span aria-hidden="true">&bull;</span>
                <span><?= e($topbarActiveTerm['name']) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="flex items-center space-x-4 sm:space-x-6 flex-shrink-0">
        <!-- Notifications -->
        <a href="<?= url('notifications.php') ?>" aria-label="Notifications<?= $unreadNotifCount > 0 ? ' (' . $unreadNotifCount . ' unread)' : '' ?>" class="relative text-slate-gray hover:text-school-blue transition-colors p-1 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="absolute -top-0.5 -right-0.5 bg-school-red text-white text-[10px] font-bold w-4 h-4 rounded-full flex items-center justify-center" aria-hidden="true">
                <?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?>
            </span>
            <?php endif; ?>
        </a>

        <!-- Profile Dropdown -->
        <div class="relative" id="profile-dropdown-container">
            <button type="button" id="profile-dropdown-btn" aria-haspopup="true" aria-expanded="false" aria-controls="profile-dropdown-menu" class="flex items-center space-x-3 rounded-full p-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue group">
                <div class="text-right hidden sm:block">
                    <p class="text-sm font-bold text-school-navy leading-tight group-hover:text-school-blue transition-colors"><?= e($topbarDisplayName) ?></p>
                    <p class="text-xs text-slate-gray"><?= e(format_role_name($topbarUserRole)) ?></p>
                </div>
                <?php if ($topbarProfileImageUrl): ?>
                    <img src="<?= e($topbarProfileImageUrl) ?>" alt="<?= e($topbarDisplayName) ?>" class="w-10 h-10 rounded-full object-cover border-2 border-transparent group-hover:border-school-blue transition-colors">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-school-gold text-school-navy font-bold flex items-center justify-center border-2 border-transparent group-hover:border-school-blue transition-colors">
                        <?= e($topbarInitials) ?>
                    </div>
                <?php endif; ?>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-gray group-hover:text-school-blue" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
            
            <div id="profile-dropdown-menu" role="menu" aria-labelledby="profile-dropdown-btn" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-md shadow-lg border border-gray-100 py-1 z-50">
                <div class="px-4 py-3 border-b border-gray-100 sm:hidden">
                    <p class="text-sm font-bold text-school-navy"><?= e($topbarDisplayName) ?></p>
                    <p class="text-xs text-slate-gray truncate"><?= e(format_role_name($topbarUserRole)) ?></p>
                </div>
                <a href="<?= e(get_dashboard_url_for_role($topbarUserRole)) ?>" role="menuitem" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">My Dashboard</a>
                <a href="<?= e($profileLinkUrl) ?>" role="menuitem" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">Profile & Security</a>
                <a href="<?= url('notifications.php') ?>" role="menuitem" class="block px-4 py-2 text-sm text-charcoal hover:bg-ivory hover:text-school-blue">Notifications</a>
                <div class="border-t border-gray-100 my-1"></div>
                <form action="<?= url('logout.php') ?>" method="POST" class="block w-full text-left">
                    <?= csrf_field() ?>
                    <button type="submit" role="menuitem" class="w-full text-left px-4 py-2 text-sm text-school-red hover:bg-red-50 font-medium">Sign Out</button>
                </form>
            </div>
        </div>
    </div>
</header>
