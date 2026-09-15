<?php
use Mova\Security\Csrf;
$variables = $variables ?? [];
$custom = $custom ?? [];
?>
<?php if (!empty($_GET['saved'])): ?>
    <div class="alert alert-success">Variables saved. Public cache cleared.</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
    <div class="alert alert-success">Custom variable removed.</div>
<?php endif; ?>

<p class="design-intro">
    Shared design values for a consistent site feel. Use them in CSS as <code>var(--max-width)</code>,
    in HTML/assemblies as <code>{{var:site_name}}</code>, and in JS as <code>window.MovaVars.site_name</code>.
</p>

<div class="toolbar" style="margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
    <a class="btn-primary" href="/hq/variables/new"><i class="fa-solid fa-plus"></i> Add variable</a>
    <a class="btn-ghost" href="/hq/variables/sheet"><i class="fa-solid fa-table"></i> Var sheet</a>
</div>

<details open style="margin-bottom:1.25rem;">
    <summary style="cursor:pointer;font-weight:600;margin-bottom:0.5rem;">How to use</summary>
    <ul style="margin:0.35rem 0 0;padding-left:1.25rem;line-height:1.55;font-size:0.9rem;">
        <li><strong>CSS</strong> (Dev Mode, Elements, content styles): <code>width: var(--max-width);</code> or custom <code>var(--mova-my-gap)</code></li>
        <li><strong>HTML / Assembly</strong>: <code>{{var:site_name}}</code> or legacy <code>{{site_name}}</code></li>
        <li><strong>JS</strong>: <code>window.MovaVars['brand_color']</code> (injected on public pages)</li>
        <li><strong>System</strong> vars come from Brand / Style / Layout — editing them on the Var sheet writes back to the original setting.</li>
        <li><strong>Custom</strong> vars are only stored here; delete a line on the sheet or use Delete on this list.</li>
    </ul>
</details>

<div class="media-grid" style="grid-template-columns:1fr;gap:0.5rem;">
    <?php foreach ($variables as $row):
        $isCustom = ($row['source'] ?? '') === 'custom';
        $src = (string) ($row['source'] ?? '');
        $readonly = !empty($row['readonly']);
        ?>
        <div class="media-card" style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:flex-start;padding:0.85rem 1rem;">
            <div style="flex:1;min-width:12rem;">
                <div style="display:flex;flex-wrap:wrap;gap:0.4rem;align-items:center;">
                    <code style="font-weight:600;"><?= htmlspecialchars($row['name']) ?></code>
                    <?php if ($isCustom): ?>
                        <span class="el-tag-badge" style="font-size:0.7rem;background:#dbeafe;color:#1e40af;padding:0.1rem 0.4rem;border-radius:4px;">custom</span>
                    <?php else: ?>
                        <span class="el-tag-badge" style="font-size:0.7rem;background:#f1f5f9;color:#475569;padding:0.1rem 0.4rem;border-radius:4px;" title="<?= htmlspecialchars($src) ?>">system</span>
                    <?php endif; ?>
                    <?php if ($readonly): ?>
                        <span style="font-size:0.7rem;opacity:0.7;">readonly</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:0.85rem;color:var(--hq-muted);margin-top:0.25rem;">
                    <?= htmlspecialchars($row['description'] ?? '') ?>
                    <?php if ($src && !$isCustom): ?>
                        <span title="Declared at"> · <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($src) ?></span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:0.35rem;font-family:var(--font-mono,ui-monospace,monospace);font-size:0.85rem;">
                    <?= htmlspecialchars($row['value']) ?>
                </div>
                <div style="margin-top:0.35rem;font-size:0.75rem;opacity:0.75;">
                    CSS: <code><?= htmlspecialchars(\Mova\Theme\VariableService::toCssName($row['name'])) ?></code>
                    · HTML: <code>{{var:<?= htmlspecialchars($row['name']) ?>}}</code>
                </div>
            </div>
            <?php if ($isCustom): ?>
                <form method="post" action="/hq/variables/delete" onsubmit="return confirm('Delete this custom variable?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="name" value="<?= htmlspecialchars($row['name']) ?>">
                    <button type="submit" class="btn-ghost btn-sm" style="color:var(--hq-danger,#b91c1c);"><i class="fa-solid fa-trash"></i></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php if (!$variables): ?>
    <p class="empty">No variables yet.</p>
<?php endif; ?>
