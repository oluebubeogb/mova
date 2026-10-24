<?php /* Section landing — Operations */ ?>
<section class="hq-landing">
  <div class="hq-landing-bg" aria-hidden="true">
    <div class="hq-landing-lines">
      <span></span><span></span><span></span>
    </div>
    <span class="hq-landing-glyph">∑</span>
    <span class="hq-landing-glyph">∫</span>
    <span class="hq-landing-glyph">π</span>
    <span class="hq-landing-glyph">∞</span>
    <span class="hq-landing-glyph">√</span>
    <span class="hq-landing-glyph">Δ</span>
  </div>

  <header class="hq-landing-header">
    <p class="hq-landing-kicker"><i class="fa-solid fa-server"></i> Operations</p>
    <h1 class="hq-landing-title">Operations workspace</h1>
    <p class="hq-landing-desc">Keep the platform healthy and secure — environment checks, authentication, backup & restore and AI usage.</p>
  </header>
<?php
// Admin notice: AI punctuation damage from the old JSON-recovery bug ("Nigeriau2019s").
$__trCount = 0;
if (\Mova\Auth\Auth::hasRole('owner', 'administrator')) {
    try {
        foreach ((new \Mova\AI\UnicodeRepairService())->scan() as $__f) {
            $__trCount += count($__f['hits']);
        }
    } catch (\Throwable $e) {
    }
}
if ($__trCount > 0): ?>
  <div class="alert alert-error" role="status" style="margin:0 0 1rem;">
    <strong>Text repair:</strong> <?= (int) $__trCount ?> AI punctuation glitch(es) like <code>Nigeriau2019s</code> found in your content.
    <a href="/hq/text-repair">Review and repair</a>
  </div>
<?php endif; ?>

  <div class="hq-landing-grid">
    <a class="hq-landing-card" href="/hq/health">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon is-green"><i class="fa-solid fa-heart-pulse"></i></div>
        <div class="hq-landing-card-body">
          <strong>Health</strong>
          <span>Environment, database, cache, mail, security</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>

    <a class="hq-landing-card" href="/hq/security">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <div class="hq-landing-card-body">
          <strong>Security</strong>
          <span>Two-factor auth and login history</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>

    <a class="hq-landing-card" href="/hq/backups">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon is-mix"><i class="fa-solid fa-cloud-arrow-up"></i></div>
        <div class="hq-landing-card-body">
          <strong>Backups</strong>
          <span>Backup and restore</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>
  
    <a class="hq-landing-card" href="/hq/updates">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon is-mix"><i class="fa-solid fa-arrows-rotate"></i></div>
        <div class="hq-landing-card-body">
          <strong>Updates</strong>
          <span>Core version check and public identity</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>

    <?php if (\Mova\Auth\Auth::hasRole('owner', 'administrator')): ?>
    <a class="hq-landing-card" href="/hq/text-repair">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon is-green"><i class="fa-solid fa-spell-check"></i></div>
        <div class="hq-landing-card-body">
          <strong>Text repair</strong>
          <span>Fix AI punctuation saved as code (u2019, u2011…)</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>
    <?php endif; ?>

    <a class="hq-landing-card" href="/hq/ai-usage">
      <div class="hq-landing-card-inner">
        <div class="hq-landing-icon"><i class="fa-solid fa-chart-column"></i></div>
        <div class="hq-landing-card-body">
          <strong>AI usage</strong>
          <span>Requests and tokens by day and feature</span>
        </div>
      </div>
      <i class="fa-solid fa-arrow-right hq-landing-arrow" aria-hidden="true"></i>
    </a>
  </div>
</section>
