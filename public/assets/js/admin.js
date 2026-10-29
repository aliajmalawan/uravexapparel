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
