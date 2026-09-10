<?php
use Mova\Security\Csrf;
$requirements = $requirements ?? null;
$step = $step ?? 'schema';
?>
<div class="auth-card" style="max-width:520px;">
    <h1>Install Mova</h1>
    <p class="tagline">Content that moves.</p>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($notice)): ?>
        <div class="alert" style="background:color-mix(in srgb, var(--hq-warning) 12%, var(--hq-surface));border:1px solid color-mix(in srgb, var(--hq-warning) 40%, var(--hq-border));padding:0.75rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:0.9rem;">
            <?= htmlspecialchars($notice) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($placeholders_moved)): ?>
        <div style="margin:0 0 1rem;padding:0.85rem 1rem;border-radius:10px;border:1px solid color-mix(in srgb, var(--hq-success) 35%, var(--hq-border));background:color-mix(in srgb, var(--hq-success) 10%, var(--hq-surface));font-size:0.9rem">
            <strong>Host placeholder removed.</strong>
            Renamed <?= count($placeholders_moved) ?> file(s)
            (<?= htmlspecialchars(implode(', ', array_column($placeholders_moved, 'from'))) ?>).
            Copies are in <code>storage/host-placeholders/</code>.
        </div>
    <?php endif; ?>

    <?php if ($requirements): ?>
        <?php
            $met = array_filter($requirements['items'], fn($i) => $i['ok']);
            $missing = array_filter($requirements['items'], fn($i) => !$i['ok']);
        ?>
        <div class="req-box" style="margin:1rem 0;text-align:left;font-size:0.85rem;">
            <strong style="display:block;margin-bottom:0.5rem;">Environment check</strong>

            <?php if ($met): ?>
                <p style="margin:0 0 0.35rem;color:#15803d;font-size:0.8rem;font-weight:600;">Met (<?= count($met) ?>)</p>
                <ul style="list-style:none;padding:0;margin:0 0 0.85rem;">
                    <?php foreach ($met as $item): ?>
                        <li style="padding:0.2rem 0;display:flex;gap:0.5rem;align-items:flex-start;">
                            <span style="color:#15803d">✓</span>
                            <span>
                                <?= htmlspecialchars($item['label']) ?>
                                <br><span style="color:var(--hq-muted);font-size:0.75rem;"><?= htmlspecialchars($item['detail']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($missing): ?>
                <p style="margin:0 0 0.35rem;color:#b45309;font-size:0.8rem;font-weight:600;">Missing or needs attention (<?= count($missing) ?>)</p>
                <ul style="list-style:none;padding:0;margin:0 0 0.5rem;">
                    <?php foreach ($missing as $item): ?>
                        <li style="padding:0.2rem 0;display:flex;gap:0.5rem;align-items:flex-start;">
                            <span style="color:<?= !empty($item['required']) && ($item['group'] ?? '') === 'disk' ? '#b91c1c' : '#b45309' ?>">✗</span>
                            <span>
                                <?= htmlspecialchars($item['label']) ?>
                                <?php if (!empty($item['required']) && ($item['group'] ?? '') === 'php'): ?>
                                    <em style="color:var(--hq-muted);font-size:0.75rem;"> (recommended)</em>
                                <?php elseif (!empty($item['required'])): ?>
                                    <em style="color:#b91c1c;font-size:0.75rem;"> (needed for install)</em>
                                <?php else: ?>
                                    <em style="color:var(--hq-muted);font-size:0.75rem;"> (optional)</em>
                                <?php endif; ?>
                                <br><span style="color:var(--hq-muted);font-size:0.75rem;"><?= htmlspecialchars($item['detail']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p style="margin:0.5rem 0 0;color:var(--hq-muted);font-size:0.78rem;line-height:1.4;">
                    PHP extensions cannot be turned on from <code>.htaccess</code> or a site <code>php.ini</code> —
                    your host (or server admin) must enable them. You can still proceed; some features may be limited until then.
                    On a VPS: <code>sudo bash bin/install-php-deps.sh</code>
                </p>
            <?php else: ?>
                <p style="margin:0;color:#15803d;font-size:0.8rem;">All checks passed.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 'schema'): ?>
        <p>Create the database and continue to your owner account.</p>
        <form method="post" action="/hq/install">
            <?= Csrf::field() ?>
            <?php if ($requirements && empty($requirements['php_ok'])): ?>
                <label style="display:flex;gap:0.5rem;align-items:flex-start;margin:0.75rem 0;font-size:0.85rem;text-align:left;">
                    <input type="checkbox" name="force_install" value="1" checked style="margin-top:0.2rem;">
                    <span>Proceed with missing PHP items. I understand some features may not work until the host enables them.</span>
                </label>
            <?php endif; ?>
            <?php if ($requirements && empty($requirements['disk_ok'])): ?>
                <label style="display:flex;gap:0.5rem;align-items:flex-start;margin:0.75rem 0;font-size:0.85rem;text-align:left;">
                    <input type="checkbox" name="force_install" value="1" style="margin-top:0.2rem;">
                    <span>Proceed anyway even though <code>storage/</code> may not be writable (install will likely fail).</span>
                </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="width:100%;margin-top:0.5rem;">
                <?= ($requirements && (empty($requirements['php_ok']) || empty($requirements['disk_ok'])))
                    ? 'Proceed with install'
                    : 'Install database' ?>
            </button>
        </form>
    <?php else: ?>
        <p>Create the owner account.</p>
        <form method="post" action="/hq/install">
            <?= Csrf::field() ?>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required autofocus>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Create owner & finish</button>
        </form>
    <?php endif; ?>
</div>
