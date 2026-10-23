<?php
$__path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
?>
<div class="hq-empty-state hq-notfound">
    <span class="hq-notfound-icon" aria-hidden="true"><i class="fa-solid fa-compass"></i></span>
    <h1><?= htmlspecialchars($title ?? 'Page not found') ?></h1>
    <p>Nothing lives at <code><?= htmlspecialchars($__path) ?></code>. It may have moved or been renamed.</p>
    <div class="hq-notfound-actions">
        <button type="button" class="btn btn-primary" data-hq-open-search><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Search HQ <kbd>Ctrl K</kbd></button>
        <a class="btn btn-secondary" href="/hq"><i class="fa-solid fa-house" aria-hidden="true"></i> Back to overview</a>
    </div>
</div>
<script>
document.querySelector('[data-hq-open-search]')?.addEventListener('click', function () {
    if (window.MovaHqSearch) window.MovaHqSearch.open(''); else document.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, bubbles: true, cancelable: true }));
});
</script>
