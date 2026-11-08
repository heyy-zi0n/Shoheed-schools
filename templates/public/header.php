<?php
$pageTitle = $pageTitle ?? 'Home';
$activePublicNav = $activePublicNav ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Shoheed Schools</title>
    <link rel="icon" type="image/png" href="<?= url('assets/images/branding/shoheed-logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= url('assets/css/tailwind.min.css') ?>?v=<?= filemtime(__DIR__ . '/../../assets/css/tailwind.min.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
</head>
<body class="antialiased flex flex-col min-h-screen bg-ivory">
    <!-- Main Navigation -->
    <nav aria-label="Primary" class="bg-paper shadow-sm sticky top-0 z-50 border-b border-gray-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="<?= url('') ?>" class="flex items-center space-x-3">
                        <img class="h-12 w-auto" width="48" height="48" src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Crest">
                        <div class="hidden sm:block">
                            <span class="block text-school-navy font-bold text-lg leading-tight uppercase tracking-wide">Shoheed Schools</span>
                            <span class="block text-slate-gray text-[10px] uppercase font-bold tracking-[0.15em]">God Is Sufficient</span>
                        </div>
                    </a> 
                </div>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="<?= url('') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'home' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">Home</a>
                    <a href="<?= url('index.php#about') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'about' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">About</a>
                    <a href="<?= url('staff.php') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'staff' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">Our Staff</a>
                    <a href="<?= url('index.php#academics') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'academics' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">Academics</a>
                    <a href="<?= url('index.php#admissions') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'admissions' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">Admissions</a>
                    <a href="<?= url('index.php#contact') ?>" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors <?= $activePublicNav === 'contact' ? 'border-b-2 border-school-blue py-7 text-school-blue' : '' ?>">Contact</a>
                    <a href="<?= url('login.php') ?>" class="bg-school-blue text-white px-5 py-2.5 rounded hover:bg-school-navy transition-colors font-medium text-sm shadow-sm">Portal Access</a>
                </div>
                
                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button type="button" id="mobile-menu-btn" aria-label="Toggle navigation menu" aria-controls="mobile-menu" aria-expanded="false" class="text-charcoal hover:text-school-blue rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-school-blue p-2">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Navigation Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-paper border-t border-gray-100">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="<?= url('') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'home' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">Home</a>
                <a href="<?= url('index.php#about') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'about' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">About</a>
                <a href="<?= url('staff.php') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'staff' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">Our Staff</a>
                <a href="<?= url('index.php#academics') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'academics' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">Academics</a>
                <a href="<?= url('index.php#admissions') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'admissions' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">Admissions</a>
                <a href="<?= url('index.php#contact') ?>" class="block px-3 py-2 rounded-md text-base font-medium <?= $activePublicNav === 'contact' ? 'bg-ivory text-school-blue' : 'text-charcoal hover:bg-ivory hover:text-school-blue' ?>">Contact</a>
                <a href="<?= url('login.php') ?>" class="block px-3 py-2 mt-4 rounded-md text-base font-medium bg-school-blue text-white text-center">Portal Access</a>
            </div>
        </div>
    </nav>
