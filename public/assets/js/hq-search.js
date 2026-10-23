/**
 * Mova HQ — global search, command palette and deep links.
 *
 *  - Header search: debounced server search (/hq/search, FTS5 trigram → partial words work),
 *    grouped rich suggestions (icon, highlighted title, breadcrumb, badge, snippet),
 *    ↑/↓ with scrollIntoView, Enter, Esc, recent searches on empty focus.
 *  - Ctrl/Cmd+K (or "/" outside fields) opens a command palette: quick actions,
 *    recent pages and live results in one keyboard-driven list.
 *  - Deep links: #hqf=<text> on any HQ page opens the right layer, scrolls to the matching
 *    heading/label/field and flashes it.
 * Server output is already HTML-escaped (only <mark> added); everything else is set via textContent.
 */
(function () {
    'use strict';

    var ENDPOINT = '/hq/search';
    var RECENT_Q = 'movaHqRecentQ';
    var MIN_CHARS = 2;
    var cache = new Map();

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }
    function safeIcon(c) { return /^[a-z0-9 -]+$/i.test(c || '') ? c : 'fa-solid fa-circle'; }
    function safeUrl(u) { return typeof u === 'string' && /^\/hq(\/|\?|#|$)/.test(u) ? u : '/hq'; }
    function isTyping(t) { return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName)); }

    function readList(key) { try { var v = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(v) ? v : []; } catch (e) { return []; } }
    function writeList(key, v) { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) { /* ignore */ } }
    function pushRecent(q) {
        q = String(q || '').trim();
        if (q.length < MIN_CHARS) return;
        var list = readList(RECENT_Q).filter(function (x) { return x.toLowerCase() !== q.toLowerCase(); });
        list.unshift(q);
        writeList(RECENT_Q, list.slice(0, 8));
    }

    var inflight = null;
    function fetchResults(q) {
        var key = q.toLowerCase();
        if (cache.has(key)) return Promise.resolve(cache.get(key));
        if (inflight && inflight.abort) inflight.abort();
        var ctrl = 'AbortController' in window ? new AbortController() : null;
        inflight = ctrl;
        return fetch(ENDPOINT + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (data) {
            cache.set(key, data);
            if (cache.size > 60) cache.delete(cache.keys().next().value);
            return data;
        });
    }

    /** Build one result row (button-like link). */
    function resultRow(item, idPrefix, idx) {
        var a = el('a', 'hq-sr-item');
        a.href = safeUrl(item.url);
        a.id = idPrefix + idx;
        a.setAttribute('role', 'option');
        a.setAttribute('aria-selected', 'false');
        a.tabIndex = -1;
        var ic = el('span', 'hq-sr-icon');
        ic.setAttribute('aria-hidden', 'true');
        ic.appendChild(el('i', safeIcon(item.icon)));
        a.appendChild(ic);
        var main = el('span', 'hq-sr-main');
        var top = el('span', 'hq-sr-top');
        var title = el('span', 'hq-sr-title');
        if (item.title_html != null) title.innerHTML = item.title_html; else title.textContent = item.title || '';
        top.appendChild(title);
        if (item.badge) top.appendChild(el('span', 'hq-sr-badge', item.badge));
        main.appendChild(top);
        if (item.crumb) main.appendChild(el('span', 'hq-sr-crumb', item.crumb));
        if (item.snippet_html) {
            var sn = el('span', 'hq-sr-snippet');
            sn.innerHTML = item.snippet_html;
            main.appendChild(sn);
        } else if (item.hint) {
            main.appendChild(el('span', 'hq-sr-snippet', item.hint));
        }
        a.appendChild(main);
        if (item.kbd) a.appendChild(el('kbd', 'hq-sr-kbd', item.kbd));
        else a.appendChild(el('span', 'hq-sr-go', '↵'));
        if (item.run) a.addEventListener('click', function (e) { e.preventDefault(); item.run(); });
        return a;
    }

    /**
     * Generic results list controller (used by dropdown and palette).
     * groups: [{name, items:[…]}]
     */
    function ListView(container, idPrefix, onPick) {
        this.c = container; this.idPrefix = idPrefix; this.onPick = onPick;
        this.rows = []; this.items = []; this.active = -1;
    }
    ListView.prototype.render = function (groups, footer) {
        var c = this.c, self = this;
        var frag = document.createDocumentFragment();
        this.rows = []; this.items = []; this.active = -1;
        groups.forEach(function (g) {
            if (!g.items || !g.items.length) return;
            var sec = el('div', 'hq-sr-group');
            sec.setAttribute('role', 'group');
            var head = el('div', 'hq-sr-group-head', g.name);
            head.id = self.idPrefix + 'g' + self.rows.length;
            sec.setAttribute('aria-labelledby', head.id);
            sec.appendChild(head);
            g.items.forEach(function (it) {
                var row = resultRow(it, self.idPrefix, self.rows.length);
                var i = self.rows.length;
                row.addEventListener('mousemove', function () { if (self.active !== i) self.setActive(i, false); });
                row.addEventListener('click', function (e) {
                    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) return;
                    self.onPick(it, e);
                });
                self.rows.push(row); self.items.push(it);
                sec.appendChild(row);
            });
            frag.appendChild(sec);
        });
        if (footer) frag.appendChild(footer);
        c.replaceChildren(frag);
        if (this.rows.length) this.setActive(0, false);
        return this.rows.length;
    };
    ListView.prototype.setActive = function (i, scroll) {
        if (this.active > -1 && this.rows[this.active]) {
            this.rows[this.active].classList.remove('is-active');
            this.rows[this.active].setAttribute('aria-selected', 'false');
        }
        this.active = i;
        var r = this.rows[i];
        if (!r) return;
        r.classList.add('is-active');
        r.setAttribute('aria-selected', 'true');
        if (scroll !== false) r.scrollIntoView({ block: 'nearest' });
        if (this.input) this.input.setAttribute('aria-activedescendant', r.id);
    };
    ListView.prototype.move = function (d) {
        if (!this.rows.length) return;
        this.setActive((this.active + d + this.rows.length) % this.rows.length, true);
    };
    ListView.prototype.pick = function (e) {
        var it = this.items[this.active];
        if (it) this.onPick(it, e || {});
    };

    function go(item, newTab) {
        if (item.run) { item.run(); return; }
        var url = safeUrl(item.url);
        if (newTab) { window.open(url, '_blank', 'noopener'); return; }
        // Same page + hash deep link → handle in place (no reload)
        try {
            var u = new URL(url, location.origin);
            if (u.pathname === location.pathname && u.search === location.search && u.hash) {
                history.replaceState(history.state, '', u.hash);
                deepLink();
                return;
            }
        } catch (e) { /* ignore */ }
        window.location.href = url;
    }

    function footerHint(text) {
        var f = el('div', 'hq-sr-foot');
        f.setAttribute('aria-hidden', 'true');
        f.appendChild(el('span', '', text));
        return f;
    }

    function emptyState(msg, sub) {
        var d = el('div', 'hq-sr-empty');
        d.appendChild(el('i', 'fa-solid fa-magnifying-glass'));
        d.appendChild(el('strong', '', msg));
        if (sub) d.appendChild(el('span', '', sub));
        return d;
    }

    /* =========================================================== header search */

    function initHeaderSearch() {
        var input = document.getElementById('hq-global-search');
        var dd = document.getElementById('hq-search-dropdown');
        var wrap = document.getElementById('hq-search-wrap');
        if (!input || !dd) return;
        var view = new ListView(dd, 'hq-sr-', function (it, e) {
            pushRecent(input.value);
            close();
            go(it, e && (e.ctrlKey || e.metaKey));
        });
        view.input = input;
        var timer = 0, lastQ = '';

        function open() { dd.hidden = false; input.setAttribute('aria-expanded', 'true'); wrap && wrap.classList.add('is-open'); }
        function close() { dd.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); wrap && wrap.classList.remove('is-open'); }

        function showRecent() {
            var rec = readList(RECENT_Q);
            if (!rec.length) {
                view.render([]);
                dd.replaceChildren(emptyState('Search everything in HQ', 'Pages, settings, fields, content, media, people, plugins, themes, sections. Try "favic", "smtp" or "seo".'));
                open();
                return;
            }
            view.render([{ name: 'Recent searches', items: rec.map(function (q) {
                return { title: q, icon: 'fa-solid fa-clock-rotate-left', run: function () { input.value = q; search(q); input.focus(); } };
            }) }], footerHint('↑↓ to move · Enter to open · Ctrl K for the command palette'));
            open();
        }

        function search(q) {
            lastQ = q;
            dd.setAttribute('aria-busy', 'true');
            fetchResults(q).then(function (data) {
                if (q !== lastQ) return;
                dd.removeAttribute('aria-busy');
                var n = view.render(data.groups || [], footerHint((data.total || 0) + ' result' + (data.total === 1 ? '' : 's') + ' · ' + (data.engine === 'fts5' ? 'full-text' : 'basic') + ' search · ' + (data.took_ms || 0) + ' ms'));
                if (!n) dd.replaceChildren(emptyState('No matches for "' + q + '"', 'Try fewer letters or another word — partial words work.'));
                open();
            }).catch(function (err) {
                if (err && err.name === 'AbortError') return;
                dd.removeAttribute('aria-busy');
                dd.replaceChildren(emptyState('Search is unavailable', 'Reload the page and try again.'));
                open();
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < MIN_CHARS) { if (!q) showRecent(); else close(); return; }
            timer = setTimeout(function () { search(q); }, 140);
        });
        input.addEventListener('focus', function () {
            var q = input.value.trim();
            if (q.length >= MIN_CHARS) search(q); else showRecent();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); if (dd.hidden) input.dispatchEvent(new Event('focus')); else view.move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); view.move(-1); }
            else if (e.key === 'Enter') {
                if (!dd.hidden && view.active > -1) { e.preventDefault(); view.pick(e); }
            } else if (e.key === 'Escape') {
                if (!dd.hidden) { e.preventDefault(); close(); } else if (input.value) { input.value = ''; }
                else input.blur();
            }
        });
        document.addEventListener('click', function (e) { if (wrap && !wrap.contains(e.target)) close(); });
        var kbd = wrap && wrap.querySelector('.hq-search-kbd');
        if (kbd) {
            kbd.textContent = /Mac|iPhone|iPad/.test(navigator.platform || '') ? '⌘K' : 'Ctrl K';
            kbd.addEventListener('mousedown', function (e) { e.preventDefault(); openPalette(input.value); });
        }
    }

    /* ========================================================= command palette */

    var pal = null;

    function quickActions() {
        var nav = window.MOVA_HQ_NAV || {};
        var acts = [
            { title: 'New content', hint: 'Write a page, post or any content type', icon: 'fa-solid fa-plus', url: '/hq/content/new', kw: 'new create write page post add' },
            { title: 'Upload media', hint: 'Images, video and documents', icon: 'fa-solid fa-cloud-arrow-up', url: '/hq/media', kw: 'upload media image file' },
            { title: 'Quick setup', hint: 'Generate a complete starter site', icon: 'fa-solid fa-wand-magic-sparkles', url: '/hq/setup-wizard', kw: 'setup wizard kit starter onboarding', admin: true },
            { title: 'Ask Mova AI', hint: 'Open the assistant (Ctrl+J)', icon: 'fa-solid fa-robot', kw: 'ai assistant chat help', run: function () { closePalette(); var b = document.getElementById('mova-ai-header-btn'); if (b) b.click(); } },
            { title: 'Toggle light / dark', hint: 'Switch the HQ theme', icon: 'fa-solid fa-circle-half-stroke', kw: 'theme dark light mode', run: function () { closePalette(); var b = document.querySelector('[data-theme-toggle]'); if (b) b.click(); } },
            { title: 'Brand & logo', hint: 'Identity, logo, favicon, theme', icon: 'fa-solid fa-swatchbook', url: '/hq/brand', kw: 'brand logo favicon theme identity', design: true },
            { title: 'Settings', hint: 'Site name, URL, timezone, AI', icon: 'fa-solid fa-gear', url: '/hq/settings', kw: 'settings general config', admin: true },
            { title: 'AI usage', hint: 'Requests and tokens by day and feature', icon: 'fa-solid fa-chart-column', url: '/hq/ai-usage', kw: 'ai usage tokens cost', admin: true },
            { title: 'View site', hint: 'Open the public site in a new tab', icon: 'fa-solid fa-arrow-up-right-from-square', kw: 'view site public preview', run: function () { closePalette(); window.open('/', '_blank', 'noopener'); } },
            { title: 'Rebuild search index', hint: 'Re-scan every HQ page and record', icon: 'fa-solid fa-arrows-rotate', kw: 'rebuild reindex search index', admin: true, run: rebuildIndex }
        ];
        var isAdmin = document.body.dataset.hqAdmin === '1';
        var canDesign = isAdmin || document.body.dataset.hqDesign === '1';
        return acts.filter(function (a) { return (!a.admin || isAdmin) && (!a.design || canDesign); });
    }

    function rebuildIndex() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var row = pal && pal.list.querySelector('.hq-sr-item.is-active .hq-sr-snippet');
        if (row) row.textContent = 'Rebuilding…';
        fetch(ENDPOINT + '/rebuild', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': meta ? meta.content : '' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            cache.clear();
            if (row) row.textContent = d && d.ok ? ('Indexed ' + d.rows + ' entries in ' + d.took_ms + ' ms (' + d.engine + ')') : ((d && d.error) || 'Rebuild failed');
        }).catch(function () { if (row) row.textContent = 'Rebuild failed'; });
    }

    function recentPages() {
        var h = (window.MovaHqNav && window.MovaHqNav.history) ? window.MovaHqNav.history() : { stack: [] };
        var seen = {}, out = [];
        for (var i = h.stack.length - 1; i >= 0 && out.length < 5; i--) {
            var e = h.stack[i];
            if (!e || seen[e.url] || e.url === location.pathname + location.search) continue;
            seen[e.url] = 1;
            out.push({ title: e.title, crumb: e.url, icon: 'fa-solid fa-clock-rotate-left', url: e.url });
        }
        return out;
    }

    function buildPalette() {
        var overlay = el('div', 'hq-palette-overlay');
        overlay.hidden = true;
        var dlg = el('div', 'hq-palette');
        dlg.setAttribute('role', 'dialog');
        dlg.setAttribute('aria-modal', 'true');
        dlg.setAttribute('aria-label', 'Command palette');
        var bar = el('div', 'hq-palette-bar');
        var ic = el('i', 'fa-solid fa-magnifying-glass'); ic.setAttribute('aria-hidden', 'true');
        var input = el('input', 'hq-palette-input');
        input.type = 'text';
        input.placeholder = 'Type a command, page, setting or content…';
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-controls', 'hq-palette-list');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-label', 'Search commands');
        input.autocomplete = 'off'; input.spellcheck = false;
        var esc = el('kbd', 'hq-sr-kbd', 'Esc');
        bar.appendChild(ic); bar.appendChild(input); bar.appendChild(esc);
        var list = el('div', 'hq-palette-list');
        list.id = 'hq-palette-list';
        list.setAttribute('role', 'listbox');
        dlg.appendChild(bar); dlg.appendChild(list);
        overlay.appendChild(dlg);
        document.body.appendChild(overlay);

        var view = new ListView(list, 'hq-pl-', function (it, e) {
            if (!it.run) { pushRecent(input.value); closePalette(); }
            go(it, e && (e.ctrlKey || e.metaKey));
        });
        view.input = input;
        var timer = 0, lastQ = null;

        function localMatch(q) {
            q = q.toLowerCase();
            return quickActions().filter(function (a) {
                return !q || (a.title + ' ' + a.kw + ' ' + a.hint).toLowerCase().indexOf(q) !== -1;
            }).map(function (a) { a.badge = a.run ? 'Action' : ''; return a; });
        }

        function refresh() {
            var q = input.value.trim();
            lastQ = q;
            var acts = localMatch(q);
            if (q.length < MIN_CHARS) {
                view.render([
                    { name: 'Quick actions', items: acts },
                    { name: 'Recent pages', items: recentPages() },
                    { name: 'Recent searches', items: readList(RECENT_Q).slice(0, 4).map(function (s) {
                        return { title: s, icon: 'fa-solid fa-magnifying-glass', run: function () { input.value = s; refresh(); } };
                    }) }
                ], footerHint('↑↓ navigate · Enter open · Ctrl+Enter new tab · Esc close'));
                return;
            }
            clearTimeout(timer);
            view.render([{ name: 'Quick actions', items: acts }], footerHint('Searching…'));
            timer = setTimeout(function () {
                fetchResults(q).then(function (data) {
                    if (q !== lastQ) return;
                    // Exact page-prefix matches ("sec" → Security) outrank quick actions.
                    var ql = q.toLowerCase(), top = [], rest = [];
                    (data.groups || []).forEach(function (g) {
                        if (g.name !== 'Pages' && g.name !== 'Settings & fields') { rest.push(g); return; }
                        var keep = [];
                        (g.items || []).forEach(function (it) {
                            if (top.length < 3 && String(it.title || '').toLowerCase().indexOf(ql) === 0) top.push(it);
                            else keep.push(it);
                        });
                        if (keep.length) rest.push({ name: g.name, items: keep });
                    });
                    var groups = (top.length ? [{ name: 'Top match', items: top }] : [])
                        .concat(acts.length ? [{ name: 'Quick actions', items: acts }] : [])
                        .concat(rest);
                    var n = view.render(groups, footerHint((data.total || 0) + ' results · ' + (data.took_ms || 0) + ' ms · Enter to open'));
                    if (!n) list.replaceChildren(emptyState('No matches for "' + q + '"', 'Partial words work — try fewer letters.'));
                }).catch(function (err) { if (!err || err.name !== 'AbortError') list.replaceChildren(emptyState('Search is unavailable')); });
            }, 120);
        }

        input.addEventListener('input', refresh);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); view.move(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); view.move(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); view.pick(e); }
            else if (e.key === 'Escape') { e.preventDefault(); closePalette(); }
            else if (e.key === 'Tab') { e.preventDefault(); view.move(e.shiftKey ? -1 : 1); } // focus trap
        });
        overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) closePalette(); });
        return { overlay: overlay, input: input, list: list, refresh: refresh, returnFocus: null };
    }

    function openPalette(prefill) {
        if (!pal) pal = buildPalette();
        if (!pal.overlay.hidden) { pal.input.select(); return; }
        pal.returnFocus = document.activeElement;
        pal.overlay.hidden = false;
        document.documentElement.classList.add('hq-palette-open');
        pal.input.value = prefill || '';
        pal.refresh();
        requestAnimationFrame(function () { pal.input.focus(); pal.input.select(); });
    }
    function closePalette() {
        if (!pal || pal.overlay.hidden) return;
        pal.overlay.hidden = true;
        document.documentElement.classList.remove('hq-palette-open');
        if (pal.returnFocus && pal.returnFocus.focus) { try { pal.returnFocus.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
    }

    function initShortcuts() {
        document.addEventListener('keydown', function (e) {
            var k = (e.key || '').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && k === 'k') {
                if (e.defaultPrevented || (e.target && e.target.isContentEditable)) return; // editor uses Ctrl+K for links
                e.preventDefault();
                if (pal && !pal.overlay.hidden) closePalette(); else openPalette('');
            } else if (k === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !isTyping(e.target)) {
                var input = document.getElementById('hq-global-search');
                if (input && input.offsetParent !== null) { e.preventDefault(); input.focus(); }
                else { e.preventDefault(); openPalette(''); }
            }
        });
    }

    /* ============================================================== deep links */

    function norm(s) { return String(s || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

    function deepLink() {
        var m = /(?:^#|&)hqf=([^&]+)/.exec(location.hash || '');
        if (!m) return;
        var needle;
        try { needle = norm(decodeURIComponent(m[1].replace(/\+/g, ' '))); } catch (e) { return; }
        if (!needle) return;
        var main = document.querySelector('.hq-main') || document.querySelector('main') || document.body;
        var sel = 'h1,h2,h3,h4,h5,h6,legend,label,summary,th,dt,button,.btn,.card-title,.hq-card-title,.plugin-name,.theme-name,strong,p,small,li,a,option';
        var nodes = Array.prototype.slice.call(main.querySelectorAll(sel)).filter(function (n) {
            return !n.closest('#hq-contextual-nav, .hq-line1, .hq-palette-overlay, script, style, template');
        });
        var hit = null;
        for (var pass = 0; pass < 3 && !hit; pass++) {
            for (var i = 0; i < nodes.length; i++) {
                var t = norm(nodes[i].textContent);
                if (!t) continue;
                if ((pass === 0 && t === needle) || (pass === 1 && t.indexOf(needle) === 0 && t.length < needle.length + 40)
                    || (pass === 2 && t.indexOf(needle) !== -1 && t.length < 400)) { hit = nodes[i]; break; }
            }
        }
        if (!hit) {
            // input placeholders / names
            var inputs = main.querySelectorAll('input,textarea,select');
            for (var j = 0; j < inputs.length && !hit; j++) {
                var ph = norm(inputs[j].getAttribute('placeholder') || inputs[j].getAttribute('aria-label') || '');
                if (ph && ph.indexOf(needle) !== -1) hit = inputs[j];
            }
        }
        if (!hit) return;
        if (hit.tagName === 'OPTION') hit = hit.closest('select') || hit;
        // Reveal hidden ancestors: layer panels, <details>, mailbox/tab panels
        var panel = hit.closest('[data-layer-panel]');
        if (panel && (panel.hidden || !panel.classList.contains('is-active')) && window.MovaHqNav) {
            window.MovaHqNav.activateLayer(panel.getAttribute('data-layer-panel'));
        }
        var det = hit.closest('details');
        while (det) { det.open = true; det = det.parentElement && det.parentElement.closest('details'); }
        // If the hit is a label, flash its field group
        var target = hit;
        if (hit.tagName === 'LABEL') target = hit.closest('.form-group, .field, .hq-field, .setting-row') || hit;
        else if (hit.tagName === 'LEGEND') target = hit.closest('fieldset') || hit;
        else if (/^H[1-6]$/.test(hit.tagName) && hit.parentElement && hit.parentElement.matches('.card-header, .hq-card-head, header')) target = hit.parentElement.parentElement || hit;
        setTimeout(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.remove('hq-flash');
            void target.offsetWidth;
            target.classList.add('hq-flash');
            setTimeout(function () { target.classList.remove('hq-flash'); }, 2600);
            var f = hit.tagName === 'LABEL' && hit.htmlFor ? document.getElementById(hit.htmlFor) : null;
            if (f && f.focus) { try { f.focus({ preventScroll: true }); } catch (e) { /* ignore */ } }
        }, 60);
    }

    function boot() {
        initHeaderSearch();
        initShortcuts();
        setTimeout(deepLink, 30);
        window.addEventListener('hashchange', deepLink);
        window.MovaHqSearch = { open: openPalette, close: closePalette, deepLink: deepLink };
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
