/*!
 * MovaTime — shared HQ time formatter (pairs with PHP Mova\Core\Time / hqTime()).
 * Stored times are UTC; display uses the site timezone (window.MOVA_TZ, default Africa/Lagos).
 *   MovaTime.format(v)  "23 Oct 2026, 9:00 PM"
 *   MovaTime.date(v)    "23 Oct 2026"
 *   MovaTime.ago(v)     "5 min ago"
 *   MovaTime.both(v)    "23 Oct 2026, 9:00 PM · 5 min ago"
 *   MovaTime.html(v, mode)  <time> element string (escaped)
 * <time class="hq-time" datetime data-time-mode="rel|both"> elements refresh every minute.
 */
(function (w, d) {
  'use strict';
  var DEFAULT = 'Africa/Lagos';
  function tz() {
    var z = w.MOVA_TZ || (d.body && d.body.getAttribute('data-hq-tz')) || DEFAULT;
    try { new Intl.DateTimeFormat('en-GB', { timeZone: z }); return z; } catch (e) { return DEFAULT; }
  }
  function parse(v) {
    if (v == null || v === '' || v === 0 || v === '0') return null;
    if (v instanceof Date) return isNaN(v) ? null : v;
    if (typeof v === 'number' || /^\d{9,}$/.test(String(v))) {
      var n = Number(v); return new Date(n < 1e12 ? n * 1000 : n);
    }
    var s = String(v).trim();
    // "2026-10-23 21:00:00" (SQLite UTC, no offset) → treat as UTC
    if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/.test(s)) s = s.replace(' ', 'T') + 'Z';
    else if (/^\d{4}-\d{2}-\d{2}$/.test(s)) s += 'T00:00:00Z';
    var dt = new Date(s);
    return isNaN(dt) ? null : dt;
  }
  function parts(dt, opts) {
    var o = { timeZone: tz() }; for (var k in opts) o[k] = opts[k];
    var out = {}; new Intl.DateTimeFormat('en-GB', o).formatToParts(dt).forEach(function (p) { out[p.type] = p.value; });
    return out;
  }
  function date(v, fb) {
    var dt = parse(v); if (!dt) return fb == null ? '—' : fb;
    var p = parts(dt, { day: 'numeric', month: 'short', year: 'numeric' });
    return p.day + ' ' + p.month + ' ' + p.year;
  }
  function format(v, fb) {
    var dt = parse(v); if (!dt) return fb == null ? '—' : fb;
    var p = parts(dt, { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
    var per = (p.dayPeriod || '').toUpperCase().replace(/\./g, '');
    return p.day + ' ' + p.month + ' ' + p.year + ', ' + p.hour + ':' + p.minute + (per ? ' ' + per : '');
  }
  function ago(v, fb) {
    var dt = parse(v); if (!dt) return fb == null ? '—' : fb;
    var s = Math.round((Date.now() - dt.getTime()) / 1000), fut = s < 0, a = Math.abs(s), t;
    if (a < 45) return fut ? 'in a moment' : 'just now';
    if (a < 3600) t = Math.max(1, Math.round(a / 60)) + ' min';
    else if (a < 86400) t = Math.round(a / 3600) + ' h';
    else if (a < 7 * 86400) { var n = Math.round(a / 86400); if (!fut && n === 1) return 'yesterday'; t = n + ' day' + (n === 1 ? '' : 's'); }
    else return date(dt);
    return fut ? 'in ' + t : t + ' ago';
  }
  function both(v, fb) { var dt = parse(v); return dt ? format(dt) + ' · ' + ago(dt) : (fb == null ? '—' : fb); }
  var ABBR = { 'Africa/Lagos': 'WAT', 'UTC': 'UTC', 'Etc/UTC': 'UTC', 'Africa/Accra': 'GMT', 'Europe/London': null };
  function abbr() {
    if (ABBR[tz()]) return ABBR[tz()];
    try { var p = parts(new Date(), { timeZoneName: 'short' }); return p.timeZoneName || tz(); } catch (e) { return tz(); }
  }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function text(dt, mode) { return mode === 'rel' ? ago(dt) : mode === 'date' ? date(dt) : mode === 'abs' ? format(dt) : both(dt); }
  function html(v, mode, fb) {
    var dt = parse(v); mode = mode || 'both';
    if (!dt) return '<span class="hq-time is-empty">' + esc(fb == null ? '—' : fb) + '</span>';
    return '<time class="hq-time" datetime="' + dt.toISOString().replace(/\.\d{3}Z$/, 'Z') + '" data-time-mode="' + esc(mode) + '" title="' + esc(format(dt) + ' ' + abbr()) + '">' + esc(text(dt, mode)) + '</time>';
  }
  function refresh(root) {
    (root || d).querySelectorAll('time.hq-time[data-time-mode="rel"], time.hq-time[data-time-mode="both"]').forEach(function (el) {
      var dt = parse(el.getAttribute('datetime')); if (dt) el.textContent = text(dt, el.getAttribute('data-time-mode'));
    });
  }
  w.MovaTime = { tz: tz, parse: parse, format: format, date: date, ago: ago, both: both, html: html, abbr: abbr, refresh: refresh };
  setInterval(function () { if (!d.hidden) refresh(); }, 60000);
})(window, document);
