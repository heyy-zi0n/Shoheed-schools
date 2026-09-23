    <!-- Footer -->
    <footer id="contact" class="bg-charcoal text-white pt-20 pb-10 border-t-4 border-school-red mt-auto">
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
                    <a href="<?= url('') ?>" class="hover:text-white transition-colors">Privacy Policy</a>
                    <a href="<?= url('') ?>" class="hover:text-white transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>
    <script src="<?= url('assets/js/app.js') ?>"></script>
    <script src="<?= url('assets/js/forms.js') ?>"></script>
    <script src="<?= url('assets/js/modals.js') ?>"></script>
    <script src="<?= url('assets/js/results.js') ?>"></script>
</body>
</html>
