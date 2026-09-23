# Mova CMS v1.1.3

**Release date:** 2026-09-23

## Highlights

### Gallery HQ integration
- **Galleries** appears in the Content workspace Line 2 nav and in global HQ search.
- Content hub workspace detection includes gallery pages.

### Auto slug
- New gallery forms auto-generate the public slug from the title (`/gallery/{slug}`). Manual edits stop auto-update.

### Exclude from gallery
- On **Media**, each image has an eye / eye-slash control to **exclude** it from the public explore stream and gallery search.
- Excluded images can still be added to a named gallery collection in HQ.
- Additive column: `media.exclude_from_gallery` (migration phase 9).

## Upgrade

1. Deploy files and open HQ once (phase 9 migration).
2. Mark any private or non-gallery uploads as excluded in Media.
3. Use Content → Galleries as before.

## Version

- **1.1.3**
