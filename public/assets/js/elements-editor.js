/**
 * Mova HQ — Elements editor (Phases 1–3)
 * Vanilla JS + CodeMirror 5 (no ESM — UI always works even if CM fails)
 */
(function () {
  'use strict';

  function boot() {
    var root = document.getElementById('el-cols');
    if (!root) return;

    var MAX_VISIBLE = 15;
    var PSEUDOS = ['hover', 'focus', 'focus-visible', 'first-child', 'last-child'];

    var GROUPS = [
      { id: 'typography', label: 'Typography', icon: 'fa-font', fields: [
        { prop: 'font-family', label: 'Font', type: 'text', placeholder: 'Inter, system-ui, sans-serif' },
        { prop: 'font-size', label: 'Size', type: 'text', placeholder: '1.5rem' },
        { prop: 'font-weight', label: 'Weight', type: 'select', options: ['', '300', '400', '500', '600', '700', '800'] },
        { prop: 'line-height', label: 'Line height', type: 'text', placeholder: '1.5' },
        { prop: 'letter-spacing', label: 'Letter spacing', type: 'text', placeholder: '0.02em' },
        { prop: 'text-align', label: 'Alignment', type: 'select', options: ['', 'left', 'center', 'right', 'justify'] },
        { prop: 'text-decoration', label: 'Decoration', type: 'select', options: ['', 'none', 'underline', 'line-through'] },
        { prop: 'text-transform', label: 'Transform', type: 'select', options: ['', 'none', 'uppercase', 'lowercase', 'capitalize'] }
      ]},
      { id: 'colors', label: 'Colors', icon: 'fa-droplet', fields: [
        { prop: 'color', label: 'Text', type: 'colortext' },
        { prop: 'background-color', label: 'Background', type: 'colortext' },
        { prop: 'border-color', label: 'Border', type: 'colortext' }
      ]},
      { id: 'spacing', label: 'Spacing', icon: 'fa-up-down-left-right', fields: [
        { prop: 'margin', label: 'Margin', type: 'text', placeholder: '0 0 1rem' },
        { prop: 'padding', label: 'Padding', type: 'text', placeholder: '0.75rem 1rem' },
        { prop: 'gap', label: 'Gap', type: 'text', placeholder: '1rem' }
      ]},
      { id: 'size', label: 'Size', icon: 'fa-maximize', fields: [
        { prop: 'width', label: 'Width', type: 'text', placeholder: 'auto' },
        { prop: 'height', label: 'Height', type: 'text', placeholder: 'auto' },
        { prop: 'min-width', label: 'Min width', type: 'text' },
        { prop: 'max-width', label: 'Max width', type: 'text' },
        { prop: 'min-height', label: 'Min height', type: 'text' },
        { prop: 'max-height', label: 'Max height', type: 'text' },
        { prop: 'aspect-ratio', label: 'Aspect ratio', type: 'text', placeholder: '16 / 9' }
      ]},
      { id: 'border', label: 'Border', icon: 'fa-square', fields: [
        { prop: 'border-width', label: 'Width', type: 'text', placeholder: '1px' },
        { prop: 'border-style', label: 'Style', type: 'select', options: ['', 'none', 'solid', 'dashed', 'dotted', 'double'] },
        { prop: 'border-radius', label: 'Radius', type: 'text', placeholder: '8px' }
      ]},
      { id: 'layout', label: 'Layout', icon: 'fa-table-cells-large', fields: [
        { prop: 'display', label: 'Display', type: 'select', options: ['', 'block', 'inline', 'inline-block', 'flex', 'grid', 'none'] },
        { prop: 'position', label: 'Position', type: 'select', options: ['', 'static', 'relative', 'absolute', 'fixed', 'sticky'] },
        { prop: 'top', label: 'Top', type: 'text' }, { prop: 'right', label: 'Right', type: 'text' },
        { prop: 'bottom', label: 'Bottom', type: 'text' }, { prop: 'left', label: 'Left', type: 'text' },
        { prop: 'z-index', label: 'Z-index', type: 'text' },
        { prop: 'overflow', label: 'Overflow', type: 'select', options: ['', 'visible', 'hidden', 'scroll', 'auto'] }
      ]},
      { id: 'flexbox', label: 'Flexbox', icon: 'fa-grip', fields: [
        { prop: 'flex-direction', label: 'Direction', type: 'select', options: ['', 'row', 'row-reverse', 'column', 'column-reverse'] },
        { prop: 'flex-wrap', label: 'Wrap', type: 'select', options: ['', 'nowrap', 'wrap', 'wrap-reverse'] },
        { prop: 'justify-content', label: 'Justify', type: 'select', options: ['', 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'] },
        { prop: 'align-items', label: 'Align items', type: 'select', options: ['', 'stretch', 'flex-start', 'center', 'flex-end', 'baseline'] },
        { prop: 'flex', label: 'Flex', type: 'text', placeholder: '1 1 auto' }
      ]},
      { id: 'grid', label: 'Grid', icon: 'fa-border-all', fields: [
        { prop: 'grid-template-columns', label: 'Columns', type: 'text', placeholder: '1fr 1fr' },
        { prop: 'grid-template-rows', label: 'Rows', type: 'text', placeholder: 'auto' },
        { prop: 'grid-gap', label: 'Gap', type: 'text', placeholder: '1rem' },
        { prop: 'place-items', label: 'Place items', type: 'text', placeholder: 'center' }
      ]},
      { id: 'background', label: 'Background', icon: 'fa-image', fields: [
        { prop: 'background-color', label: 'Color', type: 'colortext' },
        { prop: 'background-image', label: 'Image / gradient', type: 'text', placeholder: 'linear-gradient(135deg, #2563eb, #7c3aed)' },
        { prop: 'background-size', label: 'Size', type: 'select', options: ['', 'auto', 'cover', 'contain', '100% 100%'] },
        { prop: 'background-position', label: 'Position', type: 'text', placeholder: 'center center' },
        { prop: 'background-repeat', label: 'Repeat', type: 'select', options: ['', 'no-repeat', 'repeat', 'repeat-x', 'repeat-y'] }
      ]},
      { id: 'effects', label: 'Effects', icon: 'fa-wand-magic-sparkles', fields: [
        { prop: 'box-shadow', label: 'Shadow', type: 'text', placeholder: '0 4px 12px rgba(0,0,0,0.12)' },
        { prop: 'opacity', label: 'Opacity', type: 'text', placeholder: '1' },
        { prop: 'filter', label: 'Filter', type: 'text', placeholder: 'blur(0px) brightness(1)' },
        { prop: 'backdrop-filter', label: 'Backdrop filter', type: 'text', placeholder: 'blur(8px)' },
        { prop: 'mix-blend-mode', label: 'Blend mode', type: 'select', options: ['', 'normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'difference'] }
      ]},
      { id: 'transform', label: 'Transform', icon: 'fa-rotate', fields: [
        { prop: 'transform', label: 'Transform', type: 'text', placeholder: 'translateY(-4px) scale(1.02)' },
        { prop: 'transform-origin', label: 'Origin', type: 'text', placeholder: 'center center' }
      ]},
      { id: 'animation', label: 'Animation', icon: 'fa-film', fields: [
        { prop: 'transition', label: 'Transition', type: 'text', placeholder: 'all 0.2s ease' },
        { prop: 'transition-duration', label: 'Duration', type: 'text', placeholder: '0.2s' },
        { prop: 'transition-timing-function', label: 'Timing', type: 'select', options: ['', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'linear'] },
        { prop: 'animation', label: 'Animation', type: 'text', placeholder: 'fade 0.4s ease' },
        { prop: 'cursor', label: 'Cursor', type: 'select', options: ['', 'auto', 'pointer', 'default', 'text', 'move', 'not-allowed'] }
      ]},
      { id: 'targeting', label: 'Targeting', icon: 'fa-bullseye', targeting: true },
      { id: 'advanced', label: 'Advanced', icon: 'fa-terminal', customCss: true, customJs: true }
    ];

    var PREVIEWS = {
      h1: '<h1>Heading one</h1>', h2: '<h2>Heading two</h2>', h3: '<h3>Heading three</h3>',
      h4: '<h4>Heading four</h4>', h5: '<h5>Heading five</h5>', h6: '<h6>Heading six</h6>',
      p: '<p>A short paragraph for preview. Content that moves.</p>',
      a: '<p>Visit the <a href="#">sample link</a> in context.</p>',
      button: '<button type="button" class="mova-btn">Sample button</button>',
      img: '<img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22160%22 height=%2290%22%3E%3Crect fill=%22%233b82f6%22 width=%22160%22 height=%2290%22/%3E%3Ctext x=%2280%22 y=%2248%22 fill=%22white%22 text-anchor=%22middle%22 font-size=%2214%22%3EImage%3C/text%3E%3C/svg%3E" alt="Sample">',
      table: '<table><thead><tr><th>Name</th><th>Role</th></tr></thead><tbody><tr><td>Ada</td><td>Editor</td></tr><tr><td>Lin</td><td>Author</td></tr></tbody></table>',
      tr: '<table><tr><td>Cell A</td><td>Cell B</td><td>Cell C</td></tr></table>',
      td: '<table><tr><td>Table cell</td></tr></table>', th: '<table><tr><th>Header cell</th></tr></table>',
      ul: '<ul><li>First item</li><li>Second item</li><li>Third item</li></ul>',
      ol: '<ol><li>Step one</li><li>Step two</li><li>Step three</li></ol>', li: '<ul><li>List item</li></ul>',
      hr: '<p>Above the rule</p><hr><p>Below the rule</p>',
      blockquote: '<blockquote>A quoted line for style preview.</blockquote>',
      code: '<p>Inline <code>code sample</code> here.</p>',
      pre: '<pre>function hello() {\n  return true;\n}</pre>',
      section: '<section><h3>Section</h3><p>Section body text.</p></section>',
      article: '<article><h3>Article</h3><p>Article body text.</p></article>',
      header: '<header><strong>Header region</strong></header>',
      footer: '<footer><small>Footer region</small></footer>',
      nav: '<nav><a href="#">Home</a> · <a href="#">Docs</a></nav>',
      div: '<div>Division block</div>', span: '<p>Inline <span>span text</span> sample.</p>',
      form: '<form onsubmit="return false"><label>Label <input type="text" value="Sample"></label></form>',
      input: '<label>Input <input type="text" value="Sample input"></label>', label: '<label>Form label</label>'
    };

    function previewHtml(tag) {
      return PREVIEWS[tag] || ('<' + tag + '>' + tag + ' element</' + tag + '>');
    }

    function emptyState() {
      function chunk() {
        var c = { props: {}, custom_css: '' };
        PSEUDOS.forEach(function (p) { c[p] = { props: {}, custom_css: '' }; });
        return c;
      }
      return {
        desktop: chunk(), tablet: chunk(), mobile: chunk(),
        target: { class: '', id: '' },
        custom_js: '',
        enable_js: false
      };
    }

    function normalizeLoaded(d) {
      var out = emptyState();
      if (!d || typeof d !== 'object') return out;
      ['desktop', 'tablet', 'mobile'].forEach(function (bp) {
        if (!d[bp]) return;
        out[bp].props = Object.assign({}, d[bp].props || {});
        out[bp].custom_css = d[bp].custom_css || '';
        PSEUDOS.forEach(function (p) {
          if (d[bp][p]) {
            out[bp][p] = {
              props: Object.assign({}, d[bp][p].props || {}),
              custom_css: d[bp][p].custom_css || ''
            };
          }
        });
      });
      if (d.props && !d.desktop) {
        out.desktop.props = Object.assign({}, d.props);
        out.desktop.custom_css = d.custom_css || '';
      }
      if (d.target) {
        out.target = { class: d.target.class || '', id: d.target.id || '' };
      }
      out.custom_js = d.custom_js || '';
      out.enable_js = !!d.enable_js;
      return out;
    }

    var allTags = [];
    var topTags = [];
    var styled = new Set();
    try {
      allTags = JSON.parse(root.getAttribute('data-all-tags') || '[]');
      topTags = JSON.parse(root.getAttribute('data-top-tags') || '[]');
      styled = new Set(JSON.parse(root.getAttribute('data-styled') || '[]'));
    } catch (e) {}

    var currentTag = root.getAttribute('data-initial-tag') || 'h1';
    var breakpoint = 'desktop';
    var pseudo = 'base';
    var data = emptyState();
    var dirty = false;
    var cm = null;
    var suppressCm = false;

    var tagList = document.getElementById('el-tag-list');
    var search = document.getElementById('el-tag-search');
    var groupsEl = document.getElementById('el-css-groups');
    var previewContent = document.getElementById('el-preview-content');
    var previewFrame = document.getElementById('el-preview-frame');
    var tagInput = document.getElementById('el-tag-input');
    var propsJson = document.getElementById('el-props-json');
    var customCssInput = document.getElementById('el-custom-css');
    var bpJson = document.getElementById('el-breakpoints-json');
    var entryJson = document.getElementById('el-entry-json');
    var activeLabel = document.getElementById('el-active-tag-label');
    var dirtyEl = document.getElementById('el-dirty');
    var form = document.getElementById('el-form');

    var styleEl = document.getElementById('el-preview-style');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'el-preview-style';
      document.head.appendChild(styleEl);
    }

    function activeSlice() {
      var bp = data[breakpoint] || data.desktop;
      if (pseudo === 'base') return bp;
      if (!bp[pseudo]) bp[pseudo] = { props: {}, custom_css: '' };
      return bp[pseudo];
    }

    function setDirty(v) {
      dirty = !!v;
      if (dirtyEl) dirtyEl.hidden = !dirty;
    }

    function renderTagList(filter) {
      if (!tagList) return;
      var q = (filter || '').trim().toLowerCase();
      var items = !q
        ? topTags.slice(0, MAX_VISIBLE)
        : allTags.filter(function (t) { return t.indexOf(q) !== -1; }).slice(0, MAX_VISIBLE);
      tagList.innerHTML = '';
      if (!items.length) {
        tagList.innerHTML = '<li class="el-tag-empty">No tags match</li>';
        return;
      }
      items.forEach(function (tag) {
        var li = document.createElement('li');
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'el-tag-item' + (tag === currentTag ? ' is-active' : '');
        btn.setAttribute('data-tag', tag);
        btn.innerHTML =
          '<span class="el-tag-name">&lt;' + tag + '&gt;</span>' +
          (styled.has(tag) ? '<span class="el-tag-badge" title="Has styles">●</span>' : '');
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          selectTag(tag);
        });
        li.appendChild(btn);
        tagList.appendChild(li);
      });
    }

    function fieldRow(label, id, placeholder, onInput) {
      var row = document.createElement('div');
      row.className = 'el-field';
      var lab = document.createElement('label');
      lab.textContent = label;
      lab.htmlFor = id;
      var input = document.createElement('input');
      input.type = 'text';
      input.id = id;
      input.placeholder = placeholder || '';
      input.addEventListener('input', onInput);
      row.appendChild(lab);
      row.appendChild(input);
      return row;
    }

    function buildGroups() {
      if (!groupsEl) return;
      groupsEl.innerHTML = '';
      GROUPS.forEach(function (g) {
        var box = document.createElement('div');
        box.className = 'el-group' + (g.id === 'typography' ? ' is-open' : '');
        var head = document.createElement('button');
        head.type = 'button';
        head.className = 'el-group-toggle';
        head.innerHTML = '<span><i class="fa-solid ' + g.icon + '"></i> ' + g.label + '</span><i class="fa-solid fa-chevron-down el-group-chevron"></i>';
        head.addEventListener('click', function (e) {
          e.preventDefault();
          box.classList.toggle('is-open');
          if (box.classList.contains('is-open') && g.customCss && cm) {
            setTimeout(function () { cm.refresh(); }, 50);
          }
        });
        var body = document.createElement('div');
        body.className = 'el-group-body';

        (g.fields || []).forEach(function (f) {
          var row = document.createElement('div');
          row.className = 'el-field';
          var lab = document.createElement('label');
          lab.textContent = f.label;
          lab.htmlFor = 'el-prop-' + f.prop;
          row.appendChild(lab);
          if (f.type === 'select') {
            var sel = document.createElement('select');
            sel.id = 'el-prop-' + f.prop;
            sel.setAttribute('data-prop', f.prop);
            f.options.forEach(function (o) {
              var opt = document.createElement('option');
              opt.value = o;
              opt.textContent = o === '' ? '—' : o;
              sel.appendChild(opt);
            });
            sel.addEventListener('change', onPropChange);
            row.appendChild(sel);
          } else if (f.type === 'colortext') {
            var wrap = document.createElement('div');
            wrap.className = 'el-color-row';
            var color = document.createElement('input');
            color.type = 'color';
            color.setAttribute('data-prop', f.prop);
            color.value = '#000000';
            var text = document.createElement('input');
            text.type = 'text';
            text.id = 'el-prop-' + f.prop;
            text.setAttribute('data-prop', f.prop);
            text.placeholder = '#000000 or var(--color-text)';
            color.addEventListener('input', function () {
              text.value = color.value;
              onPropChange({ target: text });
            });
            text.addEventListener('input', onPropChange);
            wrap.appendChild(color);
            wrap.appendChild(text);
            row.appendChild(wrap);
          } else {
            var input = document.createElement('input');
            input.type = 'text';
            input.id = 'el-prop-' + f.prop;
            input.setAttribute('data-prop', f.prop);
            if (f.placeholder) input.placeholder = f.placeholder;
            input.addEventListener('input', onPropChange);
            row.appendChild(input);
          }
          body.appendChild(row);
        });

        if (g.targeting) {
          body.appendChild(fieldRow('CSS class', 'el-target-class', 'my-class', function () {
            data.target.class = (document.getElementById('el-target-class').value || '').trim();
            setDirty(true);
            updatePreview();
          }));
          body.appendChild(fieldRow('CSS ID', 'el-target-id', 'unique-id', function () {
            data.target.id = (document.getElementById('el-target-id').value || '').trim();
            setDirty(true);
            updatePreview();
          }));
          var tip = document.createElement('p');
          tip.className = 'el-col-hint';
          tip.textContent = 'Narrows selector to .site-main tag#id.class (optional).';
          body.appendChild(tip);
        }

        if (g.customCss) {
          var hint = document.createElement('p');
          hint.className = 'el-col-hint';
          hint.textContent = 'Custom CSS for current breakpoint + pseudo (property: value per line).';
          var cmWrap = document.createElement('div');
          cmWrap.className = 'el-cm-wrap';
          var ta = document.createElement('textarea');
          ta.id = 'el-cm-textarea';
          cmWrap.appendChild(ta);
          body.appendChild(hint);
          body.appendChild(cmWrap);
        }

        if (g.customJs) {
          var jh = document.createElement('p');
          jh.className = 'el-col-hint';
          jh.textContent = 'Optional JS (off by default). No eval/document.write.';
          var jta = document.createElement('textarea');
          jta.id = 'el-custom-js';
          jta.rows = 5;
          jta.placeholder = '// document.querySelectorAll(...)';
          jta.addEventListener('input', function () {
            data.custom_js = jta.value;
            setDirty(true);
          });
          var en = document.createElement('label');
          en.className = 'checkbox-label';
          en.style.marginTop = '0.5rem';
          en.innerHTML = '<input type="checkbox" id="el-enable-js"> Enable custom JS for this tag';
          en.querySelector('input').addEventListener('change', function (e) {
            data.enable_js = !!e.target.checked;
            setDirty(true);
          });
          body.appendChild(jh);
          body.appendChild(jta);
          body.appendChild(en);
        }

        box.appendChild(head);
        box.appendChild(body);
        groupsEl.appendChild(box);
      });

      initCodeMirror();
    }

    function initCodeMirror() {
      var ta = document.getElementById('el-cm-textarea');
      if (!ta || cm) return;
      if (typeof CodeMirror === 'undefined') {
        // Fallback: plain textarea
        ta.addEventListener('input', function () {
          activeSlice().custom_css = ta.value;
          setDirty(true);
          updatePreview();
        });
        return;
      }
      var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      cm = CodeMirror.fromTextArea(ta, {
        mode: 'css',
        lineNumbers: true,
        lineWrapping: true,
        theme: isDark ? 'material-darker' : 'default',
        viewportMargin: Infinity
      });
      cm.on('change', function () {
        if (suppressCm) return;
        activeSlice().custom_css = cm.getValue();
        setDirty(true);
        updatePreview();
      });
    }

    function setCmContent(text) {
      var ta = document.getElementById('el-cm-textarea');
      if (cm) {
        suppressCm = true;
        cm.setValue(text || '');
        suppressCm = false;
        setTimeout(function () { cm.refresh(); }, 30);
      } else if (ta) {
        ta.value = text || '';
      }
    }

    function getCmContent() {
      if (cm) return cm.getValue();
      var ta = document.getElementById('el-cm-textarea');
      return ta ? ta.value : '';
    }

    function onPropChange(e) {
      var el = e.target;
      var prop = el.getAttribute('data-prop');
      if (!prop) return;
      var val = (el.value || '').trim();
      var slice = activeSlice();
      if (val === '') delete slice.props[prop];
      else slice.props[prop] = val;
      if (el.type === 'text' && /^#[0-9a-fA-F]{6}$/.test(val)) {
        var colorInput = el.parentElement && el.parentElement.querySelector('input[type="color"]');
        if (colorInput) colorInput.value = val;
      }
      setDirty(true);
      updatePreview();
    }

    function fillForm() {
      var slice = activeSlice();
      var props = slice.props || {};
      GROUPS.forEach(function (g) {
        (g.fields || []).forEach(function (f) {
          var val = props[f.prop] || '';
          if (f.type === 'colortext') {
            var text = document.getElementById('el-prop-' + f.prop);
            if (text) text.value = val;
            var color = text && text.parentElement && text.parentElement.querySelector('input[type="color"]');
            if (color && /^#[0-9a-fA-F]{6}$/.test(val)) color.value = val;
            else if (color) color.value = '#000000';
          } else {
            var input = document.getElementById('el-prop-' + f.prop);
            if (input) input.value = val;
          }
        });
      });
      setCmContent(slice.custom_css || '');
      var tc = document.getElementById('el-target-class');
      var ti = document.getElementById('el-target-id');
      if (tc) tc.value = data.target.class || '';
      if (ti) ti.value = data.target.id || '';
      var js = document.getElementById('el-custom-js');
      var en = document.getElementById('el-enable-js');
      if (js) js.value = data.custom_js || '';
      if (en) en.checked = !!data.enable_js;
    }

    function linesFrom(slice) {
      var lines = [];
      var props = (slice && slice.props) || {};
      Object.keys(props).forEach(function (k) {
        if (props[k]) lines.push(k + ': ' + props[k] + ';');
      });
      var custom = ((slice && slice.custom_css) || '').trim();
      if (custom) {
        custom.split(/\r?\n/).forEach(function (line) {
          line = line.trim();
          if (!line || line.indexOf('/*') === 0) return;
          if (line.slice(-1) !== ';') line += ';';
          lines.push(line);
        });
      }
      return lines;
    }

    function selectorHint() {
      var s = currentTag;
      if (data.target.id) s += '#' + data.target.id;
      if (data.target.class) {
        data.target.class.split(/\s+/).filter(Boolean).forEach(function (c) {
          s += '.' + c;
        });
      }
      return s;
    }

    function compilePreviewCss() {
      var sel = '#el-preview-content ' + selectorHint();
      var css = '';
      function applyBp(bpName) {
        var bp = data[bpName];
        if (!bp) return;
        var base = linesFrom(bp);
        if (base.length) css += sel + ' {\n  ' + base.join('\n  ') + '\n}\n';
        PSEUDOS.forEach(function (p) {
          var lines = linesFrom(bp[p]);
          if (lines.length) css += sel + ':' + p + ' {\n  ' + lines.join('\n  ') + '\n}\n';
        });
      }
      applyBp('desktop');
      if (breakpoint === 'tablet' || breakpoint === 'mobile') applyBp('tablet');
      if (breakpoint === 'mobile') applyBp('mobile');
      return css;
    }

    function compileTagCssForCopy() {
      var sel = '.site-main ' + selectorHint();
      var parts = [];
      function block(bp, media) {
        var chunks = [];
        var base = linesFrom(data[bp]);
        if (base.length) chunks.push(sel + ' {\n  ' + base.join('\n  ') + '\n}');
        PSEUDOS.forEach(function (p) {
          var lines = linesFrom(data[bp][p]);
          if (lines.length) chunks.push(sel + ':' + p + ' {\n  ' + lines.join('\n  ') + '\n}');
        });
        if (!chunks.length) return;
        var body = chunks.join('\n');
        if (media) {
          parts.push('@media (max-width: ' + media + ') {\n' + body.split('\n').map(function (l) { return '  ' + l; }).join('\n') + '\n}');
        } else {
          parts.push(body);
        }
      }
      block('desktop', null);
      block('tablet', '768px');
      block('mobile', '480px');
      return parts.join('\n\n') || '/* no styles */';
    }

    function updatePreview() {
      if (!previewContent) return;
      previewContent.innerHTML = previewHtml(currentTag);
      var first = previewContent.querySelector(currentTag);
      if (first) {
        if (data.target.class) first.className = data.target.class;
        if (data.target.id) first.id = data.target.id;
      }
      styleEl.textContent = compilePreviewCss();
    }

    function flushCm() {
      activeSlice().custom_css = getCmContent();
    }

    function setBreakpoint(bp) {
      flushCm();
      breakpoint = bp;
      document.querySelectorAll('.el-bp-btn').forEach(function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-bp') === bp);
      });
      fillForm();
      updatePreview();
    }

    function setPseudo(p) {
      flushCm();
      pseudo = p;
      document.querySelectorAll('.el-pseudo-btn').forEach(function (b) {
        b.classList.toggle('is-active', b.getAttribute('data-pseudo') === p);
      });
      fillForm();
      updatePreview();
    }

    function selectTag(tag, loadRemote) {
      if (loadRemote === undefined) loadRemote = true;
      if (dirty && tag !== currentTag) {
        if (!window.confirm('Discard unsaved changes for <' + currentTag + '>?')) return;
      }
      flushCm();
      currentTag = tag;
      if (tagInput) tagInput.value = tag;
      if (activeLabel) activeLabel.textContent = tag;
      renderTagList(search ? search.value : '');
      data = emptyState();
      if (loadRemote) {
        fetch('/hq/elements/data?tag=' + encodeURIComponent(tag), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' }
        }).then(function (res) {
          if (!res.ok) return null;
          return res.json();
        }).then(function (json) {
          if (json && json.data) data = normalizeLoaded(json.data);
          fillForm();
          setDirty(false);
          updatePreview();
        }).catch(function () {
          fillForm();
          setDirty(false);
          updatePreview();
        });
      } else {
        fillForm();
        setDirty(false);
        updatePreview();
      }
    }

    function syncHidden() {
      flushCm();
      if (propsJson) propsJson.value = JSON.stringify((data.desktop && data.desktop.props) || {});
      if (customCssInput) customCssInput.value = (data.desktop && data.desktop.custom_css) || '';
      if (bpJson) bpJson.value = JSON.stringify(data);
      if (entryJson) entryJson.value = JSON.stringify(data);
    }

    function openPreviewTab() {
      var theme = (previewFrame && previewFrame.getAttribute('data-theme')) || 'light';
      var bg = theme === 'dark' ? '#0b0d12' : '#f8f9fb';
      var fg = theme === 'dark' ? '#f3f4f6' : '#111827';
      var html =
        '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
        '<title>Preview · &lt;' + currentTag + '&gt;</title>' +
        '<style>body{margin:0;padding:2rem;font-family:system-ui,sans-serif;background:' + bg + ';color:' + fg + ';}' +
        '#el-preview-content{max-width:720px;margin:0 auto;}' +
        compilePreviewCss() +
        '</style></head><body><div id="el-preview-content">' + previewHtml(currentTag) + '</div></body></html>';
      var blob = new Blob([html], { type: 'text/html' });
      var url = URL.createObjectURL(blob);
      window.open(url, '_blank', 'noopener');
      setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
    }

    // —— Search (prevent form submit on Enter)
    if (search) {
      search.addEventListener('input', function () {
        renderTagList(search.value);
      });
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          e.stopPropagation();
        }
      });
    }

    // —— Collapse
    root.querySelectorAll('[data-collapse-col]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var name = btn.getAttribute('data-collapse-col');
        var col = root.querySelector('.el-col[data-col="' + name + '"]');
        if (!col) return;
        col.classList.toggle('is-collapsed');
        btn.setAttribute('aria-expanded', col.classList.contains('is-collapsed') ? 'false' : 'true');
        if (cm) setTimeout(function () { cm.refresh(); }, 50);
        persistLayout();
      });
    });

    // —— Resize splitters (pointer events)
    var drag = null;
    function getColWidths() {
      return Array.prototype.map.call(root.querySelectorAll('.el-col'), function (c) {
        return c.getBoundingClientRect().width;
      });
    }
    function persistLayout() {
      try {
        localStorage.setItem('mova_el_layout', JSON.stringify({
          collapsed: Array.prototype.map.call(root.querySelectorAll('.el-col'), function (c) {
            return c.classList.contains('is-collapsed');
          }),
          widths: getColWidths()
        }));
      } catch (e) {}
    }
    function restoreLayout() {
      try {
        var state = JSON.parse(localStorage.getItem('mova_el_layout') || 'null');
        if (!state) return;
        var cols = root.querySelectorAll('.el-col');
        Array.prototype.forEach.call(cols, function (c, i) {
          if (state.collapsed && state.collapsed[i]) c.classList.add('is-collapsed');
          if (state.widths && state.widths[i] && !c.classList.contains('is-collapsed')) {
            c.style.flex = '0 0 ' + Math.max(160, state.widths[i]) + 'px';
          }
        });
      } catch (e) {}
    }

    root.querySelectorAll('.el-splitter').forEach(function (sp) {
      sp.addEventListener('pointerdown', function (e) {
        e.preventDefault();
        var idx = parseInt(sp.getAttribute('data-split'), 10);
        drag = { idx: idx, startX: e.clientX, widths: getColWidths(), pointerId: e.pointerId };
        try { sp.setPointerCapture(e.pointerId); } catch (err) {}
        document.body.classList.add('el-resizing');
      });
      sp.addEventListener('pointermove', function (e) {
        if (!drag) return;
        var dx = e.clientX - drag.startX;
        var cols = root.querySelectorAll('.el-col');
        var left = cols[drag.idx];
        var right = cols[drag.idx + 1];
        if (!left || !right) return;
        if (left.classList.contains('is-collapsed') || right.classList.contains('is-collapsed')) return;
        var min = 160;
        var wL = drag.widths[drag.idx] + dx;
        var wR = drag.widths[drag.idx + 1] - dx;
        if (wL < min) { wR -= (min - wL); wL = min; }
        if (wR < min) { wL -= (min - wR); wR = min; }
        left.style.flex = '0 0 ' + wL + 'px';
        right.style.flex = '0 0 ' + wR + 'px';
      });
      function endDrag(e) {
        if (!drag) return;
        drag = null;
        document.body.classList.remove('el-resizing');
        persistLayout();
        if (cm) setTimeout(function () { cm.refresh(); }, 50);
      }
      sp.addEventListener('pointerup', endDrag);
      sp.addEventListener('pointercancel', endDrag);
    });

    // —— Form / toolbar
    if (form) {
      form.addEventListener('submit', function () { syncHidden(); });
    }
    var resetBtn = document.getElementById('el-reset-tag');
    if (resetBtn) {
      resetBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (!window.confirm('Clear all styles for <' + currentTag + '>? Save to persist.')) return;
        data = emptyState();
        fillForm();
        setDirty(true);
        updatePreview();
      });
    }
    var copyBtn = document.getElementById('el-copy-css');
    if (copyBtn) {
      copyBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var text = compileTagCssForCopy();
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(function () {
            copyBtn.textContent = 'Copied';
            setTimeout(function () { copyBtn.textContent = 'Copy CSS'; }, 1200);
          }).catch(function () { window.prompt('Copy CSS:', text); });
        } else {
          window.prompt('Copy CSS:', text);
        }
      });
    }
    var themeBtn = document.getElementById('el-preview-theme');
    if (themeBtn && previewFrame) {
      themeBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var t = previewFrame.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        previewFrame.setAttribute('data-theme', t);
      });
    }
    var openBtn = document.getElementById('el-preview-open');
    if (openBtn) {
      openBtn.addEventListener('click', function (e) {
        e.preventDefault();
        flushCm();
        openPreviewTab();
      });
    }
    document.querySelectorAll('.el-bp-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        setBreakpoint(btn.getAttribute('data-bp'));
      });
    });
    document.querySelectorAll('.el-pseudo-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        setPseudo(btn.getAttribute('data-pseudo'));
      });
    });
    document.querySelectorAll('.el-pw-btn').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('.el-pw-btn').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        if (previewFrame) previewFrame.setAttribute('data-pw', btn.getAttribute('data-pw'));
      });
    });
    document.querySelectorAll('[data-goto-tag]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var tag = btn.getAttribute('data-goto-tag');
        var editorTab = document.querySelector('.hq-layer-tab[data-layer="editor"]');
        if (editorTab) editorTab.click();
        selectTag(tag);
      });
    });

    // Init
    buildGroups();
    restoreLayout();
    renderTagList('');
    selectTag(currentTag, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
