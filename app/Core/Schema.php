<?php
/**
 * Mova CMS - Database Schema & Migrations
 */

namespace Mova\Core;

class Schema
{
    public static function install(): void
    {
        $db = Database::connection();

        // Users
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                avatar TEXT,
                role TEXT NOT NULL DEFAULT 'author',
                status TEXT NOT NULL DEFAULT 'active',
                last_login_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        // Content
        $db->exec("
            CREATE TABLE IF NOT EXISTS content (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL DEFAULT 'article',
                title TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                excerpt TEXT,
                body TEXT,
                status TEXT NOT NULL DEFAULT 'draft',
                author_id INTEGER,
                featured_image TEXT,
                template TEXT,
                canonical_url TEXT,
                visibility TEXT NOT NULL DEFAULT 'public',
                parent_id INTEGER,
                sort_order INTEGER DEFAULT 0,
                published_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (parent_id) REFERENCES content(id) ON DELETE SET NULL
            )
        ");

        // Content meta (SEO, custom fields)
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_meta (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                meta_key TEXT NOT NULL,
                meta_value TEXT,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                UNIQUE(content_id, meta_key)
            )
        ");

        // Categories
        $db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                description TEXT,
                parent_id INTEGER,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
            )
        ");

        // Tags
        $db->exec("
            CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL
            )
        ");

        // Content <-> Categories
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_categories (
                content_id INTEGER NOT NULL,
                category_id INTEGER NOT NULL,
                PRIMARY KEY (content_id, category_id),
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
            )
        ");

        // Content <-> Tags
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_tags (
                content_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (content_id, tag_id),
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            )
        ");

        // Media
        $db->exec("
            CREATE TABLE IF NOT EXISTS media (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filename TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                extension TEXT NOT NULL,
                size INTEGER NOT NULL,
                width INTEGER,
                height INTEGER,
                alt_text TEXT,
                path TEXT NOT NULL,
                variants TEXT, -- JSON
                uploaded_by INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Revisions
        $db->exec("
            CREATE TABLE IF NOT EXISTS revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                title TEXT,
                body TEXT,
                excerpt TEXT,
                meta TEXT, -- JSON snapshot
                author_id INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Comments
        $db->exec("
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                parent_id INTEGER,
                author_name TEXT NOT NULL,
                author_email TEXT NOT NULL,
                author_url TEXT,
                body TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                ip_address TEXT,
                user_agent TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
            )
        ");

        // Subscribers
        $db->exec("
            CREATE TABLE IF NOT EXISTS subscribers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                name TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                list_id INTEGER,
                subscribed_at TEXT NOT NULL,
                unsubscribed_at TEXT,
                token TEXT
            )
        ");

        // Settings
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL UNIQUE,
                setting_value TEXT,
                updated_at TEXT
            )
        ");

        // Audit log
        $db->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                entity_type TEXT,
                entity_id INTEGER,
                details TEXT,
                ip_address TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Page views (lightweight analytics)
        $db->exec("
            CREATE TABLE IF NOT EXISTS page_views (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER,
                path TEXT NOT NULL,
                referrer TEXT,
                user_agent TEXT,
                ip_hash TEXT,
                country TEXT,
                device TEXT,
                browser TEXT,
                viewed_at TEXT NOT NULL
            )
        ");

        // Login attempts (rate limiting)
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                username TEXT,
                attempted_at TEXT NOT NULL
            )
        ");

        // Indexes
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_slug ON content(slug)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_status ON content(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_type ON content(type)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_published ON content(published_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_author ON content(author_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_media_filename ON media(filename)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_page_views_content ON page_views(content_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_page_views_date ON page_views(viewed_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs(created_at)");

        // FTS for search
        $db->exec("
            CREATE VIRTUAL TABLE IF NOT EXISTS content_fts USING fts5(
                title, excerpt, body, content='content', content_rowid='id'
            )
        ");

        // Triggers to keep FTS in sync
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS content_ai AFTER INSERT ON content BEGIN
                INSERT INTO content_fts(rowid, title, excerpt, body)
                VALUES (new.id, new.title, new.excerpt, new.body);
            END
        ");
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS content_ad AFTER DELETE ON content BEGIN
                INSERT INTO content_fts(content_fts, rowid, title, excerpt, body)
                VALUES('delete', old.id, old.title, old.excerpt, old.body);
            END
        ");
        $db->exec("
            CREATE TRIGGER IF NOT EXISTS content_au AFTER UPDATE ON content BEGIN
                INSERT INTO content_fts(content_fts, rowid, title, excerpt, body)
                VALUES('delete', old.id, old.title, old.excerpt, old.body);
                INSERT INTO content_fts(rowid, title, excerpt, body)
                VALUES (new.id, new.title, new.excerpt, new.body);
            END
        ");
    }

    /**
     * V2 Phase 1 tables — safe to run on existing installs.
     */
    public static function migrate(): void
    {
        if (!self::isInstalled()) {
            return;
        }

        $db = Database::connection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS content_types (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT DEFAULT 'fa-file',
                is_system INTEGER NOT NULL DEFAULT 0,
                is_public INTEGER NOT NULL DEFAULT 1,
                schema_type TEXT DEFAULT 'WebPage',
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS content_fields (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                slug TEXT NOT NULL,
                field_type TEXT NOT NULL DEFAULT 'text',
                options TEXT,
                is_required INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (type_id) REFERENCES content_types(id) ON DELETE CASCADE,
                UNIQUE(type_id, slug)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS content_relations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                related_id INTEGER NOT NULL,
                relation_type TEXT NOT NULL DEFAULT 'related',
                sort_order INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (related_id) REFERENCES content(id) ON DELETE CASCADE,
                UNIQUE(content_id, related_id, relation_type)
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS api_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                key_hash TEXT NOT NULL,
                key_prefix TEXT NOT NULL,
                scopes TEXT,
                last_used_at TEXT,
                expires_at TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                created_by INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                url TEXT NOT NULL,
                events TEXT NOT NULL,
                secret TEXT,
                status TEXT NOT NULL DEFAULT 'active',
                last_status INTEGER,
                last_triggered_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS webhook_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                webhook_id INTEGER NOT NULL,
                event TEXT NOT NULL,
                payload TEXT,
                response_code INTEGER,
                response_body TEXT,
                success INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
            )
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_relations_content ON content_relations(content_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_relations_related ON content_relations(related_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_api_keys_prefix ON api_keys(key_prefix)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_webhook_deliveries_wh ON webhook_deliveries(webhook_id)");

        // Seed system content types if empty
        $count = Database::count('content_types');
        if ($count === 0) {
            $now = date('c');
            $defaults = [
                ['article', 'Article', 'Long-form writing', 'fa-newspaper', 'Article'],
                ['page', 'Page', 'Static pages', 'fa-file', 'WebPage'],
                ['guide', 'Guide', 'How-to guides', 'fa-book', 'Article'],
                ['documentation', 'Documentation', 'Technical docs', 'fa-book-open', 'TechArticle'],
                ['faq', 'FAQ', 'Frequently asked questions', 'fa-circle-question', 'FAQPage'],
                ['custom', 'Custom', 'Flexible content', 'fa-puzzle-piece', 'WebPage'],
            ];
            $order = 0;
            foreach ($defaults as $d) {
                Database::insert('content_types', [
                    'slug'        => $d[0],
                    'name'        => $d[1],
                    'description' => $d[2],
                    'icon'        => $d[3],
                    'is_system'   => 1,
                    'is_public'   => 1,
                    'schema_type' => $d[4],
                    'sort_order'  => $order++,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }

        // --- Phase 2 tables ---
        self::migratePhase2($db);
    }

    private static function migratePhase2(\PDO $db): void
    {
        // Editorial workflow + assignments
        $db->exec("
            CREATE TABLE IF NOT EXISTS content_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                from_status TEXT,
                to_status TEXT NOT NULL,
                note TEXT,
                user_id INTEGER,
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS content_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                assignee_id INTEGER NOT NULL,
                assigned_by INTEGER,
                note TEXT,
                status TEXT NOT NULL DEFAULT 'open',
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS content_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                content_id INTEGER NOT NULL,
                user_id INTEGER,
                body TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (content_id) REFERENCES content(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        ");

        // Mail sequences
        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_sequences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'draft',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_sequence_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sequence_id INTEGER NOT NULL,
                delay_days INTEGER NOT NULL DEFAULT 0,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                sort_order INTEGER DEFAULT 0,
                FOREIGN KEY (sequence_id) REFERENCES mail_sequences(id) ON DELETE CASCADE
            )
        ");

        // Login history
        $db->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                username TEXT,
                ip_address TEXT,
                user_agent TEXT,
                success INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )
        ");

        // Media enhancements: hash for duplicates, focal point
        try {
            $cols = $db->query("PRAGMA table_info(media)")->fetchAll(\PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (!in_array('file_hash', $names, true)) {
                $db->exec("ALTER TABLE media ADD COLUMN file_hash TEXT");
            }
            if (!in_array('focal_x', $names, true)) {
                $db->exec("ALTER TABLE media ADD COLUMN focal_x REAL DEFAULT 0.5");
            }
            if (!in_array('focal_y', $names, true)) {
                $db->exec("ALTER TABLE media ADD COLUMN focal_y REAL DEFAULT 0.5");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Users: 2FA columns
        try {
            $cols = $db->query("PRAGMA table_info(users)")->fetchAll(\PDO::FETCH_ASSOC);
            $names = array_column($cols, 'name');
            if (!in_array('totp_secret', $names, true)) {
                $db->exec("ALTER TABLE users ADD COLUMN totp_secret TEXT");
            }
            if (!in_array('totp_enabled', $names, true)) {
                $db->exec("ALTER TABLE users ADD COLUMN totp_enabled INTEGER NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Content workflow status already has draft/scheduled/published — add review/approved via statuses in config usage
        $db->exec("CREATE INDEX IF NOT EXISTS idx_media_hash ON media(file_hash)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_login_history_user ON login_history(user_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_content_notes_content ON content_notes(content_id)");

        self::migratePhase3($db);
            self::migratePhase4($db);
    }

    private static function migratePhase3(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                domain TEXT,
                theme TEXT NOT NULL DEFAULT 'default',
                status TEXT NOT NULL DEFAULT 'active',
                is_primary INTEGER NOT NULL DEFAULT 0,
                settings TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS plugins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                version TEXT,
                status TEXT NOT NULL DEFAULT 'inactive',
                config TEXT,
                installed_at TEXT,
                activated_at TEXT
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS integrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                driver TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'disabled',
                config TEXT,
                updated_at TEXT
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS api_rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                api_key_id INTEGER,
                ip_address TEXT,
                endpoint TEXT,
                hits INTEGER NOT NULL DEFAULT 1,
                window_start TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_suppressions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                reason TEXT,
                created_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS trusted_devices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                device_label TEXT,
                ip_address TEXT,
                user_agent TEXT,
                token_hash TEXT,
                last_seen_at TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");

        if (Database::count('sites') === 0) {
            $now = date('c');
            $name = 'Mova';
            try {
                $row = Database::fetch("SELECT setting_value FROM settings WHERE setting_key = 'site_name'");
                if ($row && $row['setting_value']) {
                    $name = $row['setting_value'];
                }
            } catch (\Throwable $e) {
            }
            Database::insert('sites', [
                'name'       => $name,
                'slug'       => 'primary',
                'domain'     => '',
                'theme'      => 'default',
                'status'     => 'active',
                'is_primary' => 1,
                'settings'   => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $db->exec("CREATE INDEX IF NOT EXISTS idx_sites_domain ON sites(domain)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_api_rate_window ON api_rate_limits(window_start)");
    }


    private static function migratePhase4(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                label TEXT NOT NULL,
                email TEXT NOT NULL,
                display_name TEXT,
                avatar TEXT,
                imap_host TEXT NOT NULL,
                imap_port INTEGER NOT NULL DEFAULT 993,
                imap_encryption TEXT NOT NULL DEFAULT 'ssl',
                imap_username TEXT NOT NULL,
                imap_password TEXT NOT NULL,
                smtp_host TEXT,
                smtp_port INTEGER DEFAULT 587,
                smtp_encryption TEXT DEFAULT 'tls',
                smtp_username TEXT,
                smtp_password TEXT,
                is_default INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_contacts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER,
                name TEXT,
                email TEXT NOT NULL,
                notes TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES mail_accounts(id) ON DELETE SET NULL
            )
        ");

        $db->exec("
            CREATE TABLE IF NOT EXISTS mail_filters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                account_id INTEGER,
                name TEXT NOT NULL,
                match_field TEXT NOT NULL DEFAULT 'from',
                match_op TEXT NOT NULL DEFAULT 'contains',
                match_value TEXT NOT NULL,
                action TEXT NOT NULL DEFAULT 'flag',
                action_value TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (account_id) REFERENCES mail_accounts(id) ON DELETE CASCADE
            )
        ");

        $db->exec("CREATE INDEX IF NOT EXISTS idx_mail_contacts_email ON mail_contacts(email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mail_accounts_email ON mail_accounts(email)");

        self::migratePhase5($db);
    }

    private static function migratePhase5(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS assemblies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                site_id INTEGER,
                slug TEXT NOT NULL,
                name TEXT NOT NULL,
                description TEXT,
                html TEXT NOT NULL DEFAULT '',
                css TEXT NOT NULL DEFAULT '',
                js TEXT NOT NULL DEFAULT '',
                scripts_enabled INTEGER NOT NULL DEFAULT 0,
                css_global INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'draft',
                revision INTEGER NOT NULL DEFAULT 1,
                checksum TEXT,
                created_by INTEGER,
                updated_by INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                published_at TEXT
            )
        ");
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_assemblies_slug ON assemblies(slug)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_assemblies_status ON assemblies(status)");
    }

    public static function isInstalled(): bool
    {
        try {
            $row = Database::fetch("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
            return $row !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function hasOwner(): bool
    {
        return Database::count('users', "role = 'owner'") > 0;
    }
}
