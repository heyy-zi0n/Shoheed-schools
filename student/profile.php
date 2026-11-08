<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_role([ROLE_STUDENT]);

$pageTitle = 'Profile & Security';
require __DIR__ . '/../templates/layouts/app-header.php';
?>
<div class="flex h-screen overflow-hidden">
    <?php require __DIR__ . '/../templates/layouts/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <?php require __DIR__ . '/../templates/layouts/topbar.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-ivory p-6">
            <h1 class="text-2xl font-serif font-bold text-school-navy mb-6">Profile & Security</h1>
            <?php require __DIR__ . '/../templates/components/alert.php'; ?>

            <div class="max-w-md bg-paper rounded-lg shadow-sm border border-gray-100 p-6">
                <h2 class="text-lg font-bold text-charcoal mb-4">Change Password</h2>
                <form action="<?= url('actions/students/update_password.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label for="current_password" class="block text-sm font-semibold text-slate-gray mb-1">Current Password</label>
                        <div class="relative">
                            <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="w-full border border-gray-300 rounded pl-3 pr-10 py-2">
                            <button type="button" data-password-toggle="current_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r">
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
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-semibold text-slate-gray mb-1">New Password</label>
                        <div class="relative">
                            <input id="new_password" type="password" name="new_password" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded pl-3 pr-10 py-2">
                            <button type="button" data-password-toggle="new_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r">
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
                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-semibold text-slate-gray mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input id="confirm_password" type="password" name="confirm_password" required minlength="8" autocomplete="new-password" class="w-full border border-gray-300 rounded pl-3 pr-10 py-2">
                            <button type="button" data-password-toggle="confirm_password" aria-pressed="false" aria-label="Show password" class="absolute inset-y-0 right-0 px-3 flex items-center text-slate-gray hover:text-school-navy focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue rounded-r">
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
                    <button type="submit" class="bg-school-blue text-white px-4 py-2 rounded text-sm hover:bg-school-navy transition-colors">
                        Update Password
                    </button>
                </form>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../templates/layouts/app-footer.php'; ?>
