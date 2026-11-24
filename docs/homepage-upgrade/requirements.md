# Requirements — Homepage upgrade

Date: 2026-11-24 · Upgrade base: commit `01d7533` ("Create uploads.zip") · Repo protocol: OmniForge v1.4.0

## Goal

Upgrade the storefront home page (`/`) to a professional, fully responsive standard (phone, tablet, laptop, desktop) while keeping the URAVEX black/gold/silver identity, the admin-editable content, and every existing behaviour.

## Audience

US shoppers browsing a fashion and lifestyle catalogue on phones first, then laptops; returning visitors who come to see new arrivals and offers.

## Scope

- In scope: `app/Views/store/home.php`, the home-page section of `public/assets/css/app.css`, a home-only motion module in `public/assets/js/app.js`.
- Out of scope (deliberately unchanged): header/footer markup, shop/listing pages, product pages, checkout, admin, data and imported listings.

## Key features (must keep working)

1. Hero with admin-editable eyebrow, title, subtitle and two calls to action.
2. Promises bar (admin assurances).
3. Shop by category (all main categories with products, real counts).
4. New arrivals and best sellers product grids (wishlist toggle on each card).
5. About/story block with vision and history (admin content) and real store figures.
6. Featured collections band (shown when collections have products).
7. Featured offer (admin promo + real sale item).
8. Delivery + sell-on-URAVEX cards; membership block (sign up / sign in, or newsletter form when signed in).

## Keep-working list (regression flows)

- Every link on the page resolves (baseline: 29 links, 0 broken).
- Hero CTAs, category tiles, product cards, "View all" links navigate correctly.
- Wishlist heart on a card toggles (signed-in) / sends to sign-in (guest), as before.
- Membership block shows sign-up/sign-in for guests and the newsletter form for signed-in users.
- Page readable and complete with JavaScript disabled and under reduced motion.

## Acceptance criteria

- No horizontal overflow at 320 / 390 / 768 / 1024 / 1440 px; targets ≥ 24 px.
- design-intelligence gate AFTER ≥ 64/80, hard categories at 10; leak score 0.
- Full test suite green (baseline 540 passed).
- No new console errors; CSP respected (no inline scripts or style attributes in markup).
