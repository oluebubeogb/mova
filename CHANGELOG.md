# Changelog

All notable changes to Mova CMS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.2] - 2026-10-24

### Fixed
- **HTTP 500 when opening any saved item in the editor** (old pages, dev-mode pages, visual posts, AI drafts, new drafts after the first save) after upgrading on Docker / PHP-FPM. The image runs OPcache with `validate_timestamps=0`, so after files were replaced in place the FPM pool kept serving the *old* cached `public/hq/helpers.php` while compiling the *new* `public/hq/views/content/edit.php`, whose Revisions panel calls `hqTimeTag()` (added in 1.2.x) -> `Call to undefined function hqTimeTag()`. `/hq/content/new` kept working because it has no revisions. Production also ran with `error_reporting(0)` and the image logged to a file inside the container, so the 500 was blank and unlogged.
- Dev-mode editor (mova-dev-editor plugin): Monaco web worker failed with "importScripts ... URL is invalid" (relative path inside a Blob worker); now resolved to an absolute URL, as Studio already did.

### Added
- `app/Core/ErrorHandler.php`: fatals and uncaught exceptions are logged to `storage/logs/mova-errors.log` **and** stderr (docker logs) with a reference id, request method and path (no query string), file:line and trace; secrets (keys, tokens, passwords) are masked. Visitors get a friendly "Something went wrong - Ref XXXXXXXX" page (header `X-Mova-Error-Ref`); HQ shows admins where to look. Details only in debug mode.
- `app/Core/Runtime.php`: code fingerprint check - on the first web request after a code change Mova calls `opcache_reset()` (and re-issues the GET once), logs `mova.upgrade`, and a stale-code fatal (undefined function/class, redeclare) also triggers a reset (`mova.opcache`). Future in-place upgrades no longer need a manual PHP-FPM restart.
- Schema-version-gated, idempotent migrations: state (`mova_schema_state`, schema 14) is stored in settings, migrations run once per code change, failures are logged (`mova.migration`, `storage/cache/.mova-migration-error.json`) and retried on the next request instead of breaking pages.
- `bin/mova-doctor.php`: CLI health check (PHP version and extensions, OPcache settings, writable dirs and SQLite files, schema/migrations, HQ helpers, `php -l` of core files, recent errors). Flags: `--migrate`, `--reset-opcache`, `--json`, `--no-lint`. Exit code 1 on blocking problems.
- HQ -> Health: new "Upgrade & runtime" panel with the same checks.
- mbstring polyfills (`app/Core/polyfills.php`) so HQ keeps working on hosts without ext-mbstring.
- Clear message (and log line) when `config/config.php` is missing after replacing the site folder.

### Changed
- Editor: optional panels (Revisions, Related, Unicode notice) are isolated - if one fails it shows "could not be loaded (ref ...)" and is logged; editing and saving still work.
- Dockerfile: `opcache.validate_timestamps=1` with `revalidate_freq=2`, and PHP errors go to stderr (docker logs) instead of `/var/log/php_errors.log` inside the container. Rebuild the image to pick this up (also needed for the 1.2.x ai-worker in supervisord and the nginx PWA block).

### Upgrade notes (Docker)
1. Keep `config/` and `storage/`; replace the other files.
2. `docker compose build && docker compose up -d` (or at least `docker compose restart <php service>` to clear the stale OPcache).
3. `docker compose exec -u www-data <php service> php bin/mova-doctor.php` (add `--migrate` if it reports pending migrations).

## [1.2.1] - 2026-10-24

### Fixed (blog and gallery settings are respected again)
- **Blog styles follow HQ again.** The 1.2.0 card CSS had restyled every template into image-top cards, forced a featured first card on blog pages, and forced tone chips and dates into default cards. That made the HQ *Template* setting look hardcoded.
  - The default, modern and minimal templates render as before 1.2.0, including the `!important` embed guards.
  - The 1.2.0 visual polish is kept as cosmetic-only rules.
- **Embedded blogs use their own settings.** Shortcodes with extra attributes (for example `layout=`) used to bypass the blog's template. Now each embed renders with its own blog's template and options, so several blogs on one page each look their own way.
- **Embed limit.** JS-mounted embeds now use the blog's *Embed limit* (previously a fixed 6).
- **Gallery carousel height.** An unset *Max height* now defaults to 60vh (was 20vh, which made a thin strip). A non-auto *Aspect* now also sizes the carousel (capped by max height), grid cells and collage rows.
- **Space above the header.** Dev Mode and kit pages (which bring their own header) no longer get the theme's top and bottom page padding. Pages that start with a full-bleed hero or `.alignfull` / `[data-full-bleed]` block get no top padding. Normal pages keep their spacing.
- Removed a grey band under the image of default horizontal cards. The modern template no longer shows a dangling "·" when the date is hidden.

### Added (blog Display options in HQ → Blogs → Edit)
- Columns (1–4 or auto), Date (auto/show/hide), Category style (classic/tones), Feature first post, plus switches for image, excerpt and category.
  - Defaults reproduce the previous look.
  - Stored in the blog's `embed_options`.
- Precedence: shortcode attribute > blog HQ setting > default. Example: `[blog id=1 template=minimal columns=1 show_image=0 limit=3]`. Supported attributes: `template`, `limit`, `columns`, `show_image`, `show_excerpt`, `show_category`, `show_date`, `chip_style`, `featured`.
- Options are applied server-side, so custom templates comply too.

## [1.2.0] - 2026-10-24

### Added (import pre-flight)
- **Required plugins.** The dry run reads `manifest.features.plugins` and dev-mode signals in the content (`editor_mode=dev`, raw CSS/JS meta). It lists the plugins that are installed but inactive, or missing.
  - HQ: an *Activate required plugins* switch in the preview.
  - CLI: `--activate-plugins`.
  - Activation runs when the import finishes. **Undo** deactivates only the plugins that this import turned on.
- **Absolute live-host URLs.** The dry run counts `https://<pack site host>/…` URLs in the content, meta, settings, design and assemblies.
  - HQ: *Rewrite as relative* switch. CLI: `--rewrite-urls`.
  - Only URLs on the pack's own host are rewritten. Other hosts are untouched.
- HQ preview: a "Before you import" panel that summarises both checks.

### Added (UI kit: `mova-ui.css` / `mova-ui.js`, every theme, CSP-safe, no web fonts)
- **Tokens.** Soft tint and shade scales via `color-mix()`:
  - `--c-primary-50…900`;
  - `--c-secondary-*`, `--c-accent-*` and `--c-success-*`;
  - `--surface-soft` and `--surface-tint`.
- **Semantic green.** New `--color-success` token (light and dark) in `DesignConfig`.
- **Motion.** The kit reuses the core `data-reveal` system from `sections.css` and `sections.js`.
  - Below-the-fold blog cards, related posts, post extras, prose headings, figures, quotes and tables, and embedded blogs and galleries fade up with a stagger.
  - Above-the-fold content is never hidden (no LCP delay).
  - Nothing is hidden when JS is off.
  - Everything is off under `prefers-reduced-motion`.
  - Page fades use `@view-transition` (no-preference only).
  - Autoplay videos pause off-screen. Under reduced motion they stop and show controls.
- **Opt-in utilities:**
  - `.mx-lift`, `.mx-glow`, `.mx-underline` and `.mx-sheen`;
  - `.mx-btn-soft` and `.mx-btn-outline`;
  - `.mx-text-gradient`, `.mx-soft` and `.mx-tint`;
  - `.mx-chip[data-tone=0-5]`.
  - Counters (`data-count`) and marquees keep using `sections.js`.
- **Prose (`.mova-prose`, visual-editor bodies).**
  - Pasted inline `font-size`, `font-family` and `line-height` are neutralised.
  - Near-black text and white highlights are neutralised, so they stay readable in dark mode. Intentional colours are kept.
  - Coloured list markers, soft `hr`, overflow-safe tables and code.
- **Posts** (`post`/`article`/`news`), new `Mova\Theme\PostExtras`:
  - reading progress bar;
  - auto table of contents for 3 or more headings;
  - a category chip that links to the blog with the same slug;
  - share links (X, LinkedIn, WhatsApp, email, plus copy link when the clipboard is available);
  - previous and next post;
  - "Keep reading" related posts by category.
- **Blog cards (`mova-blog.css` v7):**
  - image-top cards with a 16:9 `object-fit` fix (removes the grey strip);
  - category chips coloured per category;
  - a featured first card on the blog page;
  - hover lift and image zoom;
  - a 3-line excerpt;
  - an empty state;
  - a contained gradient page head.
- **Shortcode layouts.** `[blog id=N layout=cards|list|compact limit=3]` is rendered on the server (works inside dev pages and assemblies).
- **Gallery.** An empty gallery shows a "Gallery coming soon" card instead of "No images".
- **Default theme.** Glass sticky header that shrinks on scroll, an animated blue-to-green nav underline, button and card hover polish, a gradient footer accent, and dark-mode form inputs.
- **`Mova\Core\Time::fmt()`.** Post and card dates use the site time zone.

### Added (Dev Mode, mova-dev-editor 1.1.0)
- **Core `Mova\Theme\DevFallback`.** When the plugin is inactive, a full-document dev page is unwrapped instead of being nested inside the theme's `<html>`. Its styles and scripts plus `raw_css`/`raw_js` are kept.
- **`sync_color_scheme`** (default on). Dev CSS written with `@media (prefers-color-scheme: dark)` also follows the site's light/dark toggle, so it is not applied twice. Scoped selectors keep their qualifiers.
- `@import` rules in dev CSS are hoisted so they stay valid.
- **Per-page "Scroll animations" checkbox** (`dev_enhance`). It adds `.mx-enhance` so the UI kit reveals that page's sections. No restyling.

### Changed
- `VERSION` file (1.2.0) and `Bootstrap::version()`. The higher of the code version and `config.app_version` is used everywhere: packs, backups, health, manifest, identity, the AI gateway user-agent and `mova.json`.
- The default theme `blog.php` wraps the title and description in `.mova-blog-page-head`. Assets are versioned by file modification time.

### Fixed
- Category chips (`.mova-blog-cat`, `.mx-chip`) mix 55% tone with the text colour (was 72%) so bright tones such as cyan, gold and green pass WCAG AA contrast.
- Responsive images (`img.mova-img`) stay visible when JavaScript is off; the fade-in is gated on `(scripting: enabled)` and skipped under reduced motion, and `content-visibility:auto` was removed from images (it blanked off-screen images in print/full-page captures).
- `/search` no longer overflows horizontally at 390px (`min-width: 0` on the form).
- `bin/mova-pack.php repack` refreshes the manifest's per-table row counts from the edited `data/*.json`.
- Feedback form inputs use a dark surface in dark mode (site toggle and OS setting) instead of the light `#f8fafc` fallback; `--color-input-bg` still overrides.


### Added (site packs: selective Export / Import)
- **HQ → Operations → Backups → Export & import** (`/hq/backups/packs`, owners and administrators only, CSRF-protected). It appears in HQ search and in the Operations landing.
- **Export** writes a `.movapack` file. You pick groups (content by type, blogs, galleries, media, menus, taxonomy, design, non-secret settings, site AI knowledge, forms). Subscribers, form entries and comments are opt-in.
  - Packs never contain users, passwords, keys, secret settings, mail or integration credentials, sessions or login data, logs, page views, AI jobs or the search index.
  - The pack is downloaded and a copy is kept in `storage/exports/`.
  - CLI: `bin/mova-export.php`.
- **Import** takes an uploaded pack or one placed in `storage/imports/`.
  - Validation: zip, manifest, checksums, minimum Mova version, zip-slip, media types only. SVGs are sanitised.
  - Dry-run preview: update, add, unchanged, conflict and remove, matched by ID and slug+type. It shows a per-item override, changed fields and a body diff.
  - Options: add new (on), remove missing (off, needs typing REMOVE), per-group toggles.
  - Apply runs as a background job:
    1. auto backup
    2. single transaction with rollback
    3. staged media swap
    4. revisions
    5. cache clear, then search, AI index and asset-version refresh
  - History with who and when (Africa/Lagos time), one-click Undo, and audit entries.
  - CLI: `bin/mova-import.php --dry-run|--apply|--undo|--history`. `bin/mova-pack.php` handles offline info, verify, unpack and repack.
- `bin/ai-worker.php` also advances queued site-pack imports.

### Fixed (AI punctuation saved as code: `Nigeriau2019s`, `fastu2011changing`, `20u202f%`)
- Root cause: when a model's JSON reply was not strictly valid (e.g. raw newlines inside a long HTML body), `AiChatService::recoverActionsFromBrokenJson()` unescaped strings with `stripcslashes()`. That drops the backslash of `\uXXXX`, so `\u2019` became `u2019`. The smart-quote replacement in `parseJsonReply()` could also break JSON around curly quotes. Real characters (such as a literal `—`) were never affected.
- New `Mova\AI\AiText` handles AI text in one place:
  - `jsonUnescape()`: `json_decode` with a surrogate-safe fallback;
  - `repairJson()`: smart-quote delimiters, trailing commas and raw control characters, applied only outside or inside strings as appropriate;
  - `decodeLeakedEscapes()`: literal `\uXXXX` and surrogate pairs, skipping code, `<pre>` and `<script>`;
  - `normalizePunctuation()`: U+2011 becomes `-` and U+202F becomes a space. Curly quotes and dashes are kept.
- Every AI path now uses `AiText`: the HQ panel (text protocol, recovery and native tools), the editor AI assist, the site assistant, and the setup wizard (page HTML and section rewrites). `create_content` and `update_content` clean their payload before saving, as a safety net. JSON is stored and returned with `JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE`.
- **HQ → Operations → Text repair** (`/hq/text-repair`, admin only) and `php bin/fix-unicode-escapes.php` (`--apply`, `--id=N`) repair content that was already saved.
  - Dry run by default. Every match is listed with its context.
  - A database backup (plus a JSON file of the old values) is written to `storage/backups/` before any change.
  - Covers titles, excerpts, bodies, meta and revisions.
  - Only matches a letter or digit followed by `u` and a known punctuation code point. Years, URLs, attributes and code are not touched.
  - An admin notice appears on Operations and in the editor when matches exist.
- HQ asset version is now `20261023uni`.

### Changed (HQ Mova AI panel)
- Every HQ panel prompt now runs as a background job (worker, cron, loopback, poll and shutdown fallbacks). The 'Background' checkbox is gone, and `/hq/ai/chat` and `/hq/ai/jobs` always enqueue, so old clients can't skip the queue. Answers still appear inline with live progress. Normal chat jobs keep native tool calling; only coding jobs use the async long-running path.
- 'Plain chat' is now a **RAW** switch (`role=switch`, off by default, remembered per user in localStorage). When it is on, the AI gets a minimal neutral prompt with no tools, no HqMap and no site context.
- The composer footer shows a one-line 'Ctrl+J · Enter' hint ('⌘J' on Mac) next to the switch.
- Removed the jobs icon and jobs view from the panel header and the jobs badge from the FAB. Sessions remain, and a toast still announces finished jobs while the panel is closed.

### Added (Phase 5b: installable app / PWA)
- **HQ → Extend → App (PWA)** (`/hq/pwa`, admin only; listed in the nav, HQ search and the Mova AI HQ map via `RouteRegistry`). The page has: an enable switch; app name and short name (prefilled from the site name); theme and background colours (prefilled from the brand and theme tokens); an icon field (site logo, a URL or an upload); display mode; start URL (same-origin only, never `/hq` or `/api`); and an offline page switch. Install banner settings: on/off, message, show after a delay or after N page views, and snooze days after dismissal. It also shows a live home-screen and banner preview, tooltips instead of help text, an HTTPS warning and a notice when GD is missing.
- `app/Site/Pwa.php` serves these routes from PHP (works on shared hosting, no build step):
  - `/manifest.webmanifest` (`application/manifest+json`);
  - `/sw.js` from the site root (scope `/`, `Service-Worker-Allowed: /`, `Cache-Control: no-cache`);
  - `/pwa-offline`;
  - `/pwa-icon-{192,512,maskable,180}.png`, generated with GD into `storage/pwa/` (rounded "any" icon, full-bleed maskable with a safe zone, Apple 180);
  - `/pwa-icon.svg`.
  If the site has no logo, the icon is a lettermark in the theme colour, using the bundled Inter Bold in `resources/fonts/` (OFL). Without GD, the manifest uses the logo plus the SVG icon.
- Service worker:
  - network-first for HTML, falling back to the cached copy and then the offline page;
  - cache-first for static assets;
  - versioned caches (`mova-pages-*` / `mova-assets-*`): the version changes when PWA settings, content, settings or asset versions change, and old caches are deleted on activate.
  It never handles `/hq`, `/api`, requests made by HQ pages, non-GET or range requests, preview URLs, or responses marked private. Signed-in visitors get an `X-Mova-Private: 1` header, so their pages are never cached.
- Turning the app off serves a kill-switch `/sw.js` that deletes the `mova-*` caches and unregisters. Pages also run a small inline clean-up for visitors who still have the old worker.
- The install banner (`public/assets/js/pwa.js`, `public/assets/css/pwa.css`) uses each theme's tokens (colours, font, radius) in all 4 themes and all kits (it is injected from the shared `resources/theme-parts/head.php`):
  - uses `beforeinstallprompt`; iOS Safari gets Share > Add to Home Screen instructions;
  - is hidden when the app is installed or running standalone (`display-mode` or `navigator.standalone`) and on `appinstalled`;
  - remembers dismissal for N days and closes with Esc;
  - is a labelled `role="dialog"`, honours `prefers-reduced-motion`, and is skipped inside iframes and previews.
- `nginx.conf` routes the PWA paths to PHP ahead of the static-asset rule. `.htaccess` adds the `.webmanifest` MIME type.

### Fixed (Phase 5 regression fixes)
- Site AI: the Design and Knowledge layers are restored, with all previous fields.
- HQ navigation drills down one level at a time.
- The Mova AI button is fixed at the bottom right on every HQ page, and hides while you type on mobile.
- The Ctrl+K palette now matches the top search exactly and shares its recent history (max 3, remove one with ×, Clear all). The duplicate Quick actions and Recent pages groups are gone.
- Padding for row actions in the content list.
- Human-friendly times everywhere ("8 h ago · 23 Oct 2026, 2:15 PM WAT"), using a shared PHP helper (`hqTimeTag`) and a JS formatter (`hq-time.js`). The display timezone is set in Settings → General (default Africa/Lagos). Storage stays UTC.
- HQ "M" favicon (SVG, PNG, ICO, apple-touch-icon).

### Added (Phase 5: HQ polish, security, REST API)
- **REST API v1** (`app/Api/RestApi.php`, `/api/v1`): Bearer (or `X-Api-Key`) auth validated by `ApiKeyService::authenticate()` (`validate()` alias), a scope check on every route (`site:read`, `content:read`, `content:write`, `media:read`, `taxonomy:read`; legacy `read`/`write`/`*` honoured), per-key rate limit (120/60 s), JSON errors (401/403/404/405/429), method override for restrictive hosts. The HQ API keys page keeps its copy-once display and now offers the scopes. Documented in the README.
- **Dashboard** rebuilt: greeting hero with quick actions, status lights, storage ring (cached 15 min), AI usage stats, recent edits, a Quick setup checklist until setup is complete, faint background art.
- **Navigation**: Content sub-nav grouped into Library / Structure / Tools plus a More menu (same URLs); Quick setup and Docs moved to the user/help menu; duplicate Settings and AI usage tabs removed; `/hq/sections` alias; search palette ranking, AI chips, background-job badge/toast, friendly HQ 404.
- **Editors**: content list, AI assist sidebar, media library dropzone/search/filters and a "no alt text" badge, section thumbnails generated by `bin/make-section-thumbs.php`.
- **Login** redesigned as a split brand/sign-in layout with caps-lock hint, empty-field shake and busy state.
- **Documentation theme 2.1.0**: sticky "On this page" TOC sidebar with scroll-spy (collapses to a disclosure on narrow screens), dark styled code blocks with copy buttons, `kbd`/blockquote styling and its own teal accent (`#0e7490`, dark `#22d3ee`).
- `public/assets/css/hq-p5.css` / `public/assets/js/hq-p5.js`: Phase 5 design layer (brand indigo `#5B5CE2`, gradients, tooltips, hover/open animations, stripes).
- Font Awesome, CodeMirror and Monaco are bundled locally under `public/assets/vendor` (no CDN needed on shared hosting).

### Security (Phase 5)
- Capability gate on every HQ POST route (defence in depth on top of CSRF); viewer accounts are read-only everywhere.
- API keys are stored hashed, shown once, and masked afterwards; AI/image provider keys stay masked in Settings and an empty field keeps the stored value.

### Changed (Phase 5)
- One name everywhere: **Sections** (was Templates/Sections).
- Settings → AI: key and Test connection stay visible; URL, model, provider, tool calling and background jobs live under an "Advanced" disclosure that opens automatically when non-default.
- Dark theme splits accent tokens: `--hq-accent` (text-safe `#9A9BF6`) vs `--hq-accent-fill` (`#5B5CE2`) for solid fills behind white text; new `--hq-accent-text`, `--hq-success-text`, `--hq-danger-text`.

### Accessibility (Phase 5)
- `prefers-reduced-motion` honoured across HQ, `:focus-visible` rings, one `h1` per page, labels on icon-only buttons and inputs (colour tokens, SEO row checkboxes, Elements toolbars now `role=group` + `aria-pressed`), focusable scroll regions.
- axe-core WCAG 2 A/AA: 0 colour-contrast, label and ARIA violations on all HQ nav pages in light and dark (Monaco's own syntax colours excepted).
- Mobile: no horizontal overflow at 390 px on any HQ page; 40 px+ touch targets in Studio and the AI panel; data tables scroll inside their card.

### Added (Phase 4: AI)
- One AI provider config in HQ Settings with **Test connection**: health, key, model and a test reply. Keys are always masked.
- Native tool calling where the gateway supports it, with automatic fallback to the JSON-in-text action protocol.
- A **confirm step** with a from → to preview before theme, design and structural changes (`/hq/ai/action` returns 409 until confirmed).
- A real background worker: `bin/ai-worker.php` (cron / supervisord / `--daemon` / `--once` / `--status`), a signed loopback poke, a browser poll and a shutdown-function fallback, plus a heartbeat.
- Site AI retrieval over an FTS5 index of published content, with BM25 ranking plus title and term-frequency tie-breaks.
- Per-site usage headers (`X-Mova-Site`, `X-Mova-Site-Host`, `X-Mova-Feature`); HQ → AI usage shows the gateway's own totals.
- `AiHttp`: a curl-first HTTP client with a streams fallback for every AI call.

### Fixed (Phase 4)
- The mobile floating AI button no longer covers form fields. It hides while an input is focused, on public sites and in HQ.
- A legacy `config.php` `ai` block (RunPod direct, no key) silently overrode the gateway default after upgrade.
- Quick setup copy accepts model replies that list sections as separate JSON objects, a `{"sections": [...]}` wrapper, or fenced JSON.
- The CLI worker failed to load the setup-wizard classes. It now loads the wizard properly, and a failure hands Quick setup back to web requests.
- The Site AI public route did not load `SiteIndex`.

### Fixed
- HQ AI coding replies now instruct the model to emit correct Mova CSS variables (`var(--color-primary, …)`, `var(--color-bg, …)`, etc. with fallbacks) instead of invalid bare token names like `surface` or `text` as CSS values
- Coding system prompt documents the full built-in palette map (background → `--color-bg`), radius/spacing/shadow vars, custom `--{name}` / `--mova-*` vars, and scoped modern syntax aligned with public theme pages

### Improved
- HQ AI coding prompt adds **DESIGN QUALITY** rules for pricing/cards/UI: hierarchy, featured tier, CTAs, shadows, checklist features, responsive grid, and production-grade polish (helps smaller models like Qwen 2.5 7B produce stronger layouts)
- Coding chat completions use a slightly higher temperature (0.7 when max_tokens ≥ 3000) for more creative UI output while normal chat stays at 0.5

### Added
- **Design templates (Phase 1)**: seed library under `resources/templates/` with 3 pricing templates (saas-featured, compact-stack, horizontal-toggle), static SVG previews, and shared `/assets/css/templates.css` always loaded on public themes
- `TemplateService` — lists seeds, resolves optional `design_templates` DB overrides, builds AI prompt blocks, detects `template:id` from messages
- HQ API `GET /hq/ai/templates` catalog; chat/jobs accept optional `template_id`
- Schema migration phase 12: `design_templates` table for hybrid overrides

### Added (Phase 2)
- **Design Templates page** at `/hq/templates` (catalog + detail with live preview, static SVG thumbs, Use in AI)
- AI panel **guided steps** (intent → category → template → brief → generate); skip to free chat still works
- Nav + HQ search + HqMap entries for Templates; `template_id` on chat/jobs from panel

### Planned
- Further Studio panel refinements
- Additional content-type and assembly tooling

---

## [1.1.3] - 2026-09-23

### Added
- Media library **Exclude from gallery** control — hide individual images from the public `/gallery` explore stream and search (saved gallery membership is unchanged)
- HQ search and Content workspace Line 2 nav entries for **Galleries**

### Fixed
- Galleries reachable from content-hub contextual nav and global HQ search
- Gallery slug auto-fills from title on create (editable override)

### Changed
- Version bumped to **1.1.3**

---
## [1.1.2] - 2026-09-23

### Added
- **Public Gallery** (`/gallery`): reserved system route with sidebar of saved galleries, explore view (4 recent gallery cards + month-grouped image stream with “See more”), single-gallery pages, search, lightbox (next/prev), and slideshow
- HQ Galleries CRUD under Content hub (`/hq/galleries`) with media picker; schema tables `galleries` / `gallery_items`
- Gallery slug `gallery` reserved (cannot be used for content pages)

### Fixed
- Desktop header nav no longer overflows into the site title: nav limited to ~70% width and wraps to a second line when needed

### Changed
- Version bumped to **1.1.2** (`public/mova.json`, `config/config.php`)

---
## [1.1.0] - 2026-09-20

### Added
- **Studio** — multi-column content workspace (structure, Elements-style styling, Monaco HTML/CSS/JS, live preview)
  - Routes: `/hq/studio`, `/hq/studio/{id}`, AJAX save `POST /hq/studio/{id}/save`
  - Saves to the same fields as Dev Mode (`body`, `raw_css`, `raw_js`, `editor_mode=studio`)
- Site design variables injected into Studio preview for parity with the public site
  - CSS custom properties from DesignConfig / VariableService (`--color-text`, `--color-primary`, etc.)
  - `{{var:name}}` token substitution and `window.MovaVars` for content scripts
- Studio fullscreen toolbar control (CSS shell + optional native Fullscreen API)
- Release checklist and versioning guidance (`RELEASE_CHECKLIST.md`)

### Fixed
- Studio live preview now reliably applies sitewide CSS variables (e.g. `color: var(--color-text)`) so theme colors are not missing/black in the iframe
- Studio preview builds site variable stylesheet separately from content CSS so a content CSS parse issue cannot wipe design tokens
- Studio boot no longer skips the fullscreen (and other toolbar) handlers when an earlier init step throws
- Preview `data-theme` and `:root` overrides kept in sync with the light/dark preview toggle

### Changed
- Version bumped to **1.1.0** (`public/mova.json`, `config/config.php`)

---

## [1.0.0] - 2026-09-19

### Added
- Initial public layout of Mova CMS (PHP 8.1+, SQLite by default)
- HQ admin panel, themes, plugins, multi-site support
- Image processing (WebP, variants)
- Docker / Coolify-oriented deployment layout
- Design variables system (VariableService, DesignConfig CSS tokens)
- Content editing, media, mail, backup, and related core modules

---

[Unreleased]: https://github.com/oluebubeogb/mova/compare/v1.1.3...HEAD
[1.1.3]: https://github.com/oluebubeogb/mova/releases/tag/v1.1.3
[1.1.2]: https://github.com/oluebubeogb/mova/releases/tag/v1.1.2
[1.1.0]: https://github.com/oluebubeogb/mova/releases/tag/v1.1.0
[1.0.0]: https://github.com/oluebubeogb/mova/releases/tag/v1.0.0
