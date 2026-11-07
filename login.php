<?php
require_once __DIR__ . '/config/bootstrap.php';

if (is_logged_in()) {
    $role = current_user_role();
    if ($role === ROLE_ADMIN) redirect('admin/dashboard.php');
    if ($role === ROLE_PRINCIPAL) redirect('principal/dashboard.php');
    if (in_array($role, [ROLE_CLASS_TEACHER, ROLE_SUBJECT_TEACHER], true)) redirect('teacher/dashboard.php');
    if ($role === ROLE_BURSAR) redirect('bursar/dashboard.php');
    if ($role === ROLE_STUDENT) redirect('student/dashboard.php');
    redirect('index.php');
}

$pageTitle = 'Login';
require __DIR__ . '/templates/layouts/app-header.php';
?>
<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-ivory min-h-screen">
    <div class="max-w-md w-full space-y-8 bg-paper p-10 rounded-xl shadow-md border border-gray-100">
        <div class="text-center flex flex-col items-center">
            <div class="h-16 w-16 mb-4 bg-white rounded-full flex items-center justify-center shadow-sm">
                <img class="h-12 w-auto" src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Crest">
            </div>
            <h2 class="mt-2 text-3xl font-serif font-bold text-school-navy">Shoheed Schools</h2>
            <p class="text-[10px] text-slate-gray mt-1 uppercase tracking-[0.2em] font-bold">God Is Sufficient</p>
            <h3 class="mt-6 text-xl text-charcoal font-medium">Portal Login</h3>
        </div>
        
        <?php require __DIR__ . '/templates/components/alert.php'; ?>

        <form class="mt-8 space-y-6" action="<?= url('actions/auth/login.php') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-charcoal">Username / Admission Number</label>
                    <input id="username" name="username" type="text" required autocomplete="username" autocorrect="off" autocapitalize="off" spellcheck="false" class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-charcoal rounded-md focus:outline-none focus-visible:ring-2 focus:ring-school-blue focus:border-school-blue focus:z-10 sm:text-sm mt-1">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-charcoal">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-charcoal rounded-md focus:outline-none focus-visible:ring-2 focus:ring-school-blue focus:border-school-blue focus:z-10 sm:text-sm mt-1">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-school-blue hover:bg-school-navy focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-school-blue transition-colors">
                    Sign in
                </button>
            </div>
            
            <div class="text-center mt-4 text-sm text-slate-gray">
                <a href="<?= url() ?>" class="hover:text-school-blue hover:underline">Return to Website</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/templates/layouts/app-footer.php'; ?>
