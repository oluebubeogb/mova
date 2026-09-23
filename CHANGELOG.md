# Changelog

All notable changes to Mova CMS are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
