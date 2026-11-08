<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Our Staff';

$pdo = get_db_connection();

$searchQuery = trim($_GET['search'] ?? '');
$categoryFilter = $_GET['category'] ?? '';

// Validate category filter
if (!in_array($categoryFilter, ['TEACHING', 'NON_TEACHING'])) {
    $categoryFilter = '';
}

// Build base query
$whereClauses = ["show_in_directory = 1", "status = 'ACTIVE'"];
$params = [];

if ($searchQuery !== '') {
    $whereClauses[] = "(first_name LIKE ? OR last_name LIKE ? OR middle_name LIKE ? OR public_designation LIKE ?)";
    $searchLike = '%' . $searchQuery . '%';
    array_push($params, $searchLike, $searchLike, $searchLike, $searchLike);
}

if ($categoryFilter !== '') {
    $whereClauses[] = "staff_category = ?";
    $params[] = $categoryFilter;
}

$whereSql = implode(' AND ', $whereClauses);

// Fetch all matching staff
$stmt = $pdo->prepare("
    SELECT id, first_name, last_name, profile_image, public_designation, short_bio, qualification, public_email, public_phone, staff_category
    FROM staff 
    WHERE $whereSql
    ORDER BY display_order ASC, last_name ASC
");
$stmt->execute($params);
$allStaff = $stmt->fetchAll();

$teachingStaff = array_filter($allStaff, fn($s) => $s['staff_category'] === 'TEACHING');
$nonTeachingStaff = array_filter($allStaff, fn($s) => $s['staff_category'] === 'NON_TEACHING');

$activePublicNav = 'staff';
require __DIR__ . '/templates/public/header.php';
?>

    <!-- Header Section -->
    <header class="bg-school-navy text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-serif font-bold mb-4">Meet Our Staff</h1>
            <p class="text-lg text-gray-300 max-w-2xl mx-auto">Dedicated professionals committed to discipline, excellence, and the academic growth of every student.</p>
        </div>
    </header>

    <main class="flex-grow py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Search & Filters -->
            <search class="bg-paper p-6 rounded-lg shadow-sm border border-gray-200/80 mb-12 block">
                <form action="<?= url('staff.php') ?>" method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="flex-1">
                        <label for="search" class="sr-only">Search Staff</label>
                        <input type="search" id="search" name="search" value="<?= e($searchQuery) ?>" placeholder="Search by name or designation..." autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full border border-gray-300 rounded-md p-3 text-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue">
                    </div>
                    <div class="w-full md:w-64">
                        <label for="category" class="sr-only">Category</label>
                        <select id="category" name="category" class="w-full border border-gray-300 rounded-md p-3 text-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue">
                            <option value="">All Categories</option>
                            <option value="TEACHING" <?= $categoryFilter === 'TEACHING' ? 'selected' : '' ?>>Teaching Staff</option>
                            <option value="NON_TEACHING" <?= $categoryFilter === 'NON_TEACHING' ? 'selected' : '' ?>>Administrative &amp; Support</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-school-blue text-white px-8 py-3 rounded-md hover:bg-school-navy transition-colors font-semibold text-sm">Filter</button>
                    <?php if ($searchQuery !== '' || $categoryFilter !== ''): ?>
                        <a href="<?= url('staff.php') ?>" class="bg-gray-100 text-charcoal px-6 py-3 rounded-md hover:bg-gray-200 transition-colors font-medium text-sm text-center">Reset</a>
                    <?php endif; ?>
                </form>
            </search>
            
            <?php if (empty($teachingStaff) && empty($nonTeachingStaff)): ?>
                <div class="text-center py-20">
                    <h3 class="text-2xl font-serif text-school-navy mb-2">No Staff Found</h3>
                    <p class="text-slate-gray">There are currently no public staff profiles matching your criteria.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($teachingStaff)): ?>
                <div class="mb-16">
                    <h2 class="text-3xl font-serif font-bold text-school-navy mb-8 border-b-2 border-school-gold inline-block pb-2">Teaching Staff</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <?php foreach ($teachingStaff as $staff): ?>
                            <div class="bg-paper rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                                <div class="aspect-square bg-gray-100 relative">
                                    <?php if (!empty($staff['profile_image'])): ?>
                                        <img src="<?= url('media/staff-profile.php?id=' . urlencode((string)$staff['id'])) ?>" alt="<?= e($staff['first_name'] . ' ' . $staff['last_name']) ?>" loading="lazy" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-school-blue/10 text-school-blue">
                                            <svg class="w-24 h-24 opacity-20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-6">
                                    <h3 class="text-xl font-bold text-school-navy mb-1"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></h3>
                                    <p class="text-school-red text-sm font-semibold mb-3"><?= e($staff['public_designation'] ?? 'Teacher') ?></p>
                                    
                                    <?php if (!empty($staff['qualification'])): ?>
                                        <p class="text-xs text-slate-gray mb-3 flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H15M9 11l3 3L22 4"></path></svg>
                                            <?= e($staff['qualification']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staff['short_bio'])): ?>
                                        <p class="text-sm text-charcoal leading-relaxed mb-4"><?= e($staff['short_bio']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staff['public_email']) || !empty($staff['public_phone'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col gap-2">
                                            <?php if (!empty($staff['public_email'])): ?>
                                                <a href="mailto:<?= e($staff['public_email']) ?>" class="text-xs text-school-blue hover:underline inline-flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                                    <?= e($staff['public_email']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($staff['public_phone'])): ?>
                                                <span class="text-xs text-slate-gray inline-flex items-center gap-1.5 tabular-nums">
                                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                                    <?= e($staff['public_phone']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($nonTeachingStaff)): ?>
                <div>
                    <h2 class="text-3xl font-serif font-bold text-school-navy mb-8 border-b-2 border-school-gold inline-block pb-2">Administrative & Support Staff</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                        <?php foreach ($nonTeachingStaff as $staff): ?>
                            <div class="bg-paper rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                                <div class="aspect-square bg-gray-100 relative">
                                    <?php if (!empty($staff['profile_image'])): ?>
                                        <img src="<?= url('media/staff-profile.php?id=' . urlencode((string)$staff['id'])) ?>" alt="<?= e($staff['first_name'] . ' ' . $staff['last_name']) ?>" loading="lazy" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gray-200 text-gray-400">
                                            <svg class="w-24 h-24 opacity-20" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-6">
                                    <h3 class="text-xl font-bold text-school-navy mb-1"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></h3>
                                    <p class="text-school-blue text-sm font-semibold mb-3"><?= e($staff['public_designation'] ?? 'Staff') ?></p>
                                    
                                    <?php if (!empty($staff['qualification'])): ?>
                                        <p class="text-xs text-slate-gray mb-3">
                                            <?= e($staff['qualification']) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staff['short_bio'])): ?>
                                        <p class="text-sm text-charcoal leading-relaxed mb-4"><?= e($staff['short_bio']) ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($staff['public_email']) || !empty($staff['public_phone'])): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-100 flex flex-col gap-2">
                                            <?php if (!empty($staff['public_email'])): ?>
                                                <a href="mailto:<?= e($staff['public_email']) ?>" class="text-xs text-school-blue hover:underline">
                                                    <?= e($staff['public_email']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($staff['public_phone'])): ?>
                                                <span class="text-xs text-slate-gray">
                                                    <?= e($staff['public_phone']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

<?php require_once __DIR__ . '/templates/public/footer.php'; ?>
