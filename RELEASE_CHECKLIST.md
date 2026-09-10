# Release Checklist – Mova CMS

Use this checklist every time you prepare a new public release.

## 1. Before tagging

- [ ] All intended changes are merged into `main`
- [ ] Version number updated in:
  - [ ] `public/mova.json` → `"version"`
  - [ ] `config/config.php` → `'app_version'`
  - [ ] `README.md` (if the version is mentioned)
- [ ] Schema changes are additive and safe (never drop columns/tables in a minor release)
- [ ] `debug` is set to `false` in the default config
- [ ] `.gitignore` still correctly ignores:
  - `storage/*` (except `.gitkeep`)
  - `public/mova-uploads/*`
  - `*.sqlite`
  - `.env`
- [ ] Clean install tested (`/hq/install` works on a fresh volume)
- [ ] Update path tested (existing site + new core files)
- [ ] Docker image builds successfully
- [ ] Basic smoke test of HQ and frontend passes
- [ ] CHANGELOG.md updated (recommended)

## 2. Creating the release

```bash
# Make sure you are on main and up to date
git checkout main
git pull origin main

# Commit any final version bumps
git add .
git commit -m "Release vX.Y.Z"

# Create annotated tag
git tag -a vX.Y.Z -m "Mova CMS vX.Y.Z"

# Push
git push origin main --tags
```

- [ ] GitHub / GitLab Release created with release notes
- [ ] (Optional) Attach a clean ZIP of the tagged source

## 3. After release

- [ ] Verify the tag appears correctly on the repository
- [ ] Announce the release (if you have a channel / Discord / X)
- [ ] Update any external documentation if needed

## Versioning guideline

We follow a simple semantic versioning approach:

- **Major** (2.0.0) — Breaking changes
- **Minor** (1.1.0) — New features, backward compatible
- **Patch** (1.0.1) — Bug fixes and small improvements

For the first public versions, prefer frequent minor/patch releases over large jumps.
