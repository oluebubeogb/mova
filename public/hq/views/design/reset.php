<?php
use Mova\Security\Csrf;
?>
<div class="hq-page">
  <h1>Reset design defaults</h1>
  <p class="muted">Restore Mova factory defaults for style tokens, layout, or components. This overwrites your current settings for the selected area and clears the public cache. This page is intentionally separate so resets are not one click away on every design screen.</p>

  <?php if (!empty($_GET['reset'])): ?>
    <div class="alert alert-success">Restored Mova defaults for <strong><?= htmlspecialchars((string)$_GET['reset']) ?></strong>. Public cache cleared.</div>
  <?php endif; ?>

  <div class="card" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 style="margin-top:0;font-size:1.1rem;">Style tokens</h2>
    <p class="muted">Colors, typography, radius, shadow, density.</p>
    <form method="post" action="/hq/style/reset" onsubmit="return confirm('Reset style tokens to Mova defaults? This overwrites your current style settings.');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-secondary">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset style to defaults
      </button>
    </form>
  </div>

  <div class="card" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 style="margin-top:0;font-size:1.1rem;">Layout</h2>
    <p class="muted">Header, navigation, containers, mobile menu, footer.</p>
    <form method="post" action="/hq/layout/reset" onsubmit="return confirm('Reset layout to Mova defaults? This overwrites your current layout settings.');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-secondary">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset layout to defaults
      </button>
    </form>
  </div>

  <div class="card" style="padding:1.25rem;margin-bottom:1rem;">
    <h2 style="margin-top:0;font-size:1.1rem;">Components</h2>
    <p class="muted">Buttons, cards, heroes.</p>
    <form method="post" action="/hq/components/reset" onsubmit="return confirm('Reset components to Mova defaults? This overwrites your current components settings.');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-secondary">
        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Reset components to defaults
      </button>
    </form>
  </div>
</div>
