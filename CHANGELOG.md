# Changelog

All notable changes to Mova CMS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
