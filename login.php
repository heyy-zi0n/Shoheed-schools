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
<main class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8 bg-ivory min-h-screen">
    <div class="max-w-md w-full space-y-8 bg-paper p-8 sm:p-10 rounded-xl shadow-sm border border-gray-200/80 border-t-4 border-t-school-navy">
        <div class="text-center flex flex-col items-center">
            <div class="h-20 w-20 mb-4 bg-white rounded-full flex items-center justify-center shadow-lg border-2 border-school-navy/15 ring-4 ring-ivory">
                <img class="h-14 w-auto object-contain" width="56" height="56" src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Crest">
            </div>
            <p class="mt-1 text-2xl font-serif font-bold text-school-navy">Shoheed Schools</p>
            <p class="text-[10px] text-slate-gray mt-0.5 uppercase tracking-[0.2em] font-bold">God Is Sufficient</p>
            <h1 class="mt-5 text-lg text-charcoal font-bold uppercase tracking-wider">Portal Login</h1>
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
                    <div class="relative mt-1">
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="appearance-none relative block w-full pl-3 pr-10 py-2 border border-gray-300 placeholder-gray-500 text-charcoal rounded-md focus:outline-none focus-visible:ring-2 focus:ring-school-blue focus:border-school-blue focus:z-10 sm:text-sm">
                        <button type="button" data-password-toggle="password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r-md z-20 transition-colors">
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
</main>
<?php require __DIR__ . '/templates/layouts/app-footer.php'; ?>
