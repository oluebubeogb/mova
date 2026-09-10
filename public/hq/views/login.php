<?php use Mova\Security\Csrf; ?>
<div class="auth-card">
    <h1>Mova HQ</h1>
    <p class="tagline">Welcome back. What are you moving today?</p>

    <?php
    $lockLeft = class_exists(\Mova\Auth\Auth::class) ? \Mova\Auth\Auth::lockoutRemaining() : 0;
    if ($lockLeft > 0 && empty($error)) {
        $error = 'Too many login attempts. Try again in ' . max(1, (int) ceil($lockLeft / 60)) . ' minute(s). (Limit: 10 per hour)';
    }
    ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/hq/login">
        <?= Csrf::field() ?>
        <div class="form-group">
            <label>Username or email</label>
            <input type="text" name="username" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-primary btn-block">Sign in</button>
    </form>
</div>
