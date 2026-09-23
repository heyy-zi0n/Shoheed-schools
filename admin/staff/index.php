<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Manage Staff';
$pdo = get_db_connection();

$filterCategory = $_GET['category'] ?? 'ALL';
$filterAccess = $_GET['access'] ?? 'ALL';
$filterVisibility = $_GET['visibility'] ?? 'ALL';

$sql = "SELECT s.*, u.username, u.email, u.status as user_status, r.name as role_name 
        FROM staff s 
        LEFT JOIN users u ON s.user_id = u.id 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE 1=1";
$params = [];

if ($filterCategory === 'TEACHING') {
    $sql .= " AND s.staff_category = 'TEACHING'";
} elseif ($filterCategory === 'NON_TEACHING') {
    $sql .= " AND s.staff_category = 'NON_TEACHING'";
}

if ($filterAccess === 'PORTAL') {
    $sql .= " AND s.user_id IS NOT NULL";
} elseif ($filterAccess === 'DIRECTORY_ONLY') {
    $sql .= " AND s.user_id IS NULL";
}

if ($filterVisibility === 'PUBLIC') {
    $sql .= " AND s.show_in_directory = 1";
} elseif ($filterVisibility === 'HIDDEN') {
    $sql .= " AND s.show_in_directory = 0";
}

$sql .= " ORDER BY s.display_order ASC, s.last_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staffMembers = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Staff Directory</h1>
                    <p class="text-sm text-slate-gray">Manage teachers and administrative staff.</p>
                </div>
                <div>
                    <a href="<?= url('admin/staff/create.php') ?>" class="bg-school-blue text-white px-4 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">+ Add Staff</a>
                </div>
            </div>
            
            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100 mb-6">
                <form action="<?= url('admin/staff/index.php') ?>" method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label for="category" class="block text-xs font-bold text-slate-gray mb-1 uppercase tracking-wider">Category</label>
                        <select name="category" id="category" class="border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue w-40">
                            <option value="ALL" <?= $filterCategory === 'ALL' ? 'selected' : '' ?>>All Staff</option>
                            <option value="TEACHING" <?= $filterCategory === 'TEACHING' ? 'selected' : '' ?>>Teaching Staff</option>
                            <option value="NON_TEACHING" <?= $filterCategory === 'NON_TEACHING' ? 'selected' : '' ?>>Non-Teaching Staff</option>
                        </select>
                    </div>
                    <div>
                        <label for="access" class="block text-xs font-bold text-slate-gray mb-1 uppercase tracking-wider">Access Mode</label>
                        <select name="access" id="access" class="border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue w-40">
                            <option value="ALL" <?= $filterAccess === 'ALL' ? 'selected' : '' ?>>All Access Types</option>
                            <option value="PORTAL" <?= $filterAccess === 'PORTAL' ? 'selected' : '' ?>>Portal Enabled</option>
                            <option value="DIRECTORY_ONLY" <?= $filterAccess === 'DIRECTORY_ONLY' ? 'selected' : '' ?>>Directory Only</option>
                        </select>
                    </div>
                    <div>
                        <label for="visibility" class="block text-xs font-bold text-slate-gray mb-1 uppercase tracking-wider">Public Directory</label>
                        <select name="visibility" id="visibility" class="border border-gray-300 rounded p-2 text-sm focus:ring-school-blue focus:border-school-blue w-40">
                            <option value="ALL" <?= $filterVisibility === 'ALL' ? 'selected' : '' ?>>All Visibilities</option>
                            <option value="PUBLIC" <?= $filterVisibility === 'PUBLIC' ? 'selected' : '' ?>>Public (Visible)</option>
                            <option value="HIDDEN" <?= $filterVisibility === 'HIDDEN' ? 'selected' : '' ?>>Hidden</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="bg-gray-100 text-charcoal border border-gray-300 px-4 py-2 rounded text-sm hover:bg-gray-200 transition-colors">Filter</button>
                    </div>
                </form>
            </div>

            <div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Name & Designation</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Role / Access</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Category</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($staffMembers as $staff): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if (!empty($staff['profile_image'])): ?>
                                            <div class="flex-shrink-0 h-8 w-8">
                                                <img class="h-8 w-8 rounded-full object-cover border border-gray-200" src="<?= url('media/staff-profile.php?id=' . urlencode((string)$staff['id'])) ?>" alt="">
                                            </div>
                                        <?php else: ?>
                                            <div class="flex-shrink-0 h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 font-bold text-xs">
                                                <?= e(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-charcoal"><?= e($staff['last_name'] . ', ' . $staff['first_name']) ?></div>
                                            <div class="text-xs text-slate-gray"><?= e($staff['public_designation'] ?? 'No designation') ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($staff['user_id']): ?>
                                        <div class="text-sm text-charcoal"><?= e(str_replace('_', ' ', $staff['role_name'] ?? '')) ?></div>
                                        <div class="text-xs text-school-blue font-medium mt-1">Portal Account</div>
                                    <?php else: ?>
                                        <div class="text-sm text-gray-500 italic">No Portal Account</div>
                                        <div class="text-xs text-slate-gray font-medium mt-1">Directory Only</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                                    <?php if ($staff['staff_category'] === 'TEACHING'): ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-4 font-medium rounded bg-blue-100 text-school-blue">Teaching</span>
                                    <?php elseif ($staff['staff_category'] === 'NON_TEACHING'): ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-4 font-medium rounded bg-gray-100 text-gray-700">Non-Teaching</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 inline-flex text-xs leading-4 font-medium rounded bg-yellow-100 text-yellow-800">Unclassified</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col gap-1 items-start">
                                        <?php if (($staff['status'] ?? 'ACTIVE') === 'ACTIVE'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sage text-white">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-dusty-red text-white">Inactive</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($staff['show_in_directory']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded border border-green-200 bg-green-50 text-green-700">Public</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded border border-gray-200 bg-gray-50 text-gray-500">Hidden</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="<?= url('admin/staff/edit.php?id=' . $staff['id']) ?>" class="text-school-blue hover:text-school-navy">Edit</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($staffMembers)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray text-center">No staff found.</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
