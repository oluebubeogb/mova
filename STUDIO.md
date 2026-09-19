# Studio (integrated)

Studio is a multi-column content workspace (structure + Elements-style styling + Monaco HTML/CSS/JS + live preview).

## Routes
- `/hq/studio` — picker / new blank
- `/hq/studio/{id}` — editor
- `POST /hq/studio/{id}/save` — AJAX save

## Files added
- `public/hq/routes/studio.php`
- `public/hq/views/studio/index.php`
- `public/hq/views/studio/editor.php`
- `public/assets/css/studio.css`
- `public/assets/js/studio.js`

## Already patched
- `public/hq/index.php` — route registration
- `public/hq/helpers.php` — Content workspace
- `public/assets/js/hq-nav.js` — nav item
- `public/assets/js/hq-search-index.js` — search
- `public/hq/views/landings/content.php` — hub card

## Data
Saves to the same fields as Dev Mode: `body`, meta `raw_css`, `raw_js`, `editor_mode=studio`.
