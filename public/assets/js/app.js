/* URAVEX storefront — progressive enhancement. Every feature works without JS. */
(function () {
  'use strict';

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const csrf = () => $('meta[name="csrf-token"]')?.content || '';

  /* ---------- Toast ---------- */
  function toast(message, link) {
    $$('.toast').forEach((t) => t.remove());
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    if (link) {
      const a = document.createElement('a');
      a.href = link.href;
      a.textContent = link.label;
      el.appendChild(a);
    }
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 4500);
  }

  /* ---------- Drawers (menu, filters) with focus trap ---------- */
  let lastFocus = null;
  function openDrawer(id) {
    const d = document.getElementById(id);
    if (!d) return;
    lastFocus = document.activeElement;
    d.classList.add('is-open');
    document.body.classList.add('no-scroll');
    $$(`[aria-controls="${id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'true'));
    const focusable = $$('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])', d).filter((x) => !x.disabled && x.offsetParent !== null);
    (focusable[0] || d).focus();
  }
  function closeDrawer(d) {
    d.classList.remove('is-open');
    document.body.classList.remove('no-scroll');
    $$(`[aria-controls="${d.id}"]`).forEach((b) => b.setAttribute('aria-expanded', 'false'));
    lastFocus?.focus();
  }
  document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-drawer-open]');
    if (opener) { e.preventDefault(); openDrawer(opener.dataset.drawerOpen); return; }
    const closer = e.target.closest('[data-drawer-close]');
    if (closer) { closeDrawer(closer.closest('.drawer')); }
  });
  document.addEventListener('keydown', (e) => {
    const open = $('.drawer.is-open');
    if (!open) return;
    if (e.key === 'Escape') { closeDrawer(open); return; }
    if (e.key === 'Tab') {
      const f = $$('a, button, input, select, textarea', open).filter((x) => !x.disabled && x.offsetParent !== null);
      if (!f.length) return;
      const first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });

  /* ---------- Search toggle ---------- */
  $$('[data-toggle]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const panel = document.getElementById(btn.dataset.toggle);
      if (!panel) return;
      const show = panel.hidden;
      panel.hidden = !show;
      btn.setAttribute('aria-expanded', String(show));
      if (show) $('input', panel)?.focus();
    });
  });

  /* ---------- Quantity steppers ---------- */
  document.addEventListener('click', (e) => {
    const dec = e.target.closest('[data-qty-dec]');
    const inc = e.target.closest('[data-qty-inc]');
    if (!dec && !inc) return;
    const input = $('input', (dec || inc).closest('[data-qty]'));
    const min = Number(input.min || 0), max = Number(input.max || 99);
    const next = Math.min(max, Math.max(min, Number(input.value || 0) + (inc ? 1 : -1)));
    input.value = String(next);
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* ---------- Auto-submit (sort select, desktop filters) ---------- */
  $$('form[data-auto-submit]').forEach((form) => {
    form.addEventListener('change', () => {
      if (form.requestSubmit) form.requestSubmit(); else form.submit();
    });
  });

  /* ---------- Confirm dialogs ---------- */
  document.addEventListener('submit', (e) => {
    const msg = e.target.dataset?.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  }, true);

  /* ---------- Password visibility ---------- */
  $$('[data-toggle-password]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.getAttribute('aria-controls'));
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.textContent = show ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', String(show));
    });
  });

  /* ---------- Cart count ---------- */
  function setCount(n) {
    $$('[data-cart-count]').forEach((el) => {
      el.textContent = String(n);
      el.hidden = !n;
      el.closest('a')?.setAttribute('aria-label', `Shopping bag, ${n} items`);
    });
  }

  async function postForm(form) {
    const res = await fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { Accept: 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf() },
      credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch (_) { /* non-JSON */ }
    return { res, data };
  }

  /* ---------- Product page: variant selection ---------- */
  const buyForm = $('form[data-add-to-cart]');
  if (buyForm) {
    const variants = JSON.parse(buyForm.dataset.variants || '[]');
    const select = $('select[name="variant_id"]', buyForm);
    const colorGroup = $('[data-option="color"]', buyForm);
    const sizeGroup = $('[data-option="size"]', buyForm);
    const note = $('[data-stock-note]', buyForm);
    const addBtn = $('[data-add-btn]', buyForm);
    const qty = $('input[name="quantity"]', buyForm);

    if (select && (colorGroup || sizeGroup)) {
      $('[data-variant-fallback]', buyForm).hidden = true;
      if (colorGroup) colorGroup.hidden = false;
      if (sizeGroup) sizeGroup.hidden = false;
    }

    const current = () => {
      const color = $('input[name="_color"]:checked', buyForm)?.value ?? '';
      const size = $('input[name="_size"]:checked', buyForm)?.value ?? '';
      return { color, size };
    };

    function refresh() {
      if (!select) return;
      const { color, size } = current();
      // Disable sizes that are unavailable for the chosen colour.
      $$('input[name="_size"]', buyForm).forEach((input) => {
        const v = variants.find((x) => String(x.size) === input.value && (!colorGroup || String(x.color) === color));
        input.disabled = !v || v.stock <= 0;
        input.closest('label').title = input.disabled ? 'Out of stock' : '';
      });
      let match = variants.find((x) => (!sizeGroup || String(x.size) === size) && (!colorGroup || String(x.color) === color));
      if (!match) {
        // That size doesn't exist in this colour: switch to an in-stock (or any) variant of the chosen colour.
        const pool = colorGroup ? variants.filter((x) => String(x.color) === color) : variants;
        match = pool.find((x) => x.stock > 0) || pool[0] || variants.find((x) => x.stock > 0) || variants[0];
        if (match) {
          const cr = colorGroup && $(`input[name="_color"][value="${CSS.escape(String(match.color))}"]`, buyForm);
          const sr = sizeGroup && $(`input[name="_size"][value="${CSS.escape(String(match.size))}"]`, buyForm);
          if (cr) cr.checked = true;
          if (sr) sr.checked = true;
        }
      }
      if (sizeGroup && match && match.stock <= 0) {
        const alt = variants.find((x) => String(x.color) === color && x.stock > 0);
        if (alt) { const r = $(`input[name="_size"][value="${CSS.escape(alt.size)}"]`, buyForm); if (r) { r.checked = true; match = alt; } }
      }
      $('[data-selected-color]', buyForm) && ($('[data-selected-color]', buyForm).textContent = color);
      $('[data-selected-size]', buyForm) && ($('[data-selected-size]', buyForm).textContent = current().size);

      if (!match) {
        note.textContent = 'This combination is unavailable';
        note.className = 'stock-note out';
        addBtn.disabled = true;
        addBtn.textContent = 'Unavailable';
        return;
      }
      select.value = String(match.id);
      const price = $('[data-price]');
      price.innerHTML = '';
      const now = document.createElement('span');
      now.textContent = match.price;
      if (match.sale) {
        now.className = 'now-sale';
        const was = document.createElement('span');
        was.className = 'was';
        was.textContent = match.regular;
        price.append(now, was);
      } else price.append(now);
      $('[data-price-mini]') && ($('[data-price-mini]').textContent = match.price);
      $('[data-sku]').textContent = match.sku;
      const soldOut = match.stock <= 0;
      note.textContent = soldOut ? 'Out of stock' : (match.stock <= match.low ? `Only ${match.stock} left` : 'In stock — ready to ship');
      note.className = 'stock-note ' + (soldOut ? 'out' : (match.stock <= match.low ? 'low' : 'in'));
      addBtn.disabled = soldOut;
      addBtn.textContent = soldOut ? 'Sold out' : 'Add to bag';
      qty.max = String(Math.max(1, Math.min(99, match.stock)));
      if (Number(qty.value) > Number(qty.max)) qty.value = qty.max;
      // Show the first image for the selected colour.
      const img = $$('.gallery-main img[data-color]').find((i) => i.dataset.color === color);
      if (img) showSlide(Number(img.dataset.slide));
    }

    buyForm.addEventListener('change', (e) => {
      if (e.target.name === '_color' || e.target.name === '_size') refresh();
    });
    refresh();

    buyForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      addBtn.classList.add('is-loading');
      addBtn.disabled = true;
      try {
        const { res, data } = await postForm(buyForm);
        if (res.ok && data.ok) {
          setCount(data.count);
          toast(data.message, { href: buyForm.action.replace(/\/add$/, ''), label: 'View bag' });
        } else {
          toast(data.message || (data.errors && Object.values(data.errors)[0]) || 'Could not add to bag. Please try again.');
        }
      } catch (_) {
        buyForm.submit();
      } finally {
        addBtn.classList.remove('is-loading');
        refresh();
      }
    });
  }

  /* ---------- Gallery ---------- */
  // Declared with var/function so the variant selector (above) can call it early.
  var slide = slide || 0; // keep any index set by the variant selector
  function showSlide(n) {
    const g = $('[data-gallery]');
    if (!g) return;
    const slides = $$('.gallery-main img[data-slide]', g);
    if (!slides.length) return;
    slide = (n + slides.length) % slides.length;
    slides.forEach((img, i) => { img.hidden = i !== slide; });
    $$('[data-thumb]', g).forEach((b, i) => b.setAttribute('aria-current', String(i === slide)));
  }
  const gallery = $('[data-gallery]');
  if (gallery) {
    gallery.addEventListener('click', (e) => {
      if (e.target.closest('[data-gallery-prev]')) showSlide(slide - 1);
      if (e.target.closest('[data-gallery-next]')) showSlide(slide + 1);
      const t = e.target.closest('[data-thumb]');
      if (t) showSlide(Number(t.dataset.thumb));
    });
    let x0 = null;
    const main = $('.gallery-main', gallery);
    main.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    main.addEventListener('touchend', (e) => {
      if (x0 === null) return;
      const dx = e.changedTouches[0].clientX - x0;
      if (Math.abs(dx) > 40) showSlide(slide + (dx < 0 ? 1 : -1));
      x0 = null;
    });
  }

  /* ---------- Sticky buy bar (mobile) ---------- */
  const sticky = $('[data-sticky-buy]');
  if (sticky && 'IntersectionObserver' in window && buyForm) {
    new IntersectionObserver(([entry]) => {
      const show = !entry.isIntersecting && entry.boundingClientRect.top < 0;
      sticky.classList.toggle('is-visible', show);
      sticky.setAttribute('aria-hidden', String(!show));
      $('a', sticky).tabIndex = show ? 0 : -1;
    }).observe(buyForm);
  }

  /* ---------- Wishlist toggles ---------- */
  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-wishlist-form]');
    if (!form) return;
    e.preventDefault();
    const btn = $('button', form);
    try {
      const { res, data } = await postForm(form);
      if (res.status === 401 && data.login) { window.location.href = data.login; return; }
      if (data.ok) {
        btn.setAttribute('aria-pressed', String(data.saved));
        const label = $('[data-wish-label]', form);
        if (label) label.textContent = data.saved ? 'Saved to wishlist' : 'Add to wishlist';
        toast(data.message);
      } else toast(data.message || 'Something went wrong.');
    } catch (_) { form.submit(); }
  });

  /* ---------- Checkout ---------- */
  const checkout = $('form[data-checkout-form]');
  if (checkout) {
    const refreshBtn = $('[data-refresh-btn]', checkout);
    let timer = null;
    const refreshNow = () => {
      // Keep entered details: re-render via POST intent=update (no PII in URL).
      const intent = document.createElement('input');
      intent.type = 'hidden'; intent.name = 'intent'; intent.value = 'update';
      checkout.appendChild(intent);
      checkout.noValidate = true;
      checkout.submit();
    };
    $$('[data-refresh-on-change]', checkout).forEach((el) => {
      el.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(() => { timer = null; refreshNow(); }, 250); });
    });
    if (refreshBtn) refreshBtn.closest('p').hidden = true;
    let submitting = false;
    checkout.addEventListener('submit', (e) => {
      const submitter = e.submitter;
      if (submitter && submitter.value !== 'place') return;
      // State or delivery just changed and the totals haven't refreshed yet: refresh first, so the
      // customer confirms the new total instead of hitting "Your order total was updated".
      if (timer) { e.preventDefault(); clearTimeout(timer); timer = null; refreshNow(); return; }
      if (submitting) { e.preventDefault(); return; }
      submitting = true;
      const btn = $('[data-place-order]', checkout);
      btn.classList.add('is-loading');
      btn.setAttribute('aria-disabled', 'true');
    });
  }

  /* ---------- Back to top: always scroll (the header is sticky, so a plain #top jump can be a no-op) ---------- */
  $$('a[href="#top"]').forEach((link) => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
      const target = document.getElementById('top');
      if (target) target.focus({ preventScroll: true }); // keyboard users continue from the top
    });
  });

  /* ---------- Copy buttons (wallet address, crypto amount) ---------- */
  $$('[data-copy]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const text = btn.getAttribute('data-copy');
      const done = () => {
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = 'Copy'; }, 1800);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, () => {});
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.className = 'sr-only';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (e) { /* select manually */ }
        ta.remove();
      }
    });
  });

  /* ---------- Cart quantity forms: submit on change ---------- */
  $$('form[data-qty-form]').forEach((form) => {
    const btn = $('[data-update-btn]', form);
    if (btn) btn.classList.add('sr-only');
    $('input[name="quantity"]', form).addEventListener('change', () => {
      clearTimeout(form._t);
      form._t = setTimeout(() => form.submit(), 400);
    });
  });
})();
