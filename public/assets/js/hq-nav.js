/**
 * Mova HQ vNext — Context-aware Line 2
 * [Back] [Forward] | contextual items for the current page
 */
(function () {
    'use strict';

    const NAV_CONTEXTS = {
        // ----- Overview (HQ home + section landings use their own keys) -----
        overview: {
            sectionName: 'Overview',
            items: [
                { label: 'Overview',  href: '/hq', match: { path: '/hq' } },
                { label: 'Documentation', href: '/hq/docs', match: { path: '/hq/docs' } },
                { label: 'Content',   href: '/hq/content-hub' },
                { label: 'Audience',  href: '/hq/audience' },
                { label: 'Design',    href: '/hq/design' },
                { label: 'Extend',    href: '/hq/extend' },
                { label: 'Operations', href: '/hq/operations' },
                { label: 'Settings',  href: '/hq/settings' }
            ]
        },

        // ----- Content family -----
        content: {
            sectionName: 'Content',
            items: [
                { label: 'All content',    href: '/hq/content' },
                { label: 'Media',          href: '/hq/media' },
                { label: 'Types & fields', href: '/hq/types' },
                { label: 'Categories',     href: '/hq/categories' },
                { label: 'Tags',           href: '/hq/tags' }
            ]
        },
        'content.list': {
            sectionName: 'Content',
            items: [
                { label: 'Draft',       href: '/hq/content?status=draft',       match: { status: 'draft' } },
                { label: 'In review',   href: '/hq/content?status=review',      match: { status: 'review' } },
                { label: 'Approved',    href: '/hq/content?status=approved',    match: { status: 'approved' } },
                { label: 'Scheduled',   href: '/hq/content?status=scheduled',   match: { status: 'scheduled' } },
                { label: 'Published',   href: '/hq/content?status=published',   match: { status: 'published' } },
                { label: 'Archived',    href: '/hq/content?status=archived',    match: { status: 'archived' } },
                { label: 'Trash',       href: '/hq/content?status=trash',       match: { status: 'trash' } },
                { label: 'New content', href: '/hq/content/new', primary: true, match: { path: '/hq/content/new' } }
            ]
        },
        'content.edit': {
            sectionName: 'Content',
            items: [
                { label: 'AI assist',      action: 'ai-assist' },
                { label: 'Publish',        action: 'publish' },
                { label: 'Categories',     action: 'categories' },
                { label: 'Tags',           action: 'tags' },
                { label: 'Featured image', action: 'featured-image' },
                { label: 'Related',        action: 'related-content' },
                { label: 'SEO',            action: 'seo' }
            ]
        },

        // ----- Audience family -----
        audience: {
            sectionName: 'Audience',
            items: [
                { label: 'People',     href: '/hq/people' },
                { label: 'Mailbox',    href: '/hq/mailbox' },
                { label: 'Campaigns',  href: '/hq/mail' },
                { label: 'Sequences',  href: '/hq/sequences' },
                { label: 'Insights',   href: '/hq/insights' }
            ]
        },
        mailbox: {
            sectionName: 'Audience',
            items: [
                { label: 'Compose',       href: '/hq/mailbox?tab=compose&compose=1' },
                { label: 'Inbox',         href: '/hq/mailbox?tab=inbox&folder=INBOX' },
                { label: 'Sent',          href: '/hq/mailbox?tab=inbox&folder=Sent' },
                { label: 'Drafts',        href: '/hq/mailbox?tab=inbox&folder=Drafts' },
                { label: 'Trash',         href: '/hq/mailbox?tab=inbox&folder=Trash' },
                { label: 'Spam',          href: '/hq/mailbox?tab=inbox&folder=Spam' },
                { label: 'Address book',  href: '/hq/mailbox?tab=contacts' },
                { label: 'Filters',       href: '/hq/mailbox?tab=filters' },
                { label: 'Accounts',      href: '/hq/mailbox?tab=accounts' }
            ]
        },
        campaigns: {
            sectionName: 'Audience',
            items: [
                { label: 'Add subscriber', action: 'add-subscriber' },
                { label: 'Send campaign',  action: 'send-campaign' }
            ]
        },
        insights: {
            sectionName: 'Audience',
            items: [
                { label: 'Popular content', action: 'popular' },
                { label: 'Recent views',    action: 'recent' }
            ]
        },

        // ----- Design family -----
        design: {
            sectionName: 'Design',
            items: [
                { label: 'Brand',      href: '/hq/brand' },
                { label: 'Style',      href: '/hq/style' },
                { label: 'Layout',     href: '/hq/layout' },
                { label: 'Components', href: '/hq/components' },
                { label: 'Elements',   href: '/hq/elements' }
            ]
        },
        'design.brand': {
            sectionName: 'Design',
            items: [
                { label: 'Identity',        layer: 'identity' },
                { label: 'Logo & favicon',  layer: 'marks' },
                { label: 'Theme package',   layer: 'theme' }
            ]
        },
        'design.style': {
            sectionName: 'Design',
            items: [
                { label: 'Light colors',     layer: 'light' },
                { label: 'Dark colors',      layer: 'dark' },
                { label: 'Typography',       layer: 'type' },
                { label: 'Radius & shadow',  layer: 'shape' },
                { label: 'Density',          layer: 'density' }
            ]
        },
        'design.layout': {
            sectionName: 'Design',
            items: [
                { label: 'Container',   layer: 'container' },
                { label: 'Header',      layer: 'header' },
                { label: 'Navigation',  layer: 'nav' },
                { label: 'Mobile menu', layer: 'mobile' },
                { label: 'Footer',      layer: 'footer' }
            ]
        },
        'design.components': {
            sectionName: 'Design',
            items: [
                { label: 'Buttons',    layer: 'button' },
                { label: 'Cards',      layer: 'card' },
                { label: 'Hero',       layer: 'hero' }
            ]
        },
        'design.elements': {
            sectionName: 'Design',
            items: [
                { label: 'Editor',     layer: 'editor' },
                { label: 'Applied',    layer: 'applied' }
            ]
        },

        // ----- Extend -----
        extend: {
            sectionName: 'Extend',
            items: [
                { label: 'Plugins',      href: '/hq/plugins' },
                { label: 'Integrations', href: '/hq/integrations' },
                { label: 'API keys',     href: '/hq/api-keys' },
                { label: 'Webhooks',     href: '/hq/webhooks' }
            ]
        },
        plugins: {
            sectionName: 'Extend',
            items: [
                { label: 'Plugins',      href: '/hq/plugins' },
                { label: 'Installed',    href: '/hq/plugins' },
                { label: 'Add new',      href: '/hq/plugins/#', primary: true }
            ]
        },
        integrations: {
            sectionName: 'Extend',
            items: [
                { label: 'CDN',            href: '/hq/integrations?provider=cdn' },
                { label: 'Email API',      href: '/hq/integrations?provider=smtp' },
                { label: 'Analytics',      href: '/hq/integrations?provider=analytics' },
                { label: 'Object storage', href: '/hq/integrations?provider=storage' },
                { label: 'AI provider',    href: '/hq/integrations?provider=ai' }
            ]
        },

        // ----- Operations -----
        operations: {
            sectionName: 'Operations',
            items: [
                { label: 'Health',   href: '/hq/health' },
                { label: 'Security', href: '/hq/security' },
                { label: 'Backups',  href: '/hq/backups' }
            ]
        },

        // ----- Settings -----
        settings: {
            sectionName: 'Settings',
            items: [
                { label: 'General',   href: '/hq/settings?layer=general', layer: 'general' },
                { label: 'Sites',     href: '/hq/sites' },
                { label: 'AI assist', href: '/hq/settings?layer=ai', layer: 'ai' }
            ]
        },
        'settings.general': {
            sectionName: 'Settings',
            items: [
                { label: 'General',   href: '/hq/settings?layer=general', layer: 'general' },
                { label: 'Sites',     href: '/hq/sites' },
                { label: 'AI assist', href: '/hq/settings?layer=ai', layer: 'ai' }
            ]
        }
    };

    function resolveContext() {
        const path = window.location.pathname.replace(/\/$/, '') || '/';
        const params = new URLSearchParams(window.location.search);
        const status = params.get('status');
        const section = params.get('section');
        const view = params.get('view');
        const folder = params.get('folder');

        if (path === '/hq' || path === '/hq/') return { key: 'overview', params };

        // Section landings
        if (path === '/hq/content-hub') return { key: 'content', params };
        if (path === '/hq/audience') return { key: 'audience', params };
        if (path === '/hq/design') return { key: 'design', params };
        if (path === '/hq/extend') return { key: 'extend', params };
        if (path === '/hq/operations') return { key: 'operations', params };

        // Content editor
        if (path === '/hq/content/new' || /^\/hq\/content\/edit\/\d+/.test(path) || /^\/hq\/content\/\d+/.test(path)) {
            return { key: 'content.edit', params };
        }
        if (path === '/hq/content') {
            return { key: 'content.list', params, status };
        }
        if (path.startsWith('/hq/media') || path.startsWith('/hq/types')
            || path.startsWith('/hq/categories') || path.startsWith('/hq/tags')) {
            return { key: 'content', params, status };
        }

        // Audience
        if (path.startsWith('/hq/mailbox')) return { key: 'mailbox', params, folder };
        if (path.startsWith('/hq/mail')) return { key: 'campaigns', params };
        if (path.startsWith('/hq/insights')) return { key: 'insights', params, view };
        if (path.startsWith('/hq/people') || path.startsWith('/hq/sequences')) {
            return { key: 'audience', params };
        }

        // Design
        if (path.startsWith('/hq/brand')) return { key: 'design.brand', params, section };
        if (path.startsWith('/hq/style')) return { key: 'design.style', params, section };
        if (path.startsWith('/hq/layout')) return { key: 'design.layout', params, section };
        if (path.startsWith('/hq/components')) return { key: 'design.components', params, section };
        if (path.startsWith('/hq/elements')) return { key: 'design.elements', params };

        // Extend
        if (path.startsWith('/hq/integrations')) return { key: 'integrations', params };
        if (path.startsWith('/hq/plugins')) return { key: 'plugins', params };
        if (path.startsWith('/hq/api-keys') || path.startsWith('/hq/webhooks')) return { key: 'extend', params };

        // Operations
        if (path.startsWith('/hq/health') || path.startsWith('/hq/security') || path.startsWith('/hq/backups')) {
            return { key: 'operations', params };
        }

        // Settings
        if (path.startsWith('/hq/settings')) return { key: 'settings.general', params, section };
        if (path.startsWith('/hq/sites')) return { key: 'settings.general', params };

        return { key: 'overview', params };
    }

    function isItemActive(item, ctx) {
        if (item.layer) {
            const urlLayer = new URLSearchParams(window.location.search).get('layer');
            const activeLayer = urlLayer
                || document.body.dataset.activeLayer
                || document.querySelector('.hq-layer-tab.is-active')?.getAttribute('data-layer')
                || document.querySelector('.hq-layer-panel.is-active')?.getAttribute('data-layer-panel');
            // On Sites page, only "Sites" is active (no layers on page)
            if (window.location.pathname.replace(/\/$/, '').endsWith('/hq/sites')) {
                return false;
            }
            return activeLayer === item.layer;
        }

        if (!item.match && !item.href && !item.action) return false;

        if (item.match) {
            if (item.match.path) {
                const p = window.location.pathname.replace(/\/$/, '') || '/';
                const m = item.match.path.replace(/\/$/, '') || '/';
                if (p !== m) return false;
            }
            if (item.match.status !== undefined) {
                const currentStatus = ctx.status || null;
                if (item.match.status === null) return !currentStatus;
                return currentStatus === item.match.status;
            }
            if (item.match.section && ctx.section !== item.match.section) return false;
            if (item.match.view && ctx.view !== item.match.view) return false;
            if (item.match.folder && ctx.folder !== item.match.folder) return false;
            return true;
        }

        if (item.href) {
            const itemUrl = new URL(item.href, window.location.origin);
            const currentPath = window.location.pathname.replace(/\/$/, '') || '/';
            const itemPath = itemUrl.pathname.replace(/\/$/, '') || '/';
            const currentParams = new URLSearchParams(window.location.search);

            if (currentPath !== itemPath && !currentPath.startsWith(itemPath + '/')) return false;

            const tabA = itemUrl.searchParams.get('tab');
            const folderA = itemUrl.searchParams.get('folder');
            if (itemPath === '/hq/mailbox' && (tabA || folderA || itemUrl.searchParams.get('compose'))) {
                if (tabA === 'compose' || itemUrl.searchParams.get('compose') === '1') {
                    return currentParams.get('tab') === 'compose' || currentParams.get('compose') === '1';
                }
                if (tabA === 'contacts' || tabA === 'filters' || tabA === 'accounts') {
                    return currentParams.get('tab') === tabA;
                }
                if (folderA) {
                    const currentFolder = currentParams.get('folder') || 'INBOX';
                    const onInboxTab = !currentParams.get('tab') || currentParams.get('tab') === 'inbox';
                    return onInboxTab && !currentParams.get('compose') && currentFolder.toLowerCase() === folderA.toLowerCase();
                }
            }

            if (!itemUrl.search) {
                if (itemPath === '/hq/mailbox' && (currentParams.get('tab') || currentParams.get('compose'))) return false;
                if (itemPath === '/hq' || itemPath === '') {
                    return currentPath === '/hq' || currentPath === '';
                }
                return currentPath === itemPath || currentPath.startsWith(itemPath + '/');
            }

            for (const key of ['status', 'section', 'view', 'folder', 'provider']) {
                const a = itemUrl.searchParams.get(key);
                const b = currentParams.get(key);
                if (a !== null && a !== b) return false;
            }
            return true;
        }

        if (item.action) {
            return document.body.dataset.activeTool === item.action;
        }
        return false;
    }

    function activateLayer(id) {
        const root = document.querySelector('.hq-layers');
        if (!root || !id) return false;
        const tabs = root.querySelectorAll('.hq-layer-tab');
        const panels = root.querySelectorAll('.hq-layer-panel');
        let found = false;
        tabs.forEach(function (tab) {
            const on = tab.getAttribute('data-layer') === id;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            if (on) found = true;
        });
        panels.forEach(function (panel) {
            const on = panel.getAttribute('data-layer-panel') === id;
            panel.hidden = !on;
            panel.classList.toggle('is-active', on);
            if (on) found = true;
        });
        if (found) {
            document.body.dataset.activeLayer = id;
            const storageKey = root.getAttribute('data-layer-key');
            if (storageKey) {
                try { localStorage.setItem(storageKey, id); } catch (e) { /* ignore */ }
            }
        }
        return found;
    }

    function renderLine2() {
        const nav = document.getElementById('hq-contextual-nav');
        const sectionEl = document.getElementById('hq-section-name');
        if (!nav) return;

        const ctx = resolveContext();
        const config = NAV_CONTEXTS[ctx.key] || NAV_CONTEXTS.overview;

        if (sectionEl) sectionEl.textContent = config.sectionName;

        const canBack = window.history.length > 1;
        const backDisabled = !canBack ? ' disabled' : '';

        let html = `
            <button type="button" class="hq-hist-btn" id="hq-nav-back" title="Go back" aria-label="Go back"${backDisabled}>
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <button type="button" class="hq-hist-btn" id="hq-nav-forward" title="Go forward" aria-label="Go forward">
                <i class="fa-solid fa-arrow-right"></i>
            </button>
            <span class="hq-nav-divider" aria-hidden="true"></span>
        `;

        config.items.forEach(item => {
            const active = isItemActive(item, ctx) ? ' active' : '';
            const primary = item.primary ? ' primary' : '';
            // No icons on Line 2 per UX request
            if (item.href && !item.layer) {
                html += `<a href="${item.href}" class="hq-nav-item${active}${primary}"><span>${item.label}</span></a>`;
            } else if (item.layer && item.href) {
                html += `<a href="${item.href}" class="hq-nav-item${active}${primary}" data-hq-layer="${item.layer}"><span>${item.label}</span></a>`;
            } else if (item.layer) {
                html += `<button type="button" class="hq-nav-item${active}${primary}" data-hq-layer="${item.layer}"><span>${item.label}</span></button>`;
            } else if (item.action) {
                html += `<button type="button" class="hq-nav-item${active}${primary}" data-hq-action="${item.action}"><span>${item.label}</span></button>`;
            }
        });

        nav.innerHTML = html;

        document.getElementById('hq-nav-back')?.addEventListener('click', () => {
            if (window.history.length > 1) window.history.back();
        });
        document.getElementById('hq-nav-forward')?.addEventListener('click', () => {
            window.history.forward();
        });

        nav.querySelectorAll('[data-hq-action]').forEach(btn => {
            btn.addEventListener('click', () => {
                const action = btn.getAttribute('data-hq-action');
                document.dispatchEvent(new CustomEvent('hq:tool', { detail: { action } }));
                document.body.dataset.activeTool = action;
                renderLine2();
            });
        });

        nav.querySelectorAll('[data-hq-layer]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const layer = btn.getAttribute('data-hq-layer');
                if (btn.tagName === 'A' && btn.getAttribute('href')) {
                    // Allow navigation; layer activates on target page
                    return;
                }
                e.preventDefault();
                activateLayer(layer);
                renderLine2();
            });
        });
    }

    const SEARCH_INDEX = (window.MOVA_HQ_SEARCH_INDEX && window.MOVA_HQ_SEARCH_INDEX.length) ? window.MOVA_HQ_SEARCH_INDEX : [
        { title: 'Overview',         href: '/hq',                 keywords: 'overview dashboard home', group: 'Overview' },
        { title: 'Content hub',      href: '/hq/content-hub',     keywords: 'content', group: 'Content' },
        { title: 'All content',      href: '/hq/content',         keywords: 'posts pages articles content list', group: 'Content' },
        { title: 'New content',      href: '/hq/content/new',     keywords: 'new create add content post page', group: 'Content' },
        { title: 'Media library',    href: '/hq/media',           keywords: 'media images files upload', group: 'Content' },
        { title: 'Types & fields',   href: '/hq/types',           keywords: 'types fields', group: 'Content' },
        { title: 'Categories',       href: '/hq/categories',      keywords: 'categories taxonomy', group: 'Content' },
        { title: 'Tags',             href: '/hq/tags',            keywords: 'tags taxonomy', group: 'Content' },
        { title: 'Audience',         href: '/hq/audience',        keywords: 'audience', group: 'Audience' },
        { title: 'People',           href: '/hq/people',          keywords: 'people contacts audience', group: 'Audience' },
        { title: 'Mailbox',          href: '/hq/mailbox',         keywords: 'mailbox inbox email', group: 'Audience' },
        { title: 'Campaigns',        href: '/hq/mail',            keywords: 'campaigns newsletter', group: 'Audience' },
        { title: 'Sequences',        href: '/hq/sequences',       keywords: 'sequences automation', group: 'Audience' },
        { title: 'Insights',         href: '/hq/insights',        keywords: 'insights analytics', group: 'Audience' },
        { title: 'Design',           href: '/hq/design',          keywords: 'design', group: 'Design' },
        { title: 'Brand',            href: '/hq/brand',           keywords: 'brand identity', group: 'Design' },
        { title: 'Style',            href: '/hq/style',           keywords: 'style colors typography', group: 'Design' },
        { title: 'Layout',           href: '/hq/layout',          keywords: 'layout header footer', group: 'Design' },
        { title: 'Components',       href: '/hq/components',      keywords: 'components buttons cards', group: 'Design' },
        { title: 'Elements',         href: '/hq/elements',        keywords: 'elements editor', group: 'Design' },
        { title: 'Extend',           href: '/hq/extend',          keywords: 'extend', group: 'Extend' },
        { title: 'Plugins',          href: '/hq/plugins',         keywords: 'plugins modules', group: 'Extend' },
        { title: 'Integrations',     href: '/hq/integrations',    keywords: 'integrations', group: 'Extend' },
        { title: 'API keys',         href: '/hq/api-keys',             keywords: 'api keys', group: 'Extend' },
        { title: 'Webhooks',         href: '/hq/webhooks',        keywords: 'webhooks', group: 'Extend' },
        { title: 'Operations',       href: '/hq/operations',      keywords: 'operations', group: 'Operations' },
        { title: 'Health',           href: '/hq/health',          keywords: 'health status', group: 'Operations' },
        { title: 'Security',         href: '/hq/security',        keywords: 'security 2fa', group: 'Operations' },
        { title: 'Backups',          href: '/hq/backups',         keywords: 'backups', group: 'Operations' },
        { title: 'Settings',         href: '/hq/settings',        keywords: 'settings general', group: 'Settings' },
        { title: 'Sites',            href: '/hq/sites',           keywords: 'sites multisite', group: 'Settings' }
    ];

    function initSearch() {
        const input = document.getElementById('hq-global-search');
        const dropdown = document.getElementById('hq-search-dropdown');
        if (!input || !dropdown) return;
        let activeIndex = -1;

        function filter(q) {
            q = (q || '').trim().toLowerCase();
            if (!q) return [];
            return (function () {
                const tokens = q.split(/\s+/).filter(Boolean);
                function hay(item) {
                    return ((item.title || '') + ' ' + (item.keywords || '') + ' ' + (item.group || '') + ' ' + (item.body || '')).toLowerCase();
                }
                function score(item) {
                    const h = hay(item);
                    let s = 0;
                    tokens.forEach(tok => {
                        if (item.title && item.title.toLowerCase().includes(tok)) s += 10;
                        if (item.keywords && item.keywords.toLowerCase().includes(tok)) s += 5;
                        if (item.body && item.body.toLowerCase().includes(tok)) s += 3;
                        if (item.group && item.group.toLowerCase().includes(tok)) s += 1;
                    });
                    return s;
                }
                function snippet(item) {
                    const body = item.body || item.keywords || '';
                    if (!body) return '';
                    const lower = body.toLowerCase();
                    let pos = -1, tok = tokens[0] || '';
                    for (const t of tokens) {
                        const p = lower.indexOf(t);
                        if (p >= 0) { pos = p; tok = t; break; }
                    }
                    if (pos < 0) return body.slice(0, 100);
                    const start = Math.max(0, pos - 40);
                    let sn = body.slice(start, start + 110);
                    if (start > 0) sn = '…' + sn;
                    if (start + 110 < body.length) sn += '…';
                    return sn;
                }
                return SEARCH_INDEX.map(item => Object.assign({}, item, { _score: score(item), _snippet: snippet(item) }))
                    .filter(item => item._score > 0)
                    .sort((a, b) => b._score - a._score)
                    .slice(0, 10);
            })();
        }

        function render(items) {
            if (!items.length) {
                dropdown.hidden = true;
                return;
            }
            dropdown.innerHTML = items.map((item, i) =>
                `<a class="hq-search-item${i === activeIndex ? ' is-active' : ''}" href="${item.href}">
                    <span class="hq-search-title">${item.title}</span>
                    <span class="hq-search-group">${item.group}</span>
                    ${item._snippet ? `<span class="hq-search-snippet">${item._snippet.replace(/</g,'&lt;')}</span>` : ''}
                </a>`).join('');
            dropdown.hidden = false;
        }

        function close() {
            dropdown.hidden = true;
            activeIndex = -1;
        }

        input.addEventListener('input', () => {
            activeIndex = -1;
            render(filter(input.value));
        });

        input.addEventListener('keydown', e => {
            const items = dropdown.querySelectorAll('.hq-search-item');
            if (dropdown.hidden || !items.length) {
                if (e.key === 'Escape') close();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                render(filter(input.value));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                render(filter(input.value));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const target = activeIndex >= 0 ? items[activeIndex] : items[0];
                if (target) window.location.href = target.href;
            } else if (e.key === 'Escape') {
                close();
                input.blur();
            }
        });

        document.addEventListener('click', e => {
            if (!document.getElementById('hq-search-wrap')?.contains(e.target)) close();
        });

        input.addEventListener('focus', () => {
            if (input.value.trim()) render(filter(input.value));
        });
    }

    function initUserMenu() {
        const btn = document.getElementById('hq-user-btn');
        const dropdown = document.getElementById('hq-user-dropdown');
        if (!btn || !dropdown) return;
        btn.addEventListener('click', e => {
            e.stopPropagation();
            dropdown.hidden = !dropdown.hidden;
        });
        document.addEventListener('click', () => { dropdown.hidden = true; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderLine2();
        initSearch();
        initUserMenu();
    });

    document.addEventListener('hq:tool', () => renderLine2());
})();
