/* URAVEX admin — small enhancements; all actions are plain forms. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  // Sidebar drawer (mobile/tablet)
  const nav = $('#admin-nav');
  const openBtn = $('[data-admin-nav-open]');
  const setNav = (open) => {
    nav.classList.toggle('is-open', open);
    openBtn?.setAttribute('aria-expanded', String(open));
    if (open) $('a', nav)?.focus(); else openBtn?.focus();
  };
  openBtn?.addEventListener('click', () => setNav(true));
  $$('[data-admin-nav-close]').forEach((b) => b.addEventListener('click', () => setNav(false)));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && nav?.classList.contains('is-open')) setNav(false); });

  // Confirmations
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset?.confirm;
    if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
    // Prevent double submission of any admin form.
    if (e.target.dataset.submitted) { e.preventDefault(); return; }
    if (!e.defaultPrevented && e.target.method === 'post') {
      e.target.dataset.submitted = '1';
      setTimeout(() => { delete e.target.dataset.submitted; }, 4000);
    }
  }, true);

  // Auto-submit filters
  $$('form[data-auto-submit]').forEach((f) => f.addEventListener('change', () => f.requestSubmit ? f.requestSubmit() : f.submit()));

  // Chart tooltips (hover + keyboard focus)
  $$('[data-chart]').forEach((wrap) => {
    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    wrap.appendChild(tip);
    const show = (bar) => {
      const r = bar.getBoundingClientRect();
      const w = wrap.getBoundingClientRect();
      tip.textContent = bar.dataset.tip;
      tip.style.left = `${r.left - w.left + r.width / 2}px`;
      tip.style.top = `${r.top - w.top}px`;
      tip.hidden = false;
    };
    $$('.bar', wrap).forEach((bar) => {
      bar.addEventListener('mouseenter', () => show(bar));
      bar.addEventListener('focus', () => show(bar));
      bar.addEventListener('mouseleave', () => { tip.hidden = true; });
      bar.addEventListener('blur', () => { tip.hidden = true; });
    });
  });

  // Variant rows: add row
  const addRow = $('[data-add-variant]');
  if (addRow) {
    addRow.addEventListener('click', () => {
      const tbody = $('[data-variant-rows]');
      const tpl = $('#variant-row-template');
      const idx = Date.now();
      tbody.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', `n${idx}`));
      $('tr:last-child input', tbody)?.focus();
    });
  }

  // Slug from title (new items only)
  const title = $('[data-slug-source]');
  const slug = $('[data-slug-target]');
  if (title && slug && !slug.value) {
    title.addEventListener('input', () => {
      if (slug.dataset.touched) return;
      slug.value = title.value.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });
    slug.addEventListener('input', () => { slug.dataset.touched = '1'; });
  }

  // Refund: tick restock + quantity helper
  $$('[data-select-all]').forEach((cb) => cb.addEventListener('change', () => {
    $$(cb.dataset.selectAll).forEach((x) => { x.checked = cb.checked; });
  }));
})();

// Currency settings: keep the "1 XXX =" label in sync with the chosen currency.
(function () {
  const sel = document.querySelector('[data-currency-select]');
  const code = document.querySelector('[data-currency-code]');
  if (!sel || !code) return;
  const sync = () => { code.textContent = sel.value; };
  sel.addEventListener('change', sync);
  sync();
  document.getElementById('currency-preview')?.scrollIntoView({ block: 'start' });
})();

// Currency settings: suggest the live rate for the store-currency change.
(function () {
  const sel = document.querySelector('[data-currency-select]');
  const rate = document.querySelector('[data-live-rates]');
  if (!sel || !rate) return;
  let live = {};
  try { live = JSON.parse(rate.dataset.liveRates || '{}'); } catch (_) { live = {}; }
  const hint = document.createElement('p');
  hint.className = 'hint';
  rate.insertAdjacentElement('afterend', hint);
  const sync = () => {
    const v = live[sel.value];
    hint.textContent = v ? `Live rate: 1 ${sel.value} = ${v} (filled in for you — check it before converting).` : 'No live rate available for this currency — enter it manually.';
    if (v && !rate.dataset.touched) rate.value = v;
  };
  rate.addEventListener('input', () => { rate.dataset.touched = '1'; });
  sel.addEventListener('change', sync);
  sync();
})();
(function () {
  /* Copy buttons */
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy');
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(function () { btn.textContent = 'Copied'; }); return; }
      var ta = document.createElement('textarea'); ta.value = text; ta.className = 'sr-only'; document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); btn.textContent = 'Copied'; } catch (e) { /* select manually */ }
      ta.remove();
    });
  });
})();
(function () {
  'use strict';
  /* Categories page: live search over the tree */
  var search = document.querySelector('[data-cat-search]');
  var tree = document.querySelector('[data-cat-tree]');
  if (search && tree) {
    var empty = document.querySelector('[data-cat-empty]');
    var nodes = Array.prototype.slice.call(tree.querySelectorAll('.cat-node'));
    search.addEventListener('input', function () {
      var q = search.value.trim().toLowerCase();
      var shown = 0;
      nodes.forEach(function (node) {
        var match = !q || node.getAttribute('data-search').indexOf(q) !== -1;
        node.hidden = !match;
        if (match) shown++;
        var group = node.querySelector('details');
        var parentHit = q && node.querySelector('.cat-row.is-parent').textContent.toLowerCase().indexOf(q) !== -1;
        Array.prototype.forEach.call(node.querySelectorAll('.cat-row.is-child'), function (kid) {
          kid.hidden = !!q && !parentHit && kid.getAttribute('data-search').indexOf(q) === -1;
        });
        if (group && q && match) group.open = true;
      });
      if (empty) empty.hidden = shown > 0;
    });
  }

  /* Character counters: <span class="char-count" data-count-for="field" data-ideal="60"> */
  Array.prototype.forEach.call(document.querySelectorAll('[data-count-for]'), function (out) {
    var field = document.getElementById(out.getAttribute('data-count-for'));
    if (!field) return;
    var ideal = Number(out.getAttribute('data-ideal')) || 0;
    var max = Number(field.getAttribute('maxlength')) || 0;
    var update = function () {
      var n = field.value.length;
      out.textContent = ideal ? n + ' / ' + ideal + ' recommended' : n + (max ? ' / ' + max : '');
      out.classList.toggle('is-over', ideal > 0 && n > ideal);
    };
    field.addEventListener('input', update);
    update();
  });

  /* Google preview for the category being edited */
  var preview = document.querySelector('[data-seo-preview]');
  if (preview) {
    var get = function (sel) { var el = document.querySelector(sel); return el ? el.value.trim() : ''; };
    var desc = document.getElementById('description');
    var render = function () {
      var name = get('[data-seo-name]');
      var slug = get('[data-seo-slug]') || name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
      preview.querySelector('.seo-url').textContent = preview.getAttribute('data-site') + '/category/' + (slug || 'your-category');
      preview.querySelector('.seo-title').textContent = get('[data-seo-title]') || (name ? name + ' | URAVEX Store' : 'Category name | URAVEX Store');
      preview.querySelector('.seo-desc').textContent = get('[data-seo-desc]') || (desc ? desc.value.trim() : '') || 'Add a short description so shoppers and search engines know what this category offers.';
    };
    ['[data-seo-name]', '[data-seo-slug]', '[data-seo-title]', '[data-seo-desc]', '#description'].forEach(function (sel) {
      var el = document.querySelector(sel);
      if (el) el.addEventListener('input', render);
    });
    render();
  }
})();
