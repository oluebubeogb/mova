# Mova CMS

**Content that moves.**

A modern, lightweight PHP CMS designed to feel like WordPress but cleaner and faster.  
Built for shared hosting, VPS, and Coolify / Docker.

- PHP 8.1+
- SQLite by default (no database server required)
- Built-in multi-site support
- Themes & plugins
- Image processing (WebP, variants)
- Full HQ admin panel

---

## Quick Start (Coolify / Docker – recommended)

1. Create a new private or public Git repository.
2. Push this repository as the root.
3. In Coolify:
   - New Application → connect the Git repo
   - Build Pack: **Dockerfile**
   - Port: **80**
   - Persistent volumes:
     - `/var/www/storage` → `mova-storage`
     - `/var/www/public/mova-uploads` → `mova-uploads`
4. Deploy.
5. Visit `https://your-domain/hq/install` and create your owner account.

---

## Manual VPS / Shared Hosting

### Standard layout (recommended)

```
mova/
├── app/
├── config/
├── storage/          ← must be writable
├── mova-themes/
├── mova-plugins/
├── public/           ← document root
│   ├── index.php
│   ├── assets/
│   ├── hq/
│   └── mova-uploads/ ← must be writable
├── Dockerfile
├── nginx.conf
└── supervisord.conf
```

1. Point your web server document root to the `public/` folder.
2. Make `storage/` and `public/mova-uploads/` writable (775 or 755).
3. Open `/hq/install`.

### Flat layout (shared hosting / public_html)

If your host only gives you a `public_html` folder, you can flatten the structure (move everything from `public/` up one level). The config auto-detects this.

Requirements: PHP 8.1+, SQLite (pdo_sqlite), GD, Apache mod_rewrite (or Nginx equivalent).

---

## Updating Mova

### If you forked or cloned this repo

```bash
# 1. Add the upstream remote (only once)
git remote add upstream https://github.com/YOUR-USERNAME/mova.git   # or the official URL

# 2. Fetch latest changes
git fetch upstream

# 3. Merge updates into your main branch
git checkout main
git merge upstream/main

# 4. Push to your own repo
git push origin main
```

Then redeploy in Coolify (or pull on your VPS).

**Important:** Never put real site content, uploads, or the SQLite database into Git.  
They are already ignored via `.gitignore`.

### Safe update checklist

1. Backup your `storage/` folder and database.
2. Pull / merge the new core.
3. Clear the page cache (or just let it expire).
4. Visit `/hq` — Mova will run any pending schema migrations automatically.

---

## Multi-site

Mova includes multi-site support:

- Go to **HQ → Sites**
- Add a new site and set its domain
- Each site can use a different theme

Content is currently shared in one database. Full isolation per site is planned for a future release.

---

## Development

```bash
# Local Docker test
docker build -t mova .
docker run -d -p 8080:80 \
  -v $(pwd)/storage:/var/www/storage \
  -v $(pwd)/public/mova-uploads:/var/www/public/mova-uploads \
  mova
```

Open http://localhost:8080/hq/install

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for how to contribute.

Maintainers: see [RELEASE_CHECKLIST.md](RELEASE_CHECKLIST.md) when cutting a new version.

## License & Credits

Mova CMS – open core.  
Feel free to use it for personal and commercial projects.

---

## Support

- Open an issue on the repository
- Check the HQ → Docs section after install
