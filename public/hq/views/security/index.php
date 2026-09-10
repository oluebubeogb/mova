<?php
use Mova\Security\Csrf;
$secretOnce = $_SESSION['_mova_totp_secret_once'] ?? null;
unset($_SESSION['_mova_totp_secret_once']);
?>

<div class="toolbar">
    <h2 style="margin:0;font-size:1rem;">Security center</h2>
</div>

<div class="form-layout">
    <div class="form-main">
        <div class="panel">
            <h3>Two-factor authentication</h3>
            <?php if (!empty($user['totp_enabled'])): ?>
                <p style="color:var(--hq-success);">2FA is enabled for your account.</p>
                <?php if ($secretOnce): ?>
                    <div class="alert">Save this secret (show-once): <code><?= htmlspecialchars($secretOnce) ?></code></div>
                <?php endif; ?>
                <form method="post" action="/hq/security">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="disable_2fa">
                    <button type="submit" class="btn-danger">Disable 2FA</button>
                </form>
            <?php else: ?>
                <p style="font-size:0.9rem;color:var(--hq-muted);">Enable a TOTP secret for your account. Full authenticator app pairing can be refined later.</p>
                <form method="post" action="/hq/security">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="enable_2fa">
                    <button type="submit" class="btn-primary">Enable 2FA</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="panel" style="margin-top:1.25rem;">
            <h3>Login history</h3>
            <?php if (empty($logins)): ?>
                <p class="empty">No history yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>When</th><th>User</th><th>IP</th><th>Result</th></tr></thead>
                    <tbody>
                    <?php foreach ($logins as $l): ?>
                        <tr>
                            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($l['created_at']))) ?></td>
                            <td><?= htmlspecialchars($l['username'] ?? '') ?></td>
                            <td><?= htmlspecialchars($l['ip_address'] ?? '') ?></td>
                            <td><?= !empty($l['success']) ? 'OK' : 'FAIL' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <aside class="form-sidebar">
        <div class="panel">
            <h3>Mail suppressions</h3>
            <form method="post" action="/hq/security" style="margin-bottom:1rem;">
                <?= Csrf::field() ?>
                <input type="hidden" name="action" value="suppress">
                <div class="form-group">
                    <input type="email" name="email" required placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <input type="text" name="reason" placeholder="bounce / complaint / manual">
                </div>
                <button type="submit" class="btn-primary btn-block">Add</button>
            </form>
            <?php if (!empty($suppressions)): ?>
                <ul style="list-style:none;padding:0;margin:0;font-size:0.85rem;">
                    <?php foreach ($suppressions as $s): ?>
                        <li style="display:flex;justify-content:space-between;gap:0.5rem;padding:0.35rem 0;border-bottom:1px solid var(--hq-border);">
                            <span><?= htmlspecialchars($s['email']) ?></span>
                            <form method="post" action="/hq/security">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="action" value="unsuppress">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn-ghost" style="font-size:0.75rem;">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </aside>
</div>
