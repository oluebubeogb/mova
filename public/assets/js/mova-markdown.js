/**
 * Mova — shared Markdown helpers (paste + optional AI insert).
 * Safe subset aligned with app/Content/Markdown.php.
 * Does not alter Dev/Studio HTML paths.
 */
(function (global) {
  'use strict';

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function safeUrl(url) {
    url = String(url || '').trim();
    if (!url) return null;
    if (/^(javascript|data|vbscript):/i.test(url)) return null;
    if (/^(https?:|mailto:|\/|\.\/|\.\.\/|#)/i.test(url)) return url;
    if (/^[a-zA-Z0-9][a-zA-Z0-9._~:/?#\[\]@!$&'()*+,;=%-]*$/.test(url)) return url;
    return null;
  }

  /**
   * True when text looks like Markdown and is not structured HTML.
   */
  function looksLikeMarkdown(text) {
    if (!text || typeof text !== 'string') return false;
    var t = text.trim();
    if (!t) return false;
    if (/<(p|div|h[1-6]|ul|ol|li|table|thead|tbody|tr|td|th|article|section|header|footer|nav|main|figure|blockquote|pre|form|style|script|iframe|svg|video)\b/i.test(t)) {
      return false;
    }
    return /(\*\*[^*]+\*\*|__[^_\s][^_]*__|(?<!\*)\*(?!\*)([^*\n]+)\*(?!\*)|^#{1,6}\s+\S|^\s*[-*+]\s+\S|^\s*\d+\.\s+\S|`[^`\n]+`|\[[^\]]+\]\([^)\s]+\)|^>\s+\S)/m.test(t);
  }

  function looksLikeHtml(text) {
    if (!text || typeof text !== 'string') return false;
    return /<(p|div|h[1-6]|ul|ol|li|table|article|section|pre|style|script|iframe)\b/i.test(text.trim());
  }

  function inline(text) {
    var s = escapeHtml(text);

    s = s.replace(/`([^`]+)`/g, function (_, code) {
      return '<code>' + code + '</code>';
    });

    s = s.replace(/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, function (_, alt, url) {
      var src = safeUrl(url);
      if (!src) return _;
      return '<img src="' + escapeHtml(src) + '" alt="' + alt + '" loading="lazy">';
    });

    s = s.replace(/\[([^\]]+)\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g, function (_, label, url) {
      var href = safeUrl(url);
      if (!href) return _;
      return '<a href="' + escapeHtml(href) + '">' + label + '</a>';
    });

    s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    s = s.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    s = s.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>');
    s = s.replace(/(?<!\w)_([^_]+)_(?!\w)/g, '<em>$1</em>');
    s = s.replace(/\n/g, '<br>\n');
    return s;
  }

  /**
   * Convert Markdown string to HTML (subset).
   */
  function toHtml(md) {
    if (!md || typeof md !== 'string') return '';
    md = md.replace(/\r\n/g, '\n').replace(/\r/g, '\n').trim();
    if (!md) return '';

    var codeBlocks = [];
    md = md.replace(/```([a-zA-Z0-9_-]*)\n?([\s\S]*?)```/g, function (_, lang, code) {
      var idx = codeBlocks.length;
      codeBlocks.push('<pre><code>' + escapeHtml(code.replace(/\n$/, '')) + '</code></pre>');
      return '\n\n%%CODEBLOCK' + idx + '%%\n\n';
    });

    var lines = md.split('\n');
    var out = [];
    var para = [];
    var listType = null;
    var listItems = [];

    function flushPara() {
      if (!para.length) return;
      var text = para.join('\n').trim();
      para = [];
      if (!text) return;
      out.push('<p>' + inline(text) + '</p>');
    }

    function flushList() {
      if (!listType || !listItems.length) {
        listType = null;
        listItems = [];
        return;
      }
      var html = '<' + listType + '>';
      for (var i = 0; i < listItems.length; i++) {
        html += '<li>' + inline(listItems[i]) + '</li>';
      }
      html += '</' + listType + '>';
      out.push(html);
      listType = null;
      listItems = [];
    }

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];
      var m;

      m = line.trim().match(/^%%CODEBLOCK(\d+)%%$/);
      if (m) {
        flushPara();
        flushList();
        out.push(codeBlocks[parseInt(m[1], 10)] || '');
        continue;
      }

      if (line.trim() === '') {
        flushPara();
        flushList();
        continue;
      }

      m = line.match(/^(#{1,6})\s+(.+)$/);
      if (m) {
        flushPara();
        flushList();
        var level = m[1].length;
        out.push('<h' + level + '>' + inline(m[2].trim()) + '</h' + level + '>');
        continue;
      }

      m = line.match(/^>\s?(.*)$/);
      if (m) {
        flushPara();
        flushList();
        out.push('<blockquote><p>' + inline(m[1].trim()) + '</p></blockquote>');
        continue;
      }

      m = line.match(/^\s*([-*+])\s+(.+)$/);
      if (m) {
        flushPara();
        if (listType && listType !== 'ul') flushList();
        listType = 'ul';
        listItems.push(m[2]);
        continue;
      }

      m = line.match(/^\s*\d+\.\s+(.+)$/);
      if (m) {
        flushPara();
        if (listType && listType !== 'ol') flushList();
        listType = 'ol';
        listItems.push(m[1]);
        continue;
      }

      if (/^(-{3,}|\*{3,}|_{3,})\s*$/.test(line.trim())) {
        flushPara();
        flushList();
        out.push('<hr>');
        continue;
      }

      m = line.match(/^\s{2,}(.+)$/);
      if (listType && m) {
        listItems[listItems.length - 1] += ' ' + m[1];
        continue;
      }

      flushList();
      para.push(line);
    }

    flushPara();
    flushList();

    var html = out.join('\n');
    html = html.replace(/%%CODEBLOCK(\d+)%%/g, function (_, idx) {
      return codeBlocks[parseInt(idx, 10)] || '';
    });
    return html;
  }

  /**
   * Convert only if looks like Markdown; otherwise return original.
   */
  function maybeToHtml(text) {
    if (!looksLikeMarkdown(text)) return text;
    return toHtml(text);
  }

  /**
   * Insert HTML at the current selection inside a contenteditable element.
   */
  function insertHtmlAtSelection(html) {
    if (document.queryCommandSupported && document.queryCommandSupported('insertHTML')) {
      document.execCommand('insertHTML', false, html);
      return true;
    }
    var sel = global.getSelection && global.getSelection();
    if (!sel || !sel.rangeCount) return false;
    var range = sel.getRangeAt(0);
    range.deleteContents();
    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var frag = document.createDocumentFragment();
    var node;
    while ((node = tmp.firstChild)) {
      frag.appendChild(node);
    }
    range.insertNode(frag);
    range.collapse(false);
    sel.removeAllRanges();
    sel.addRange(range);
    return true;
  }

  /**
   * Attach paste handler to a contenteditable editor.
   * Options: { bodyInput: HTMLTextAreaElement|null, onChange: function }
   */
  function bindPaste(editor, options) {
    if (!editor || !editor.isContentEditable) return;
    options = options || {};

    editor.addEventListener('paste', function (e) {
      var cd = e.clipboardData || global.clipboardData;
      if (!cd) return;

      var plain = '';
      try {
        plain = cd.getData('text/plain') || '';
      } catch (err) {
        plain = '';
      }
      var htmlClip = '';
      try {
        htmlClip = cd.getData('text/html') || '';
      } catch (err2) {
        htmlClip = '';
      }

      // Rich HTML from Word/Docs/etc. — keep browser default (or clean path)
      if (htmlClip && htmlClip.replace(/<[^>]+>/g, '').trim().length > 0 && !looksLikeMarkdown(plain)) {
        return;
      }

      if (!plain || !looksLikeMarkdown(plain)) {
        return;
      }

      e.preventDefault();
      var converted = toHtml(plain);
      insertHtmlAtSelection(converted);

      if (options.bodyInput) {
        options.bodyInput.value = editor.innerHTML;
      }
      if (typeof options.onChange === 'function') {
        options.onChange(editor.innerHTML);
      }
      // Fire input so existing listeners stay in sync
      try {
        editor.dispatchEvent(new Event('input', { bubbles: true }));
      } catch (err3) {}
    });
  }

  global.MovaMarkdown = {
    toHtml: toHtml,
    maybeToHtml: maybeToHtml,
    looksLikeMarkdown: looksLikeMarkdown,
    looksLikeHtml: looksLikeHtml,
    bindPaste: bindPaste,
    insertHtmlAtSelection: insertHtmlAtSelection,
    escapeHtml: escapeHtml
  };
})(typeof window !== 'undefined' ? window : this);
