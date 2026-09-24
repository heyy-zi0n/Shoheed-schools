<?php
// flash.php is loaded by bootstrap — no local require needed.
$flashes = get_flash();
foreach ($flashes as $flash):
    $bgClass = 'bg-blue-50 text-blue-800 border-blue-200';
    $isAutoDismiss = true;
    if ($flash['type'] === 'error') {
        $bgClass = 'bg-red-50 text-red-800 border-red-200';
        $isAutoDismiss = false;
    } elseif ($flash['type'] === 'success') {
        $bgClass = 'bg-green-50 text-green-800 border-green-200';
    } elseif ($flash['type'] === 'warning') {
        $bgClass = 'bg-yellow-50 text-yellow-800 border-yellow-200';
    }
    
    $dismissAttr = $isAutoDismiss ? 'data-auto-dismiss="true"' : '';
?>
<div class="flash-message p-4 mb-4 text-sm rounded-lg border whitespace-pre-line <?= $bgClass ?>" role="alert" <?= $dismissAttr ?>>
    <?= e($flash['message']) ?>
</div>
<?php endforeach; ?>
