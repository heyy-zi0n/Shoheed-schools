<?php
/**
 * @var string $title
 * @var string $message
 * @var string|null $icon (optional SVG string)
 */
?>
<div class="p-8 text-center bg-gray-50/50 rounded-lg border border-dashed border-gray-200">
    <?php if (!empty($icon)): ?>
        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100 mb-4">
            <span class="text-gray-400">
                <?= $icon ?>
            </span>
        </div>
    <?php endif; ?>
    <h3 class="text-sm font-bold text-school-navy"><?= e($title) ?></h3>
    <p class="text-sm text-slate-gray mt-1"><?= e($message) ?></p>
</div>
