<?php
/**
 * Documentation — Mova public theme layout.
 * Shared chrome (head, header, footer, scripts) lives in resources/theme-parts via
 * Mova\Theme\SiteFrame; this file only picks the theme's own stylesheet/script.
 */
$F = \Mova\Theme\SiteFrame::build(get_defined_vars(), [
    'theme' => 'documentation',
    'css' => [$themePath . '/' . 'documentation.css'],
    'js' => [$themePath . '/' . 'documentation.js'],
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include $F['parts'] . '/head.php'; ?>
</head>
<body class="<?= htmlspecialchars($F['body_class']) ?>">
<?php include $F['parts'] . '/header.php'; ?>
<main class="site-main" id="main" tabindex="-1">
<?php if ($F['canvas'] || !$F['chrome']): ?>
<?php include $viewFile ?? ($themePath . '/' . $view . '.php'); ?>
<?php else: ?>
    <div class="container">
<?php include $viewFile ?? ($themePath . '/' . $view . '.php'); ?>
    </div>
<?php endif; ?>
</main>
<?php include $F['parts'] . '/footer.php'; ?>
<?php include $F['parts'] . '/scripts.php'; ?>
</body>
</html>
