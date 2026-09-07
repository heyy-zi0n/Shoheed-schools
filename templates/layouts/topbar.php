<header class="bg-paper border-b border-gray-200 px-6 py-4 flex items-center justify-between sticky top-0 z-20 shadow-sm">
    <div class="flex items-center space-x-4">
        <h2 class="text-xl font-semibold text-school-navy"><?= e($pageTitle ?? 'Dashboard') ?></h2>
    </div>
    <div class="flex items-center space-x-4">
        <div class="text-sm text-slate-gray">
            <?= e(current_user_role()) ?>
        </div>
        <form action="<?= url('logout.php') ?>" method="POST" class="inline">
            <?= csrf_field() ?>
            <button type="submit" class="text-sm text-school-red hover:underline font-medium">Logout</button>
        </form>
    </div>
</header>
