<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/bootstrap.php';
$pageTitle = 'Home';

$pdo = get_db_connection();
$stmt = $pdo->query("
    SELECT id, first_name, last_name, profile_image, public_designation, staff_category
    FROM staff 
    WHERE show_in_directory = 1 AND status = 'ACTIVE' 
    ORDER BY display_order ASC, last_name ASC 
    LIMIT 4
");
$previewStaff = $stmt->fetchAll();

// Fetch real institutional statistics from database where available
$publicStudentCount = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'ACTIVE'")->fetchColumn();
$publicStaffCount   = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'ACTIVE'")->fetchColumn();
$publicClassCount   = (int)$pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();

// Fetch public announcements if any are published
$publicNewsStmt = $pdo->query("
    SELECT title, body, created_at
    FROM announcements
    WHERE status = 'PUBLISHED' AND audience IN ('PUBLIC', 'ALL')
    ORDER BY created_at DESC
    LIMIT 3
");
$publicAnnouncements = $publicNewsStmt->fetchAll();

$activePublicNav = 'home';
require __DIR__ . '/templates/public/header.php';
?>
<main id="main-content" class="flex-grow">
    <!-- Hero Section -->
    <section class="relative bg-school-navy text-white border-b-4 border-school-gold">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 md:py-28">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7">
                    <div class="inline-flex items-center space-x-2 border border-school-gold/40 bg-school-blue/40 px-3 py-1 rounded text-xs font-bold tracking-[0.18em] uppercase text-school-gold mb-6">
                        <span>Shoheed Schools</span>
                        <span aria-hidden="true">&bull;</span>
                        <span>God Is Sufficient</span>
                    </div>
                    <h1 class="text-4xl sm:text-5xl md:text-6xl font-serif font-bold leading-tight mb-6">
                        Learning with purpose.<br>Growing with discipline.
                    </h1>
                    <p class="text-base sm:text-lg md:text-xl text-gray-200 mb-10 max-w-2xl leading-relaxed">
                        Structured and personalised education that develops academic ability, moral character, confidence, and future readiness from Early Years through Senior Secondary.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="#about" class="bg-school-gold text-school-navy px-8 py-3.5 rounded-md font-bold text-center hover:bg-white transition-colors shadow-sm">
                            Discover Shoheed
                        </a>
                        <a href="<?= url('login.php') ?>" class="border border-white/80 text-white px-8 py-3.5 rounded-md font-semibold text-center hover:bg-white hover:text-school-navy transition-colors">
                            Portal Login
                        </a>
                    </div>
                </div>

                <!-- Institutional Dossier Card (replaces decorative greyscale watermark placeholder) -->
                <div class="lg:col-span-5">
                    <div class="bg-paper text-charcoal rounded-xl p-6 sm:p-8 shadow-lg border-t-4 border-school-red">
                        <div class="flex items-center space-x-4 pb-6 mb-6 border-b border-gray-200">
                            <img src="<?= url('assets/images/branding/shoheed-logo.png') ?>" alt="Shoheed Schools Crest" width="56" height="56" class="h-14 w-14 object-contain flex-shrink-0">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.15em] text-school-blue">Institutional Profile</p>
                                <h2 class="text-xl font-serif font-bold text-school-navy">Shoheed Private Schools</h2>
                                <p class="text-xs text-slate-gray font-semibold uppercase tracking-widest">Lagos &amp; Ogun State Campuses</p>
                            </div>
                        </div>

                        <dl class="space-y-4 text-sm">
                            <div class="flex justify-between items-start pb-3 border-b border-gray-100">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-gray">Academic Levels</dt>
                                <dd class="font-semibold text-school-navy text-right">Pre-School, Primary &amp; JSS1&ndash;SSS3</dd>
                            </div>
                            <div class="flex justify-between items-start pb-3 border-b border-gray-100">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-gray">Morning Session</dt>
                                <dd class="font-semibold text-charcoal tabular-nums text-right">8:00 AM &ndash; 3:00 PM</dd>
                            </div>
                            <div class="flex justify-between items-start pb-3 border-b border-gray-100">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-gray">Afternoon Tutorial</dt>
                                <dd class="font-semibold text-charcoal tabular-nums text-right">3:30 PM &ndash; 5:00 PM</dd>
                            </div>
                            <div class="flex justify-between items-start">
                                <dt class="text-xs font-bold uppercase tracking-wider text-slate-gray">Headquarters</dt>
                                <dd class="text-xs text-charcoal text-right max-w-[220px]">5, Olufunmilayo Close, Alakuko/Alagbado, Lagos</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- School Introduction -->
    <section id="about" class="py-20 bg-ivory">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row items-center gap-12">
            <div class="md:w-1/2">
                <p class="page-eyebrow mb-3">About Shoheed</p>
                <h2 class="text-3xl sm:text-4xl font-serif font-bold text-school-navy mb-6 leading-tight">
                    A learning environment built around discipline, academic growth and personal development.
                </h2>
                <p class="text-charcoal mb-6 leading-relaxed">
                    Shoheed Schools combines structured teaching, continuous student monitoring, academic counselling, and character development to support learners across every stage of their educational journey.
                </p>
                <a href="#admissions" class="text-school-red font-semibold hover:text-school-navy transition-colors inline-flex items-center border-b-2 border-school-red pb-1 text-sm">
                    <span>Learn about our admissions process</span>
                    <span class="ml-2" aria-hidden="true">&rarr;</span>
                </a>
            </div>
            <div class="md:w-1/2 w-full grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="bg-paper p-7 rounded-lg shadow-sm border border-gray-200/80 border-t-4 border-t-school-blue">
                    <h3 class="text-xl font-serif font-bold text-school-navy mb-2">Personalised Teaching</h3>
                    <p class="text-sm text-slate-gray leading-relaxed">Close teacher-student interaction with structured lesson delivery and continuous assessment.</p>
                </div>
                <div class="bg-paper p-7 rounded-lg shadow-sm border border-gray-200/80 border-t-4 border-t-school-gold">
                    <h3 class="text-xl font-serif font-bold text-school-navy mb-2">Guided Monitoring</h3>
                    <p class="text-sm text-slate-gray leading-relaxed">Consistent academic tracking, behavioural evaluation (SDBA), and student counselling.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Academic Journey -->
    <section class="py-20 bg-paper border-y border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <p class="page-eyebrow mb-2">Our School Journey</p>
                <h2 class="text-3xl sm:text-4xl font-serif font-bold text-school-navy">The Shoheed Academic Pathway</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Early Years -->
                <div class="p-8 border border-gray-200 rounded-xl bg-ivory/50 flex flex-col justify-between">
                    <div>
                        <span class="inline-block text-xs font-bold uppercase tracking-widest text-school-blue mb-3">Stage 01</span>
                        <h3 class="text-2xl font-serif font-bold text-school-navy mb-2">Early Years</h3>
                        <p class="text-school-red font-semibold text-xs uppercase tracking-wider mb-4">Pre-School &rarr; Nursery</p>
                        <p class="text-charcoal text-sm leading-relaxed">A nurturing foundation for young learners focusing on early literacy, numeracy, social confidence, and motor skills in a caring environment.</p>
                    </div>
                </div>
                <!-- Foundation -->
                <div class="p-8 border border-gray-200 rounded-xl bg-ivory/50 flex flex-col justify-between">
                    <div>
                        <span class="inline-block text-xs font-bold uppercase tracking-widest text-school-blue mb-3">Stage 02</span>
                        <h3 class="text-2xl font-serif font-bold text-school-navy mb-2">Basic / Primary</h3>
                        <p class="text-school-red font-semibold text-xs uppercase tracking-wider mb-4">Primary 1 &rarr; Primary 6</p>
                        <p class="text-charcoal text-sm leading-relaxed">Strong foundational academic development introducing core subjects, computer studies, and disciplined study habits.</p>
                    </div>
                </div>
                <!-- Secondary -->
                <div class="p-8 border-2 border-school-blue rounded-xl bg-paper shadow-sm flex flex-col justify-between">
                    <div>
                        <span class="inline-block text-xs font-bold uppercase tracking-widest text-school-navy bg-school-gold/30 px-2.5 py-0.5 rounded mb-3">Stage 03 &bull; Portal Active</span>
                        <h3 class="text-2xl font-serif font-bold text-school-navy mb-2">Secondary School</h3>
                        <p class="text-school-red font-semibold text-xs uppercase tracking-wider mb-4">JSS1 &rarr; JSS3 &bull; SSS1 &rarr; SSS3</p>
                        <p class="text-charcoal text-sm leading-relaxed">Comprehensive junior and senior secondary curriculum with cumulative 20/20/60 assessment and national examination readiness.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Band (Real Database Metrics + Institutional Scope) -->
    <section class="bg-school-navy text-white py-14 border-b border-white/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 md:divide-x md:divide-school-gold/30">
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-school-gold tabular-nums mb-2">2</p>
                    <p class="text-xs tracking-[0.15em] uppercase text-gray-300 font-semibold">Campuses (Lagos &amp; Ogun)</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white tabular-nums mb-2"><?= $publicClassCount > 0 ? number_format($publicClassCount) : '6' ?></p>
                    <p class="text-xs tracking-[0.15em] uppercase text-gray-300 font-semibold">Secondary Levels (JSS1&ndash;SSS3)</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white tabular-nums mb-2"><?= $publicStaffCount > 0 ? number_format($publicStaffCount) : 'Dedicated' ?></p>
                    <p class="text-xs tracking-[0.15em] uppercase text-gray-300 font-semibold">Active Educators &amp; Staff</p>
                </div>
                <div class="text-center px-4">
                    <p class="text-4xl md:text-5xl font-serif font-bold text-white tabular-nums mb-2"><?= $publicStudentCount > 0 ? number_format($publicStudentCount) : 'Enrolling' ?></p>
                    <p class="text-xs tracking-[0.15em] uppercase text-gray-300 font-semibold">Portal-Enrolled Students</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Shoheed & Core Values -->
    <section id="academics" class="py-20 bg-ivory">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col lg:flex-row gap-14">
            <div class="lg:w-7/12">
                <p class="page-eyebrow mb-2">Why Shoheed</p>
                <h2 class="text-3xl sm:text-4xl font-serif font-bold text-school-navy mb-10">Our Educational Pillars</h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                    <div class="flex items-start">
                        <span class="text-school-red font-serif font-bold text-2xl mr-4 tabular-nums">01</span>
                        <div>
                            <h3 class="text-base font-bold text-school-navy mb-1.5">Academic Development</h3>
                            <p class="text-slate-gray text-sm leading-relaxed">An organised scheme of work focused on steady academic growth and measurable improvement.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="text-school-red font-serif font-bold text-2xl mr-4 tabular-nums">02</span>
                        <div>
                            <h3 class="text-base font-bold text-school-navy mb-1.5">Morals &amp; Discipline</h3>
                            <p class="text-slate-gray text-sm leading-relaxed">Academic instruction reinforced by strong character, personal responsibility, and institutional order.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="text-school-red font-serif font-bold text-2xl mr-4 tabular-nums">03</span>
                        <div>
                            <h3 class="text-base font-bold text-school-navy mb-1.5">Computer Education</h3>
                            <p class="text-slate-gray text-sm leading-relaxed">Practical technology and computer studies integrated as a core component of student literacy.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <span class="text-school-red font-serif font-bold text-2xl mr-4 tabular-nums">04</span>
                        <div>
                            <h3 class="text-base font-bold text-school-navy mb-1.5">Guidance &amp; Monitoring</h3>
                            <p class="text-slate-gray text-sm leading-relaxed">Close academic supervision, termly psychomotor/affective evaluation, and student counselling.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="lg:w-5/12 bg-paper p-8 sm:p-10 rounded-xl shadow-sm border border-gray-200/80">
                <p class="page-eyebrow mb-2">Our Identity</p>
                <h2 class="text-2xl sm:text-3xl font-serif font-bold text-school-navy mb-6">Core Values &amp; Examination Readiness</h2>
                
                <ul class="grid grid-cols-2 gap-3 mb-8">
                    <?php foreach (['Discipline', 'Excellence', 'Integrity', 'Responsibility', 'Respect', 'Diligence'] as $val): ?>
                        <li class="flex items-center text-sm text-charcoal font-semibold py-2 px-3 bg-ivory/70 rounded border border-gray-200/60">
                            <span class="w-2 h-2 rounded-full bg-school-gold mr-2.5 flex-shrink-0" aria-hidden="true"></span>
                            <?= e($val) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="pt-6 border-t border-gray-200">
                    <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-school-blue mb-3">External Examination Preparation</h3>
                    <p class="text-xs text-slate-gray leading-relaxed mb-4">
                        Senior Secondary students receive structured syllabus coverage and intensive afternoon tutorial preparation for national and tertiary entrance examinations:
                    </p>
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <span class="py-1.5 px-2 bg-school-navy text-white text-xs font-bold rounded">WAEC</span>
                        <span class="py-1.5 px-2 bg-school-navy text-white text-xs font-bold rounded">NECO</span>
                        <span class="py-1.5 px-2 bg-school-navy text-white text-xs font-bold rounded">GCE</span>
                        <span class="py-1.5 px-2 bg-school-navy text-white text-xs font-bold rounded">UTME</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Meet Our Team Preview -->
    <section class="py-16 bg-paper border-y border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <p class="page-eyebrow mb-2">Our Dedicated Team</p>
                <h2 class="text-3xl font-serif font-bold text-school-navy mb-4">Meet the Educators Guiding Our Students</h2>
                <p class="text-charcoal max-w-2xl mx-auto text-sm sm:text-base leading-relaxed">
                    Our teaching and administrative staff are committed professionals dedicated to academic rigour and student mentorship.
                </p>
            </div>
            
            <?php if (!empty($previewStaff)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-10">
                    <?php foreach ($previewStaff as $staff): ?>
                        <div class="bg-ivory/40 rounded-lg shadow-sm border border-gray-200/80 overflow-hidden text-center p-6 hover:border-school-blue transition-colors">
                            <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-white shadow-sm mb-4 bg-gray-100">
                                <?php if (!empty($staff['profile_image'])): ?>
                                    <img src="<?= url('media/staff-profile.php?id=' . urlencode((string)$staff['id'])) ?>" alt="<?= e($staff['first_name'] . ' ' . $staff['last_name']) ?>" width="96" height="96" loading="lazy" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-school-blue/10 text-school-blue font-bold text-xl">
                                        <?= e(substr((string)$staff['first_name'], 0, 1) . substr((string)$staff['last_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-base font-bold text-school-navy mb-1"><?= e($staff['first_name'] . ' ' . $staff['last_name']) ?></h3>
                            <p class="text-school-red text-xs font-semibold uppercase tracking-wider mb-1.5"><?= e($staff['public_designation'] ?? 'Staff') ?></p>
                            <p class="text-[11px] text-slate-gray uppercase"><?= $staff['staff_category'] === 'TEACHING' ? 'Teaching Staff' : 'Administrative & Support' ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="<?= url('staff.php') ?>" class="inline-block bg-school-blue text-white px-7 py-3 rounded-md text-sm font-semibold hover:bg-school-navy transition-colors shadow-sm">
                    View Full Staff Directory &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- Leadership Message -->
    <section class="py-20 bg-school-navy text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-school-gold font-bold tracking-[0.18em] uppercase text-xs mb-6">A Message From The Principal</p>
            <blockquote class="text-2xl sm:text-3xl md:text-4xl font-serif leading-snug mb-8 text-gray-100">
                &ldquo;At Shoheed Schools, we do not merely teach syllabus material; we build individuals capable of thinking clearly, acting responsibly, and achieving their highest potential.&rdquo;
            </blockquote>
            <div class="w-16 h-1 bg-school-red mx-auto mb-5" aria-hidden="true"></div>
            <p class="font-bold text-base">Office of the Principal</p>
            <p class="text-xs text-gray-300 uppercase tracking-widest mt-1">Shoheed Schools</p>
        </div>
    </section>

    <?php if (!empty($publicAnnouncements)): ?>
    <!-- Public Updates / Notices -->
    <section id="news" class="py-16 bg-ivory border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-10">
                <p class="page-eyebrow mb-2">School Updates</p>
                <h2 class="text-3xl font-serif font-bold text-school-navy">Latest Announcements</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($publicAnnouncements as $notice): ?>
                    <article class="bg-paper p-6 rounded-lg border border-gray-200/80 shadow-sm flex flex-col justify-between">
                        <div>
                            <p class="text-xs font-semibold text-school-blue uppercase tracking-wider mb-2"><?= e(date('F j, Y', strtotime($notice['created_at']))) ?></p>
                            <h3 class="text-lg font-bold text-school-navy mb-2"><?= e($notice['title']) ?></h3>
                            <p class="text-sm text-charcoal leading-relaxed"><?= nl2br(e($notice['body'])) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Admissions -->
    <section id="admissions" class="py-20 bg-paper">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-ivory border border-gray-200 rounded-xl overflow-hidden flex flex-col md:flex-row">
                <div class="md:w-7/12 p-8 sm:p-12 md:p-14 flex flex-col justify-center">
                    <p class="page-eyebrow mb-2">Join Shoheed Schools</p>
                    <h2 class="text-3xl sm:text-4xl font-serif font-bold text-school-navy mb-4">Admissions Process</h2>
                    <p class="text-charcoal text-sm sm:text-base mb-8 leading-relaxed">
                        We welcome applications from students and families who value structured learning, moral discipline, and consistent academic mentorship.
                    </p>
                    <ol class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-charcoal font-medium">
                        <li class="flex items-center bg-paper p-3 rounded border border-gray-200/80"><span class="text-school-red font-bold tabular-nums mr-3">01</span> Enquiry &amp; Counselling</li>
                        <li class="flex items-center bg-paper p-3 rounded border border-gray-200/80"><span class="text-school-red font-bold tabular-nums mr-3">02</span> Application Submission</li>
                        <li class="flex items-center bg-paper p-3 rounded border border-gray-200/80"><span class="text-school-red font-bold tabular-nums mr-3">03</span> Placement Assessment</li>
                        <li class="flex items-center bg-paper p-3 rounded border border-gray-200/80"><span class="text-school-red font-bold tabular-nums mr-3">04</span> Admission &amp; Registration</li>
                    </ol>
                </div>
                <div class="md:w-5/12 bg-school-blue p-8 sm:p-12 md:p-14 text-white flex flex-col justify-center items-start">
                    <h3 class="text-2xl font-serif font-bold mb-3">Visit or Contact Admissions</h3>
                    <p class="mb-6 text-gray-200 text-sm leading-relaxed">
                        Obtain an application form at either our Lagos Headquarters (Alakuko/Alagbado) or Ogun State Annex (Ibaragun) during school hours (8:00 AM &ndash; 3:00 PM).
                    </p>
                    <a href="#contact" class="bg-white text-school-navy px-7 py-3 rounded-md text-sm font-bold hover:bg-school-gold transition-colors shadow-sm">
                        View Campus Contacts
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Portal Access Gateway (UI.md §31) -->
    <section class="py-16 bg-ivory border-t border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-school-navy text-white rounded-xl p-8 sm:p-12 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8 border-l-4 border-school-gold">
                <div class="max-w-2xl">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-school-gold mb-2">Shoheed Management Portal</p>
                    <h2 class="text-2xl sm:text-3xl font-serif font-bold mb-3">Access Academic Records, Attendance &amp; Fee Statements</h2>
                    <p class="text-sm text-gray-300 leading-relaxed">
                        Students, Subject &amp; Class Teachers, the Bursar, Principal, and Administrators can sign in with their school credentials to manage termly results, attendance, and fee receipts.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-4 flex-shrink-0">
                    <a href="<?= url('login.php') ?>" class="bg-school-gold text-school-navy px-7 py-3.5 rounded-md text-sm font-bold hover:bg-white transition-colors shadow-sm">
                        Access Portal &rarr;
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/templates/public/footer.php'; ?>
