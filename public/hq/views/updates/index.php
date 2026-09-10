<?php
/** @var array $check */
/** @var array $meta */
$current = htmlspecialchars($check['current'] ?? ($meta['version'] ?? '?'));
$latest = htmlspecialchars($check['latest'] ?? '');
$message = htmlspecialchars($check['message'] ?? '');
$siteName = htmlspecialchars($meta['site']['name'] ?? 'Mova');
$created = htmlspecialchars(substr((string)($meta['site']['created_at'] ?? ''), 0, 10));
$updated = htmlspecialchars(substr((string)($meta['site']['updated_at'] ?? ''), 0, 10));
?>
<section class="hq-landing" style="max-width:720px">
  <header class="hq-landing-header">
    <p class="hq-landing-kicker"><i class="fa-solid fa-arrows-rotate"></i> Updates</p>
    <h1 class="hq-landing-title">Core updates & identity</h1>
    <p class="hq-landing-desc">Check for new Mova releases and keep public identity files in sync.</p>
  </header>

  <div class="hq-card" style="padding:1.25rem 1.35rem;border:1px solid var(--hq-border);border-radius:14px;background:var(--hq-surface);margin-bottom:1rem">
    <p style="margin:0 0 0.5rem;font-size:0.9rem;color:var(--hq-muted)">Installed version</p>
    <p style="margin:0;font-size:1.35rem;font-weight:700"><?= $current ?></p>
    <?php if ($latest): ?>
      <p style="margin:0.75rem 0 0;font-size:0.95rem">Latest: <strong><?= $latest ?></strong></p>
    <?php endif; ?>
    <p style="margin:0.75rem 0 0;color:var(--hq-muted);font-size:0.9rem"><?= $message ?></p>
    <p style="margin:1rem 0 0;font-size:0.82rem;color:var(--hq-muted)">
      One-click package apply ships in a later release. For now use <code>php bin/mova update:check</code> and install core packages manually, or set <code>release_manifest</code> in <code>mova.json</code>.
    </p>
  </div>

  <div class="hq-card" style="padding:1.25rem 1.35rem;border:1px solid var(--hq-border);border-radius:14px;background:var(--hq-surface)">
    <h2 style="margin:0 0 0.75rem;font-size:1.05rem">Public identity</h2>
    <p style="margin:0 0 0.75rem;font-size:0.88rem;color:var(--hq-muted)">
      Exposed as <code>/mova.txt</code> (and <code>mova.json</code>). Folder branding: <code>mova-uploads</code>, <code>mova-plugins</code>, <code>mova-themes</code>.
    </p>
    <ul style="margin:0 0 1rem;padding-left:1.2rem;font-size:0.9rem;color:var(--hq-text)">
      <li>Site: <?= $siteName ?></li>
      <li>Created: <?= $created ?: '—' ?></li>
      <li>Last modified: <?= $updated ?: '—' ?></li>
    </ul>
    <form method="post" action="/hq/updates/identity" style="display:flex;flex-wrap:wrap;gap:0.6rem;align-items:end">
      <div>
        <label style="display:block;font-size:0.8rem;color:var(--hq-muted);margin-bottom:0.25rem">Site name</label>
        <input type="text" name="site_name" value="<?= $siteName ?>" style="padding:0.45rem 0.65rem;border:1px solid var(--hq-border);border-radius:8px;background:var(--hq-input-bg);color:var(--hq-text)">
      </div>
      <button type="submit" class="btn-primary" style="padding:0.5rem 1rem;border-radius:8px;border:none;background:var(--hq-accent);color:#fff;cursor:pointer">Refresh identity files</button>
    </form>
  </div>
</section>
