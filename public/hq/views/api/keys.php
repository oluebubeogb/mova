<?php
use Mova\Security\Csrf;
use Mova\Api\RestApi;

$scopeDefs = RestApi::SCOPES;
$base = rtrim((string) \Mova\Core\Bootstrap::config('app_url', ''), '/') . RestApi::PREFIX;
?>
<header class="hq-page-head">
    <div>
        <h1>API keys</h1>
        <p class="hq-page-sub">Let other apps read and write your content through the REST API. Each key only gets the scopes you tick.</p>
    </div>
</header>

<?php if (!empty($newKey)): ?>
<div class="alert alert-success apk-new" role="status">
    <div class="apk-new-head"><i class="fa-solid fa-key" aria-hidden="true"></i> <strong>Copy your new key now</strong> — for security it is shown only once.</div>
    <div class="apk-new-row">
        <code id="apk-new-value" class="apk-new-value"><?= htmlspecialchars($newKey) ?></code>
        <button type="button" class="btn btn-secondary btn-sm" id="apk-copy" data-tip="Copy to clipboard"><i class="fa-regular fa-copy" aria-hidden="true"></i> Copy</button>
    </div>
</div>
<?php endif; ?>

<div class="apk-grid">
<section class="panel apk-create">
    <h2 class="apk-h">Create a key</h2>
    <form method="post" action="/hq/api-keys">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label for="apk-name">Name</label>
            <input type="text" id="apk-name" name="name" required maxlength="80" placeholder="Mobile app, headless site…">
        </div>
        <fieldset class="form-group apk-scopes">
            <legend>Scopes</legend>
            <?php foreach ($scopeDefs as $scope => $desc): ?>
            <label class="apk-scope">
                <input type="checkbox" name="scopes[]" value="<?= htmlspecialchars($scope) ?>" <?= $scope === 'content:read' ? 'checked' : '' ?>>
                <span><code><?= htmlspecialchars($scope) ?></code><small><?= htmlspecialchars($desc) ?></small></span>
            </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="form-group">
            <label for="apk-exp">Expires</label>
            <select id="apk-exp" name="expires_days">
                <option value="0">Never</option>
                <option value="30">In 30 days</option>
                <option value="90" selected>In 90 days</option>
                <option value="365">In 1 year</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Generate key</button>
    </form>
</section>

<section class="panel apk-docs">
    <h2 class="apk-h">Quick start</h2>
    <p class="apk-muted">Send the key in the <code>Authorization</code> header (or <code>X-Api-Key</code> if your host strips it). Responses are JSON; limit is <?= (int) RestApi::RATE_LIMIT ?> requests per minute per key.</p>
<pre class="apk-code"><code>curl -H "Authorization: Bearer YOUR_KEY" \
  <?= htmlspecialchars($base) ?>/content?per_page=10</code></pre>
    <table class="data-table apk-endpoints">
        <thead><tr><th>Endpoint</th><th>Scope</th></tr></thead>
        <tbody>
            <tr><td><code>GET /api/v1/content</code> <small>?type=&amp;status=&amp;search=&amp;page=&amp;per_page=</small></td><td><code>content:read</code></td></tr>
            <tr><td><code>GET /api/v1/content/{id}</code></td><td><code>content:read</code></td></tr>
            <tr><td><code>GET /api/v1/search?q=</code></td><td><code>content:read</code></td></tr>
            <tr><td><code>POST /api/v1/content</code></td><td><code>content:write</code></td></tr>
            <tr><td><code>PATCH /api/v1/content/{id}</code></td><td><code>content:write</code></td></tr>
            <tr><td><code>DELETE /api/v1/content/{id}</code> <small>moves to Trash</small></td><td><code>content:write</code></td></tr>
            <tr><td><code>GET /api/v1/media</code>, <code>/media/{id}</code></td><td><code>media:read</code></td></tr>
            <tr><td><code>GET /api/v1/categories</code>, <code>/tags</code></td><td><code>taxonomy:read</code></td></tr>
            <tr><td><code>GET /api/v1</code>, <code>/site</code></td><td><code>site:read</code></td></tr>
        </tbody>
    </table>
    <p class="apk-muted">Keys without <code>content:write</code> only ever see published content. Errors: <code>401</code> missing/invalid key, <code>403</code> missing scope, <code>429</code> rate limited.</p>
</section>
</div>

<h2 class="apk-h" style="margin-top:1.5rem;">Your keys</h2>
<?php if (empty($keys)): ?>
    <div class="hq-empty-state">
        <div class="hq-empty-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></div>
        <p>No API keys yet. Create one above to connect an app.</p>
    </div>
<?php else: ?>
<div class="panel" style="padding:0;overflow-x:auto;">
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Key</th>
            <th>Scopes</th>
            <th>Status</th>
            <th>Last used</th>
            <th>Expires</th>
            <th><span class="hq-sr-only">Actions</span></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($keys as $k):
        $sc = is_string($k['scopes']) ? (json_decode($k['scopes'], true) ?: []) : (array) $k['scopes'];
        $expired = !empty($k['expires_at']) && strtotime($k['expires_at']) < time();
        $st = $k['status'] === 'active' && $expired ? 'expired' : $k['status'];
    ?>
        <tr>
            <td><strong><?= htmlspecialchars($k['name']) ?></strong></td>
            <td><code><?= htmlspecialchars($k['key_prefix']) ?>…</code></td>
            <td><?php foreach ($sc as $s): ?><span class="apk-chip"><?= htmlspecialchars((string) $s) ?></span><?php endforeach; ?></td>
            <td><span class="status-chip status-<?= $st === 'active' ? 'published' : 'trash' ?>"><?= htmlspecialchars($st) ?></span></td>
            <td><?= $k['last_used_at'] ? htmlspecialchars(date('M j, Y H:i', strtotime($k['last_used_at']))) : '—' ?></td>
            <td><?= !empty($k['expires_at']) ? htmlspecialchars(date('M j, Y', strtotime($k['expires_at']))) : 'Never' ?></td>
            <td style="white-space:nowrap;text-align:right;">
                <?php if ($k['status'] === 'active'): ?>
                <form method="post" action="/hq/api-keys" style="display:inline;" onsubmit="return confirm('Revoke this key? Apps using it stop working immediately.');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <button type="submit" class="btn btn-ghost btn-sm">Revoke</button>
                </form>
                <?php endif; ?>
                <form method="post" action="/hq/api-keys" style="display:inline;" onsubmit="return confirm('Delete this key permanently?');">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm" aria-label="Delete key <?= htmlspecialchars($k['name']) ?>">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<style>
.apk-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr); gap: 1rem; align-items: start; }
@media (max-width: 960px) { .apk-grid { grid-template-columns: minmax(0, 1fr); } }
.apk-h { font-size: 1rem; margin: 0 0 .8rem; }
.apk-muted { color: var(--hq-muted); font-size: .88rem; }
.apk-scopes { border: 0; padding: 0; margin: 0 0 1rem; }
.apk-scopes legend { font-weight: 600; font-size: .85rem; margin-bottom: .4rem; }
.apk-scope { display: flex; gap: .6rem; align-items: flex-start; padding: .5rem .6rem; border-radius: 10px; cursor: pointer; font-weight: 400; transition: background-color .15s; }
.apk-scope:hover { background: var(--hq-row-hover); }
.apk-scope input { margin-top: .2rem; }
.apk-scope small { display: block; color: var(--hq-muted); font-size: .78rem; }
.apk-code { background: var(--hq-input-bg); border: 1px solid var(--hq-border); border-radius: 10px; padding: .7rem .9rem; overflow-x: auto; font-size: .8rem; }
.apk-endpoints code { font-size: .78rem; }
.apk-endpoints small { color: var(--hq-muted); }
.apk-chip { display: inline-block; font-size: .72rem; font-family: ui-monospace, monospace; padding: 2px 8px; margin: 1px 3px 1px 0; border-radius: 999px; background: rgba(91,92,226,.1); color: var(--hq-brand, #5B5CE2); }
.apk-new { display: grid; gap: .5rem; }
.apk-new-row { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }
.apk-new-value { user-select: all; word-break: break-all; flex: 1 1 260px; padding: .45rem .6rem; border-radius: 8px; background: rgba(0,0,0,.06); }
</style>
<script>
(function () {
  var b = document.getElementById('apk-copy'), v = document.getElementById('apk-new-value');
  if (!b || !v) return;
  b.addEventListener('click', function () {
    navigator.clipboard && navigator.clipboard.writeText(v.textContent.trim()).then(function () {
      if (window.HQToast) window.HQToast('Key copied', 'success'); b.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Copied';
    });
  });
})();
</script>
