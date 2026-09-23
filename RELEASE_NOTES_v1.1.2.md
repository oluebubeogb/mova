# Mova CMS v1.1.2

**Release date:** 2026-09-23

## Highlights

### Public Gallery
A first-class gallery experience at **`/gallery`** (reserved system route — cannot be used as a content slug).

- **Explore view:** sidebar of saved galleries, full-width search, four recent gallery cards, then a chronological image stream grouped by month (soft target ~48 images; completes the last month up to ~72)
- **See more:** loads additional images without a full page reload
- **Single gallery pages:** `/gallery/{slug}` with title, description, date, image grid, and **Slideshow**
- **Lightbox:** next/prev, captions, dates (keyboard arrows + Escape)
- **Search:** titles, descriptions, captions, and dates with rich results
- **HQ:** Content hub → **Galleries** (`/hq/galleries`) — create/edit collections and pick media
- Schema: additive tables `galleries` and `gallery_items` (auto-migrated on boot)

### Header navigation
Desktop nav is limited to about **70%** of available width and **wraps** to a second line instead of overlapping the site title/logo.

## Upgrade notes

1. Deploy the new core files as usual.
2. Open HQ once so schema migration phase 8 runs (creates gallery tables).
3. Optionally add a nav link: `Gallery|/gallery` under **Design → Layout → Navigation**.
4. Create galleries under **Content → Galleries** after uploading media.

No database columns or tables are removed. Existing content and media are unchanged.

## Files of interest

| Path | Role |
|------|------|
| `app/Gallery/GalleryService.php` | Gallery CRUD + stream/search |
| `app/Core/Schema.php` | `migratePhase8` |
| `public/index.php` | `/gallery` routes |
| `mova-themes/default/gallery.php` | Public UI |
| `public/assets/css/gallery.css` / `js/gallery.js` | Styles + lightbox/slideshow |
| `public/hq/routes/galleries.php` | HQ routes |
| `public/assets/css/mova.css` | Nav wrap fix |

## Version

- `config/config.php` → `app_version` **1.1.2**
- `public/mova.json` / `mova.defaults.json` → **1.1.2**

## Git

```bash
git checkout main
git pull origin main
git tag -a v1.1.2 -m "Mova CMS v1.1.2"
git push origin main --tags
```

Then create a GitHub Release from tag `v1.1.2` using these notes.
