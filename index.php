<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Shoheed Schools</title>
    <link rel="icon" type="image/png" href="<?= url('assets/images/branding/shoheed-logo.png') ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'school-blue': '#263B8F',
                        'school-navy': '#111B3F',
                        'school-red': '#C62828',
                        'school-gold': '#E5B926',
                        'ivory': '#F5F1E8',
                        'paper': '#FFFDFC',
                        'slate-gray': '#697386',
                        'charcoal': '#20242C'
                    },
                    fontFamily: {
                        'sans': ['Manrope', 'sans-serif'],
                        'serif': ['DM Serif Display', 'serif']
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Manrope:wght@400;500;600;700&display=swap');
    </style>
    <link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= url('assets/css/print.css') ?>" media="print">
</head>
<body class="antialiased flex flex-col min-h-screen bg-ivory">
    <!-- Utility Bar -->
    <div class="bg-school-navy text-white text-xs py-2 px-6 flex justify-between items-center hidden md:flex">
        <div class="flex space-x-6">
            <span>+234 802 872 1204</span>
            <span>Alakuko/Alagbado, Lagos</span>
            <span>Morning Session: 8:00 AM – 3:00 PM</span>
        </div>
        <a href="<?= url('login.php') ?>" class="text-school-gold hover:text-white font-medium transition-colors">Portal Login</a>
    </div>

    <!-- Main Navigation -->
    <nav class="bg-paper shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20">
                <div class="flex items-center">
                    <a href="<?= url() ?>" class="flex items-center space-x-3">
                        <img class="h-12 w-auto" src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools">
                        <div class="hidden sm:block">
                            <h1 class="text-school-navy font-bold text-lg leading-tight uppercase tracking-wide">Shoheed Schools</h1>
                            <p class="text-slate-gray text-[10px] uppercase font-bold tracking-[0.15em]">God Is Sufficient</p>
                        </div>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors">Home</a>
                    <a href="#about" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors">About</a>
                    <a href="#academics" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors">Academics</a>
                    <a href="#admissions" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors">Admissions</a>
                    <a href="#contact" class="text-charcoal hover:text-school-blue font-medium text-sm transition-colors">Contact</a>
                    <a href="<?= url('login.php') ?>" class="bg-school-blue text-white px-5 py-2.5 rounded hover:bg-school-navy transition-colors font-medium text-sm shadow-sm">Portal Access</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative bg-school-navy text-white overflow-hidden">
        <!-- Abstract motif background -->
        <div class="absolute inset-0 opacity-10 pointer-events-none">
            <svg class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/4" width="800" height="800" fill="none" viewBox="0 0 800 800"><circle cx="400" cy="400" r="400" fill="#E5B926"/></svg>
            <svg class="absolute bottom-0 left-0 transform -translate-x-1/3 translate-y-1/3" width="600" height="600" fill="none" viewBox="0 0 600 600"><circle cx="300" cy="300" r="300" fill="#C62828"/></svg>
        </div>
        
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 md:py-32 relative z-10 flex flex-col md:flex-row items-center">
            <div class="md:w-3/5 md:pr-12">
                <p class="text-school-gold font-bold tracking-widest uppercase text-sm mb-4">God Is Sufficient</p>
                <h2 class="text-5xl md:text-6xl font-serif font-bold leading-tight mb-6">Learning with purpose.<br>Growing with discipline.</h2>
                <p class="text-lg md:text-xl text-gray-300 mb-10 max-w-2xl font-light leading-relaxed">
                    Structured and personalised education that develops academic ability, character, confidence and future readiness.
                </p>
                <div class="flex flex-col sm:flex-row space-y-4 sm:space-y-0 sm:space-x-4">
                    <a href="#about" class="bg-school-gold text-school-navy px-8 py-4 rounded font-bold text-center hover:bg-white transition-colors shadow-lg">Discover Shoheed</a>
                    <a href="<?= url('login.php') ?>" class="border border-white text-white px-8 py-4 rounded font-medium text-center hover:bg-white hover:text-school-navy transition-colors">Portal Login</a>
                </div>
            </div>
            <div class="md:w-2/5 mt-16 md:mt-0 hidden md:block">
                <!-- If real images are available, they would replace this placeholder area -->
                <div class="relative w-full aspect-[4/5] bg-school-blue/30 rounded-lg overflow-hidden border border-school-blue/50 p-2 shadow-2xl">
                     <div class="w-full h-full bg-school-navy/80 rounded flex items-center justify-center">
                         <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Logo" class="w-1/2 opacity-50 grayscale">
                     </div>
                </div>
            </div>
        </div>
    </section>

    <!-- School Introduction -->
    <section id="about" class="py-20 bg-ivory">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center">
            <div class="md:w-1/2 md:pr-16 mb-12 md:mb-0">
                <h3 class="text-school-blue font-bold tracking-widest uppercase text-xs mb-3">About Shoheed</h3>
                <h2 class="text-4xl font-serif font-bold text-school-navy mb-6 leading-tight">A learning environment built around discipline, academic growth and personal development.</h2>
                <p class="text-charcoal mb-6 leading-relaxed">
                    Shoheed Schools combines structured teaching, student monitoring, guidance, and character development to support learners across their educational journey. We believe that true education develops not just the mind, but the character of every student.
                </p>
                <a href="#admissions" class="text-school-red font-semibold hover:text-school-navy transition-colors inline-flex items-center border-b border-school-red pb-1">
                    Learn about our admissions process <span class="ml-2">&rarr;</span>
                </a>
            </div>
            <div class="md:w-1/2 w-full grid grid-cols-2 gap-4">
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 flex flex-col items-start justify-center h-48">
                    <h4 class="text-xl font-serif font-bold text-school-navy mb-2">Personalised</h4>
                    <p class="text-sm text-slate-gray">Closer teacher-student interaction and structured support.</p>
                </div>
                <div class="bg-paper p-8 rounded-lg shadow-sm border border-gray-100 flex flex-col items-start justify-center h-48 mt-8">
                    <h4 class="text-xl font-serif font-bold text-school-navy mb-2">Monitored</h4>
                    <p class="text-sm text-slate-gray">Consistent student monitoring, counselling and guidance.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Academic Journey -->
    <section class="py-20 bg-paper border-y border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h3 class="text-school-blue font-bold tracking-widest uppercase text-xs mb-3">Our School Journey</h3>
                <h2 class="text-4xl font-serif font-bold text-school-navy">The Shoheed Pathway</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Early Years -->
                <div class="p-8 border border-gray-100 rounded-xl bg-ivory/50">
                    <div class="w-12 h-12 bg-school-blue text-white rounded-full flex items-center justify-center font-serif text-xl font-bold mb-6">1</div>
                    <h4 class="text-2xl font-serif font-bold text-school-navy mb-3">Early Years</h4>
                    <p class="text-school-red font-semibold text-sm mb-4">Pre-School &rarr; Nursery</p>
                    <p class="text-charcoal text-sm leading-relaxed">A nurturing beginning for young learners, focusing on foundational cognitive, social, and motor skills in a secure environment.</p>
                </div>
                <!-- Foundation -->
                <div class="p-8 border border-gray-100 rounded-xl bg-ivory/50">
                    <div class="w-12 h-12 bg-school-blue text-white rounded-full flex items-center justify-center font-serif text-xl font-bold mb-6">2</div>
                    <h4 class="text-2xl font-serif font-bold text-school-navy mb-3">Foundation</h4>
                    <p class="text-school-red font-semibold text-sm mb-4">Basic / Primary</p>
                    <p class="text-charcoal text-sm leading-relaxed">Strong foundational academic development, introducing core subjects and building disciplined learning habits.</p>
                </div>
                <!-- Secondary -->
                <div class="p-8 border border-school-blue/20 rounded-xl bg-white shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-2 h-full bg-school-blue"></div>
                    <div class="w-12 h-12 bg-school-navy text-white rounded-full flex items-center justify-center font-serif text-xl font-bold mb-6">3</div>
                    <h4 class="text-2xl font-serif font-bold text-school-navy mb-3">Secondary</h4>
                    <p class="text-school-red font-semibold text-sm mb-4">JSS1 &rarr; SSS3</p>
                    <p class="text-charcoal text-sm leading-relaxed mb-4">Broader academic exposure, focused preparation for examinations (WAEC, NECO, UTME), and transition into higher education.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Band -->
    <section class="bg-school-navy text-white py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 divide-x divide-school-blue">
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-school-gold mb-2">2</p>
                    <p class="text-xs tracking-widest uppercase text-gray-400 font-semibold">Campuses</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white mb-2">100%</p>
                    <p class="text-xs tracking-widest uppercase text-gray-400 font-semibold">Commitment</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white mb-2">JSS&ndash;SSS</p>
                    <p class="text-xs tracking-widest uppercase text-gray-400 font-semibold">Secondary Levels</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white mb-2">1st</p>
                    <p class="text-xs tracking-widest uppercase text-gray-400 font-semibold">Choice for Discipline</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Shoheed & Core Values -->
    <section id="academics" class="py-20 bg-ivory">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-16">
            <div class="lg:w-1/2">
                <h3 class="text-school-blue font-bold tracking-widest uppercase text-xs mb-3">Why Shoheed</h3>
                <h2 class="text-4xl font-serif font-bold text-school-navy mb-10">Our Educational Pillars</h2>
                
                <div class="space-y-8">
                    <div class="flex items-start">
                        <div class="text-school-red font-serif font-bold text-2xl mr-6 mt-1">01</div>
                        <div>
                            <h4 class="text-xl font-bold text-charcoal mb-2">Academic Development</h4>
                            <p class="text-slate-gray text-sm leading-relaxed">An organised scheme of work focused on steady academic growth and measurable improvement.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="text-school-red font-serif font-bold text-2xl mr-6 mt-1">02</div>
                        <div>
                            <h4 class="text-xl font-bold text-charcoal mb-2">Morals & Discipline</h4>
                            <p class="text-slate-gray text-sm leading-relaxed">Academic development reinforced by strong character, personal responsibility, and institutional discipline.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="text-school-red font-serif font-bold text-2xl mr-6 mt-1">03</div>
                        <div>
                            <h4 class="text-xl font-bold text-charcoal mb-2">Computer Education</h4>
                            <p class="text-slate-gray text-sm leading-relaxed">Modern technology and computer studies integrated as a fundamental part of student development.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:w-1/2 bg-paper p-10 md:p-12 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-school-blue font-bold tracking-widest uppercase text-xs mb-3">Our Identity</h3>
                <h2 class="text-3xl font-serif font-bold text-school-navy mb-8">Core Values</h2>
                
                <ul class="space-y-4">
                    <li class="flex items-center text-charcoal font-medium pb-4 border-b border-gray-100">
                        <span class="w-2 h-2 rounded-full bg-school-gold mr-4"></span> Discipline
                    </li>
                    <li class="flex items-center text-charcoal font-medium pb-4 border-b border-gray-100">
                        <span class="w-2 h-2 rounded-full bg-school-gold mr-4"></span> Excellence
                    </li>
                    <li class="flex items-center text-charcoal font-medium pb-4 border-b border-gray-100">
                        <span class="w-2 h-2 rounded-full bg-school-gold mr-4"></span> Integrity
                    </li>
                    <li class="flex items-center text-charcoal font-medium pb-4 border-b border-gray-100">
                        <span class="w-2 h-2 rounded-full bg-school-gold mr-4"></span> Responsibility
                    </li>
                    <li class="flex items-center text-charcoal font-medium">
                        <span class="w-2 h-2 rounded-full bg-school-gold mr-4"></span> Respect
                    </li>
                </ul>
            </div>
        </div>
    </section>

    <!-- Leadership Message -->
    <section class="py-24 bg-school-navy text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h3 class="text-school-gold font-bold tracking-widest uppercase text-xs mb-8">A Message From The Principal</h3>
            <p class="text-3xl md:text-4xl font-serif leading-snug mb-10 text-gray-100">
                "At Shoheed Schools, we do not merely teach syllabus material; we build individuals capable of thinking clearly, acting responsibly, and achieving their highest potential."
            </p>
            <div class="w-16 h-1 bg-school-red mx-auto mb-6"></div>
            <p class="font-bold text-lg">The Principal</p>
            <p class="text-sm text-gray-400">Shoheed Schools</p>
        </div>
    </section>

    <!-- Admissions -->
    <section id="admissions" class="py-20 bg-paper">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-ivory border border-gray-200 rounded-2xl overflow-hidden flex flex-col md:flex-row">
                <div class="md:w-1/2 p-12 md:p-16 flex flex-col justify-center">
                    <h3 class="text-school-blue font-bold tracking-widest uppercase text-xs mb-3">Join Us</h3>
                    <h2 class="text-4xl font-serif font-bold text-school-navy mb-6">Admissions</h2>
                    <p class="text-charcoal mb-8 leading-relaxed">
                        We welcome applications from dedicated students who are ready to engage with our structured academic environment and uphold the school's values of discipline and excellence.
                    </p>
                    <div class="space-y-3 mb-8 text-sm text-slate-gray font-medium">
                        <p class="flex items-center"><span class="text-school-red mr-3">01</span> Enquiry & Information</p>
                        <p class="flex items-center"><span class="text-school-red mr-3">02</span> Application Form Submission</p>
                        <p class="flex items-center"><span class="text-school-red mr-3">03</span> Academic Assessment</p>
                        <p class="flex items-center"><span class="text-school-red mr-3">04</span> Admission Decision</p>
                        <p class="flex items-center"><span class="text-school-red mr-3">05</span> Enrollment & Registration</p>
                    </div>
                </div>
                <div class="md:w-1/2 bg-school-blue p-12 md:p-16 text-white flex flex-col justify-center items-start">
                    <h3 class="text-2xl font-serif font-bold mb-4">Start the Process</h3>
                    <p class="mb-8 text-school-blue-100 opacity-90 text-sm leading-relaxed">
                        Contact our administrative office to purchase an application form and schedule an assessment date for your child.
                    </p>
                    <a href="#contact" class="bg-white text-school-navy px-8 py-3 rounded font-bold hover:bg-school-gold transition-colors shadow-sm">
                        Contact Office
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact" class="bg-charcoal text-white pt-20 pb-10 border-t-4 border-school-red">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-16">
                <div class="md:col-span-1">
                    <div class="flex items-center space-x-3 mb-6 bg-white p-2 inline-flex rounded">
                        <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Logo" class="h-10 w-10 object-contain">
                    </div>
                    <h2 class="text-xl font-bold tracking-wide uppercase mb-2">Shoheed Schools</h2>
                    <p class="text-school-gold text-xs uppercase tracking-widest font-bold mb-6">God Is Sufficient</p>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        Providing structured and personalised education that develops academic ability, character, and future readiness.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold tracking-widest uppercase mb-6 text-gray-300">Headquarters</h3>
                    <p class="text-gray-400 text-sm leading-relaxed mb-4">
                        5, Olufunmilayo Close,<br>
                        Opposite Jendol Super Store,<br>
                        Alakuko/Alagbado, Lagos.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold tracking-widest uppercase mb-6 text-gray-300">Annex</h3>
                    <p class="text-gray-400 text-sm leading-relaxed mb-4">
                        Andiri Bus Stop,<br>
                        Ibaragun,<br>
                        Abule Lemode,<br>
                        Ogun State.
                    </p>
                </div>
                
                <div>
                    <h3 class="text-sm font-bold tracking-widest uppercase mb-6 text-gray-300">Contact Us</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            +234 802 872 1204
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                            +234 817 904 3226
                        </li>
                    </ul>
                    <div class="mt-8">
                        <a href="<?= url('login.php') ?>" class="inline-block border border-gray-600 text-gray-300 hover:text-white hover:border-white px-6 py-2 rounded text-sm transition-colors">
                            Portal Login &rarr;
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-500">
                <p>&copy; <?= date('Y') ?> Shoheed Schools. All rights reserved.</p>
                <div class="flex space-x-6 mt-4 md:mt-0">
                    <a href="#" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-white transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    <script src="<?= url('assets/js/app.js') ?>"></script>
    <script src="<?= url('assets/js/forms.js') ?>"></script>
    <script src="<?= url('assets/js/modals.js') ?>"></script>
    <script src="<?= url('assets/js/results.js') ?>"></script>
</body>
</html>
