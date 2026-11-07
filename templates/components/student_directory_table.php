<?php
/**
 * Shared filter bar, keyboard-scrollable table, and pagination for Student Directory.
 *
 * Expected variables:
 * @var string $directoryActionUrl  e.g. url('admin/students/index.php')
 * @var string $studentViewBaseUrl  e.g. 'admin/students/view.php'
 * @var array  $classes             All classes for filter select
 * @var string $search              Current search string
 * @var ?int   $filterClassId       Current class filter
 * @var string $filterStatus        Current status filter
 * @var array  $directoryResult     Output of query_student_directory()
 */
$students   = $directoryResult['students'];
$totalRows  = $directoryResult['total'];
$totalPages = $directoryResult['pages'];
$curPage    = $directoryResult['page'];
?>
<div class="bg-paper p-4 sm:p-6 rounded-lg shadow-sm border border-gray-100 mb-6">
    <form method="GET" action="<?= e($directoryActionUrl) ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">
        <div class="lg:col-span-5">
            <label for="dir_search" class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Search Student</label>
            <input type="search" id="dir_search" name="q" value="<?= e($search) ?>" placeholder="Admission number or name..." autocorrect="off" autocapitalize="off" spellcheck="false" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue">
        </div>
        <div class="lg:col-span-3">
            <label for="dir_class" class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Class</label>
            <select id="dir_class" name="class_id" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue">
                <option value="">All Classes</option>
                <?php foreach ($classes as $cls): ?>
                    <option value="<?= (int)$cls['id'] ?>" <?= $filterClassId === (int)$cls['id'] ? 'selected' : '' ?>><?= e($cls['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lg:col-span-2">
            <label for="dir_status" class="block text-xs font-bold text-slate-gray uppercase tracking-wider mb-1">Status</label>
            <select id="dir_status" name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-school-blue focus:border-school-blue">
                <option value="">All Statuses</option>
                <?php foreach (['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive', 'GRADUATED' => 'Graduated', 'WITHDRAWN' => 'Withdrawn'] as $stKey => $stLabel): ?>
                    <option value="<?= $stKey ?>" <?= strtoupper($filterStatus) === $stKey ? 'selected' : '' ?>><?= $stLabel ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="lg:col-span-2 flex gap-2">
            <button type="submit" class="flex-1 bg-school-navy text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-school-blue transition-colors">Filter</button>
            <?php if ($search !== '' || $filterClassId || $filterStatus !== ''): ?>
                <a href="<?= e($directoryActionUrl) ?>" class="px-3 py-2 border border-gray-300 rounded-md text-sm text-charcoal hover:bg-gray-50 transition-colors">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="bg-paper p-6 rounded-lg shadow-sm border border-gray-100">
    <div class="flex justify-between items-center mb-4 text-xs text-slate-gray">
        <span>Showing <strong><?= count($students) ?></strong> of <strong><?= number_format($totalRows) ?></strong> student<?= $totalRows === 1 ? '' : 's' ?></span>
        <?php if ($totalPages > 1): ?>
            <span>Page <?= $curPage ?> of <?= $totalPages ?></span>
        <?php endif; ?>
    </div>

    <div class="overflow-x-auto" role="region" aria-label="Student directory table" tabindex="0">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Admission No.</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Name</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Gender</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Current Class</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-slate-gray uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-slate-gray uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($students as $student): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-charcoal font-mono">
                        <?= e($student['admission_number']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-8 w-8 bg-gray-200 rounded-full flex items-center justify-center text-gray-600 font-bold text-xs">
                                <?= e(substr((string)$student['first_name'], 0, 1) . substr((string)$student['last_name'], 0, 1)) ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-charcoal"><?= e($student['last_name'] . ', ' . $student['first_name'] . ' ' . ($student['middle_name'] ?? '')) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-gray">
                        <?= e($student['gender']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-charcoal font-medium">
                        <?= e($student['current_class'] ?? 'Unassigned') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php if ($student['status'] === 'ACTIVE'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-sage text-white">Active</span>
                        <?php elseif ($student['status'] === 'GRADUATED'): ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-school-blue text-white">Graduated</span>
                        <?php else: ?>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-dusty-red text-white"><?= e($student['status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="<?= url($studentViewBaseUrl . '?id=' . (int)$student['id']) ?>" class="text-school-blue hover:text-school-navy">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 whitespace-nowrap text-sm text-slate-gray text-center">No students found matching your criteria.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php
        $buildPageUrl = static function (int $targetPage) use ($directoryActionUrl, $search, $filterClassId, $filterStatus): string {
            $q = ['page' => $targetPage];
            if ($search !== '') $q['q'] = $search;
            if ($filterClassId) $q['class_id'] = $filterClassId;
            if ($filterStatus !== '') $q['status'] = $filterStatus;
            return $directoryActionUrl . '?' . http_build_query($q);
        };
        ?>
        <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
            <div class="text-xs text-slate-gray">Page <?= $curPage ?> of <?= $totalPages ?></div>
            <div class="flex space-x-2">
                <?php if ($curPage > 1): ?>
                    <a href="<?= e($buildPageUrl($curPage - 1)) ?>" class="px-3 py-1.5 bg-gray-100 text-charcoal rounded text-xs font-medium hover:bg-gray-200 transition-colors">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($curPage < $totalPages): ?>
                    <a href="<?= e($buildPageUrl($curPage + 1)) ?>" class="px-3 py-1.5 bg-school-blue text-white rounded text-xs font-medium hover:bg-school-navy transition-colors">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
