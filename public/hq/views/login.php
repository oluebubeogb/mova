<?php
use Mova\Security\Csrf;

$__site = 'Mova';
try {
    if (class_exists(\Mova\Theme\DesignConfig::class)) {
        $__site = trim((string) \Mova\Theme\DesignConfig::setting('site_name', 'Mova')) ?: 'Mova';
    }
} catch (\Throwable $e) {
}
$lockLeft = class_exists(\Mova\Auth\Auth::class) ? \Mova\Auth\Auth::lockoutRemaining() : 0;
if ($lockLeft > 0 && empty($error)) {
    $error = 'Too many sign-in attempts. Try again in ' . max(1, (int) ceil($lockLeft / 60)) . ' minute(s).';
}
$__h = (int) date('G');
$__hello = $__h < 12 ? 'Good morning' : ($__h < 18 ? 'Good afternoon' : 'Good evening');
?>
<div class="auth-split">
    <aside class="auth-brand" aria-hidden="true">
        <svg class="auth-art" viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice" focusable="false">
            <defs>
                <radialGradient id="aG1" cx="20%" cy="15%" r="60%"><stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
                <radialGradient id="aG2" cx="85%" cy="90%" r="55%"><stop offset="0" stop-color="#22d3ee" stop-opacity=".35"/><stop offset="1" stop-color="#22d3ee" stop-opacity="0"/></radialGradient>
            </defs>
            <rect width="600" height="800" fill="url(#aG1)"/>
            <rect width="600" height="800" fill="url(#aG2)"/>
            <g fill="none" stroke="#fff" stroke-opacity=".14">
                <circle cx="470" cy="160" r="90"/><circle cx="470" cy="160" r="150"/><circle cx="470" cy="160" r="220"/>
                <path d="M-20 620 C 140 540, 260 700, 420 610 S 640 560, 660 600"/>
                <path d="M-20 660 C 140 580, 260 740, 420 650 S 640 600, 660 640" stroke-opacity=".08"/>
            </g>
            <g fill="#fff" fill-opacity=".5"><circle cx="560" cy="300" r="3"/><circle cx="90" cy="420" r="2"/><circle cx="380" cy="80" r="2.5"/><circle cx="200" cy="720" r="3"/></g>
        </svg>
        <div class="auth-brand-inner">
            <span class="auth-logo"><span>M</span> Mova HQ</span>
            <div class="auth-pitch">
                <p class="auth-kicker"><?= htmlspecialchars($__site) ?></p>
                <h2>Your whole site, one calm workspace.</h2>
                <ul>
                    <li><i class="fa-solid fa-pen-nib"></i> Write, schedule and publish pages</li>
                    <li><i class="fa-solid fa-wand-magic-sparkles"></i> Draft and polish with Mova AI</li>
                    <li><i class="fa-solid fa-shield-halved"></i> Roles, backups and health checks built in</li>
                </ul>
            </div>
            <p class="auth-foot">Tip: press <kbd>Ctrl</kbd> <kbd>K</kbd> anywhere in HQ to jump to any page.</p>
        </div>
    </aside>

    <main class="auth-main">
        <div class="auth-card auth-card--p5">
            <span class="auth-mark" aria-hidden="true">M</span>
            <h1><?= $__hello ?></h1>
            <p class="tagline">Sign in to <?= htmlspecialchars($__site) ?> HQ to keep things moving.</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error auth-alert" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> <span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>

            <form method="post" action="/hq/login" class="auth-form" novalidate>
                <?= Csrf::field() ?>
                <div class="form-group">
                    <label for="login-user">Username or email</label>
                    <div class="auth-input">
                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                        <input type="text" id="login-user" name="username" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="login-pass">Password</label>
                    <div class="auth-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input type="password" id="login-pass" name="password" required autocomplete="current-password">
                    </div>
                    <p class="auth-caps" id="login-caps" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> Caps Lock is on</p>
                </div>
                <button type="submit" class="btn btn-primary btn-block auth-submit"><span>Sign in</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
            </form>
            <p class="auth-help"><i class="fa-regular fa-circle-question" aria-hidden="true"></i> <span>Forgot your password? Ask a site owner to reset it under <strong>People</strong>.</span></p>
        </div>
    </main>
</div>
<script>
(function () {
    var f = document.querySelector('.auth-form'); if (!f) return;
    var pw = document.getElementById('login-pass'), caps = document.getElementById('login-caps');
    function capsCheck(e) { if (e.getModifierState) caps.hidden = !e.getModifierState('CapsLock'); }
    pw.addEventListener('keydown', capsCheck); pw.addEventListener('keyup', capsCheck);
    pw.addEventListener('blur', function () { caps.hidden = true; });
    f.addEventListener('submit', function (e) {
        var bad = [].slice.call(f.querySelectorAll('input[required]')).filter(function (i) { return !i.value.trim(); });
        if (bad.length) { e.preventDefault(); bad[0].focus(); bad.forEach(function (i) { var w = i.closest('.auth-input'); w.classList.remove('is-shake'); void w.offsetWidth; w.classList.add('is-shake'); }); return; }
        var b = f.querySelector('.auth-submit'); b.classList.add('is-busy'); b.setAttribute('aria-busy', 'true');
    });
})();
</script>
