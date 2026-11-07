<?php
require_once __DIR__ . '/../../config/bootstrap.php';

require_role([ROLE_ADMIN]);

$pageTitle = 'Add Staff';
$pdo = get_db_connection();

// Exclude Student and Parent roles from staff role assignment
$stmt = $pdo->query("SELECT * FROM roles WHERE name NOT IN ('STUDENT', 'PARENT') ORDER BY name");
$roles = $stmt->fetchAll();

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
                    <h1 class="text-2xl font-serif font-bold text-school-navy">Add Staff</h1>
                    <p class="text-sm text-slate-gray">Create a new staff member and user account.</p>
                </div>
                <div>
                    <a href="<?= url('admin/staff/index.php') ?>" class="text-slate-gray hover:text-school-navy text-sm font-medium transition-colors">&larr; Back to Directory</a>
                </div>
            </div>
            
            <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 max-w-3xl">
                <form action="<?= url('actions/staff/create.php') ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <?= csrf_field() ?>
                    
                    <div class="border-b border-gray-200 pb-4 mb-4">
                        <h3 class="text-lg font-medium text-school-navy">Personal Information</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-charcoal">First Name *</label>
                            <input type="text" name="first_name" id="first_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-charcoal">Middle Name (Optional)</label>
                            <input type="text" name="middle_name" id="middle_name" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-charcoal">Last Name *</label>
                            <input type="text" name="last_name" id="last_name" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="gender" class="block text-sm font-medium text-charcoal">Gender *</label>
                            <select name="gender" id="gender" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-charcoal">Phone Number</label>
                            <input type="text" name="phone" id="phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="address" class="block text-sm font-medium text-charcoal">Address</label>
                            <input type="text" name="address" id="address" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                    </div>

                    <div>
                        <label for="profile_image" class="block text-sm font-medium text-charcoal">Profile Photo (Optional)</label>
                        <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-1.5 px-3 text-sm">
                        <p class="mt-1 text-xs text-slate-gray">JPG, PNG, or WebP (max 2 MB).</p>
                    </div>
                    
                    <div class="border-b border-gray-200 pb-4 mb-4 mt-8">
                        <h3 class="text-lg font-medium text-school-navy">System Access</h3>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input id="access_portal" name="portal_access" type="radio" value="PORTAL_ENABLED" checked class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300">
                            <label for="access_portal" class="ml-3 block text-sm font-medium text-charcoal">
                                Portal Enabled (Create User Account)
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input id="access_directory" name="portal_access" type="radio" value="DIRECTORY_ONLY" class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300">
                            <label for="access_directory" class="ml-3 block text-sm font-medium text-charcoal">
                                Directory Only (No Login Account)
                            </label>
                        </div>
                    </div>
                    
                    <div class="border-b border-gray-200 pb-4 mb-4 mt-8">
                        <h3 class="text-lg font-medium text-school-navy">Public Directory Profile</h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="staff_category" class="block text-sm font-medium text-charcoal">Staff Category *</label>
                            <select id="staff_category" name="staff_category" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="">Select category...</option>
                                <option value="TEACHING">Teaching Staff</option>
                                <option value="NON_TEACHING">Non-Teaching Staff</option>
                            </select>
                        </div>
                        <div>
                            <label for="public_designation" class="block text-sm font-medium text-charcoal">Public Designation *</label>
                            <input type="text" name="public_designation" id="public_designation" placeholder="e.g. Mathematics Teacher, School Secretary" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="public_email" class="block text-sm font-medium text-charcoal">Public Email (Optional)</label>
                            <input type="email" name="public_email" id="public_email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="public_phone" class="block text-sm font-medium text-charcoal">Public Phone (Optional)</label>
                            <input type="text" name="public_phone" id="public_phone" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <label for="qualification" class="block text-sm font-medium text-charcoal">Qualification (Optional)</label>
                        <input type="text" name="qualification" id="qualification" placeholder="e.g. B.Sc. Education" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                    </div>
                    
                    <div class="mt-6">
                        <label for="short_bio" class="block text-sm font-medium text-charcoal">Short Bio (Optional)</label>
                        <textarea id="short_bio" name="short_bio" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm"></textarea>
                    </div>
                    
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="show_in_directory" name="show_in_directory" type="checkbox" value="1" class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="show_in_directory" class="font-medium text-charcoal">Show in Public Directory</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-charcoal">Display Order Priority (Lower appears first)</label>
                            <input type="number" name="display_order" id="display_order" value="100" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                    </div>
                    
                    <div id="section-teaching-eligibility">
                        <div class="border-b border-gray-200 pb-4 mb-4 mt-8">
                            <h3 class="text-lg font-medium text-school-navy">Teaching Eligibility</h3>
                            <p class="text-xs text-slate-gray mt-1">These settings determine whether this staff member can be selected for academic teaching assignments. Actual subjects and classes are assigned by the Principal.</p>
                        </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="can_teach_subjects" name="can_teach_subjects" type="checkbox" value="1" class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="can_teach_subjects" class="font-medium text-charcoal">Can teach subjects</label>
                            </div>
                        </div>
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="can_be_class_teacher" name="can_be_class_teacher" type="checkbox" value="1" class="focus:ring-school-blue h-4 w-4 text-school-blue border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="can_be_class_teacher" class="font-medium text-charcoal">Can serve as a Class Teacher</label>
                            </div>
                        </div>
                    </div>
                    
                    <div id="section-account-info">
                        <div class="border-b border-gray-200 pb-4 mb-4 mt-8">
                            <h3 class="text-lg font-medium text-school-navy">Account Information</h3>
                        </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="role_id" class="block text-sm font-medium text-charcoal">System Role *</label>
                            <select id="role_id" name="role_id" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                                <option value="">Select a role...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>"><?= e(str_replace('_', ' ', $role['name'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-charcoal">Email Address (Optional)</label>
                            <input type="email" name="email" id="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="username" class="block text-sm font-medium text-charcoal">Username *</label>
                            <input type="text" name="username" id="username" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-charcoal">Temporary Password *</label>
                            <input type="password" name="password" id="password" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-school-blue focus:border-school-blue sm:text-sm">
                        </div>
                    </div>
                    </div>
                    
                    <div class="pt-5 border-t border-gray-200 mt-8 flex justify-end">
                        <button type="submit" class="bg-school-blue text-white px-6 py-2 rounded font-medium text-sm hover:bg-school-navy transition-colors shadow-sm">Save Staff Member</button>
                    </div>
                </form>
            </div>
            
        </main>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const portalRadios = document.querySelectorAll('input[name="portal_access"]');
        const teachingSection = document.getElementById('section-teaching-eligibility');
        const accountSection = document.getElementById('section-account-info');
        
        function toggleSections() {
            const isPortal = document.getElementById('access_portal').checked;
            
            if (isPortal) {
                teachingSection.style.display = 'block';
                accountSection.style.display = 'block';
                
                document.getElementById('role_id').required = true;
                document.getElementById('username').required = true;
                document.getElementById('password').required = true;
            } else {
                teachingSection.style.display = 'none';
                accountSection.style.display = 'none';
                
                document.getElementById('role_id').required = false;
                document.getElementById('username').required = false;
                document.getElementById('password').required = false;
                
                document.getElementById('can_teach_subjects').checked = false;
                document.getElementById('can_be_class_teacher').checked = false;
            }
        }
        
        portalRadios.forEach(radio => {
            radio.addEventListener('change', toggleSections);
        });
        
        toggleSections(); // Initial state
    });
</script>
<?php require __DIR__ . '/../../templates/layouts/app-footer.php'; ?>
