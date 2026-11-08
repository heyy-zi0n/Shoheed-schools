<?php
/**
 * @var string $title
 * @var string $url
 * @var string $icon (1-2 characters or icon key, e.g. 'S', 'T', 'A', 'F', '₦', 'B', 'C', 'I')
 * @var string $color
 */
$iconKey = strtoupper(trim((string)($icon ?? '')));
$svgPath = match ($iconKey) {
    'S' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m16-10a4 4 0 010 7.75M13 7a4 4 0 11-8 0 4 4 0 018 0zm6 4v6m3-3h-6" />',
    'T' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z" />',
    'A', 'C' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />',
    'F', 'I' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
    '₦' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />',
    'B' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />',
    default => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 10V3L4 14h7v7l9-11h-7z" />',
};
?>
<a href="<?= e($url) ?>" class="flex items-center justify-between p-4 bg-paper border border-gray-200/80 rounded-lg shadow-sm hover:border-school-blue hover:bg-ivory/60 transition-colors group">
    <div class="flex items-center min-w-0">
        <div class="w-10 h-10 rounded-lg bg-ivory border border-gray-200/80 text-school-navy flex items-center justify-center mr-3.5 flex-shrink-0 group-hover:bg-school-blue group-hover:text-white group-hover:border-school-blue transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <?= $svgPath ?>
            </svg>
        </div>
        <p class="font-semibold text-sm text-school-navy group-hover:text-school-blue transition-colors truncate"><?= e($title) ?></p>
    </div>
    <span class="text-slate-gray group-hover:text-school-blue transition-colors ml-2 flex-shrink-0" aria-hidden="true">&rarr;</span>
</a>
