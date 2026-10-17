/**
 * Mova blog — infinite scroll feed + embed mounts
 */
(function () {
  'use strict';

  function fetchFeed(id, offset, limit) {
    var url = '/blog/api/feed/' + encodeURIComponent(id) + '?offset=' + offset + '&limit=' + (limit || 12);
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('Feed unavailable');
      return r.json();
    });
  }

  function bindInfinite(root) {
    if (root.dataset.movaBlogBound) return;
    root.dataset.movaBlogBound = '1';
    var id = root.getAttribute('data-blog-id') || root.getAttribute('data-blog');
    if (!id) return;
    var grid = root.querySelector('.mova-blog-grid') || root;
    var offset = parseInt(root.getAttribute('data-offset') || '0', 10) || 0;
    var limit = parseInt(root.getAttribute('data-limit') || '12', 10) || 12;
    var hasMore = root.getAttribute('data-has-more') !== '0';
    var loading = false;

    var sentinel = document.createElement('div');
    sentinel.className = 'mova-blog-sentinel';
    root.appendChild(sentinel);
    var status = document.createElement('div');
    status.className = 'mova-blog-loading';
    status.hidden = true;
    status.textContent = 'Loading…';
    root.appendChild(status);

    function loadMore() {
      if (loading || !hasMore) return;
      loading = true;
      status.hidden = false;
      fetchFeed(id, offset, limit).then(function (data) {
        var html = data.html || '';
        if (html) {
          var tmp = document.createElement('div');
          tmp.innerHTML = html;
          var cards = tmp.querySelectorAll('.mova-blog-card, .mova-blog-grid > *');
          if (tmp.querySelector('.mova-blog-grid')) {
            Array.prototype.forEach.call(tmp.querySelector('.mova-blog-grid').children, function (c) {
              grid.appendChild(c);
            });
          } else {
            Array.prototype.forEach.call(tmp.children, function (c) {
              grid.appendChild(c);
            });
          }
        }
        offset = data.next_offset != null ? data.next_offset : offset + limit;
        hasMore = !!data.has_more;
        root.setAttribute('data-offset', String(offset));
        root.setAttribute('data-has-more', hasMore ? '1' : '0');
        if (!hasMore) {
          status.className = 'mova-blog-end';
          status.textContent = 'You’re all caught up';
          status.hidden = false;
        } else {
          status.hidden = true;
        }
      }).catch(function () {
        status.textContent = 'Could not load more';
        status.hidden = false;
      }).finally(function () {
        loading = false;
      });
    }

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) loadMore();
        });
      }, { rootMargin: '240px' });
      io.observe(sentinel);
    } else {
      window.addEventListener('scroll', function () {
        var rect = sentinel.getBoundingClientRect();
        if (rect.top < window.innerHeight + 200) loadMore();
      });
    }
  }

  function mountEmbed(node) {
    if (node.dataset.movaMounted) return;
    node.dataset.movaMounted = '1';
    var id = node.getAttribute('data-blog');
    if (!id) return;
    node.innerHTML = '<div class="mova-blog-loading">Loading…</div>';
    fetchFeed(id, 0, parseInt(node.getAttribute('data-limit') || '6', 10) || 6).then(function (data) {
      node.innerHTML = '';
      node.setAttribute('data-blog-id', id);
      node.setAttribute('data-offset', String(data.next_offset || 0));
      node.setAttribute('data-has-more', data.has_more ? '1' : '0');
      node.setAttribute('data-limit', String(data.limit || 6));
      var wrap = document.createElement('div');
      wrap.innerHTML = data.html || '<p class="mova-blog-end">No posts</p>';
      while (wrap.firstChild) node.appendChild(wrap.firstChild);
      if (!node.querySelector('.mova-blog-grid') && node.querySelector('.mova-blog-card')) {
        var g = document.createElement('div');
        g.className = 'mova-blog-grid';
        Array.prototype.slice.call(node.querySelectorAll('.mova-blog-card')).forEach(function (c) {
          g.appendChild(c);
        });
        node.insertBefore(g, node.firstChild);
      }
      bindInfinite(node);
    }).catch(function () {
      node.innerHTML = '<p class="mova-blog-end">Unavailable</p>';
    });
  }

  function boot() {
    document.querySelectorAll('.mova-blog-feed[data-blog-id]').forEach(bindInfinite);
    document.querySelectorAll('.mova-blog[data-blog]').forEach(mountEmbed);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  window.MovaBlog = { boot: boot };
})();
