-- URAVEX Apparel — initial schema (MySQL 8 / MariaDB 10.4+, InnoDB, utf8mb4)
--
-- Conventions
--  * Money: BIGINT minor units (paisa for PKR) in *_minor columns + CHAR(3) currency.
--  * Time: DATETIME stored in UTC (connection time_zone = '+00:00');
--    rendered in the business timezone.
--  * is_demo marks development seed data; analytics exclude it in production and
--    `php bin/console.php demo:purge` deletes it before launch.

-- ───────────────────────────── Identity ─────────────────────────────

CREATE TABLE users (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(120) NOT NULL,
  email             VARCHAR(191) NOT NULL,
  phone             VARCHAR(32)  NULL,
  password_hash     VARCHAR(255) NOT NULL,
  role              ENUM('customer','content_editor','order_staff','manager','owner') NOT NULL DEFAULT 'customer',
  email_verified_at DATETIME NULL,
  disabled          TINYINT(1) NOT NULL DEFAULT 0,
  -- Bumped on password change/reset: invalidates every existing session.
  session_version   INT UNSIGNED NOT NULL DEFAULT 1,
  last_login_at     DATETIME NULL,
  is_demo           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY ix_users_role (role),
  KEY ix_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-use, expiring tokens. Only the SHA-256 hash is stored.
CREATE TABLE user_tokens (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  type        ENUM('verify_email','reset_password') NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tokens_hash (token_hash),
  KEY ix_user_tokens_user (user_id, type),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  bucket        VARCHAR(191) NOT NULL,
  window_start  INT UNSIGNED NOT NULL,
  hits          INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start),
  KEY ix_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE addresses (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      INT UNSIGNED NOT NULL,
  label        VARCHAR(60)  NULL,
  full_name    VARCHAR(120) NOT NULL,
  phone        VARCHAR(32)  NOT NULL,
  line1        VARCHAR(191) NOT NULL,
  line2        VARCHAR(191) NULL,
  city         VARCHAR(100) NOT NULL,
  province     VARCHAR(100) NULL,
  postal_code  VARCHAR(20)  NULL,
  country      CHAR(2) NOT NULL DEFAULT 'PK',
  is_default   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_addresses_user (user_id),
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Catalog ─────────────────────────────

CREATE TABLE categories (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(120) NOT NULL,
  slug             VARCHAR(140) NOT NULL,
  description      TEXT NULL,
  image_url        VARCHAR(255) NULL,
  parent_id        INT UNSIGNED NULL,
  sort_order       INT NOT NULL DEFAULT 0,
  status           ENUM('active','archived') NOT NULL DEFAULT 'active',
  seo_title        VARCHAR(191) NULL,
  seo_description  VARCHAR(320) NULL,
  is_demo          TINYINT(1) NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY ix_categories_status (status, sort_order),
  CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE collections (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name              VARCHAR(120) NOT NULL,
  slug              VARCHAR(140) NOT NULL,
  description       TEXT NULL,
  image_url         VARCHAR(255) NULL,
  sort_order        INT NOT NULL DEFAULT 0,
  featured_on_home  TINYINT(1) NOT NULL DEFAULT 0,
  home_order        INT NOT NULL DEFAULT 0,
  status            ENUM('active','archived') NOT NULL DEFAULT 'active',
  seo_title         VARCHAR(191) NULL,
  seo_description   VARCHAR(320) NULL,
  is_demo           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_collections_slug (slug),
  KEY ix_collections_status (status, sort_order),
  KEY ix_collections_home (featured_on_home, home_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title             VARCHAR(191) NOT NULL,
  slug              VARCHAR(191) NOT NULL,
  sku               VARCHAR(64)  NOT NULL,
  description       MEDIUMTEXT NOT NULL,
  care_details      TEXT NULL,
  shipping_returns  TEXT NULL,
  category_id       INT UNSIGNED NULL,
  tags              VARCHAR(500) NOT NULL DEFAULT '',
  currency          CHAR(3) NOT NULL DEFAULT 'PKR',
  price_minor       BIGINT UNSIGNED NOT NULL,
  sale_price_minor  BIGINT UNSIGNED NULL,
  cost_minor        BIGINT UNSIGNED NULL,
  status            ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  featured          TINYINT(1) NOT NULL DEFAULT 0,
  featured_order    INT NOT NULL DEFAULT 0,
  seo_title         VARCHAR(191) NULL,
  seo_description   VARCHAR(320) NULL,
  published_at      DATETIME NULL,
  is_demo           TINYINT(1) NOT NULL DEFAULT 0,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_slug (slug),
  UNIQUE KEY uq_products_sku (sku),
  KEY ix_products_status_pub (status, published_at),
  KEY ix_products_category (category_id, status),
  KEY ix_products_featured (featured, featured_order),
  KEY ix_products_price (price_minor),
  FULLTEXT KEY ft_products_search (title, tags, description),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL,
  CONSTRAINT ck_products_sale CHECK (sale_price_minor IS NULL OR sale_price_minor < price_minor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_collections (
  product_id     INT UNSIGNED NOT NULL,
  collection_id  INT UNSIGNED NOT NULL,
  sort_order     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (product_id, collection_id),
  KEY ix_pc_collection (collection_id, sort_order),
  CONSTRAINT fk_pc_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_collection FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id   INT UNSIGNED NOT NULL,
  -- Relative path prefix under public/uploads; sizes are {path}-{w}.webp
  path         VARCHAR(255) NOT NULL,
  alt          VARCHAR(191) NOT NULL DEFAULT '',
  color        VARCHAR(60) NULL,
  width        INT UNSIGNED NOT NULL,
  height       INT UNSIGNED NOT NULL,
  -- position 0 = featured image
  position     INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_images_product (product_id, position),
  CONSTRAINT fk_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_variants (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id           INT UNSIGNED NOT NULL,
  sku                  VARCHAR(64) NOT NULL,
  size                 VARCHAR(40) NOT NULL DEFAULT '',
  color                VARCHAR(60) NOT NULL DEFAULT '',
  color_hex            CHAR(7) NULL,
  price_minor          BIGINT UNSIGNED NULL,
  sale_price_minor     BIGINT UNSIGNED NULL,
  stock                INT NOT NULL DEFAULT 0,
  low_stock_threshold  INT UNSIGNED NOT NULL DEFAULT 5,
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  position             INT NOT NULL DEFAULT 0,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_variants_sku (sku),
  UNIQUE KEY uq_variants_option (product_id, size, color),
  KEY ix_variants_stock (stock),
  CONSTRAINT fk_variants_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE,
  CONSTRAINT ck_variants_stock CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Shopping ─────────────────────────────

CREATE TABLE carts (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           INT UNSIGNED NULL,
  -- SHA-256 of the guest cookie token (raw token only lives in the cookie)
  guest_token_hash  CHAR(64) NULL,
  coupon_code       VARCHAR(40) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_carts_user (user_id),
  UNIQUE KEY uq_carts_guest (guest_token_hash),
  KEY ix_carts_updated (updated_at),
  CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cart_id     INT UNSIGNED NOT NULL,
  variant_id  INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_items (cart_id, variant_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts (id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT ck_cart_items_qty CHECK (quantity BETWEEN 1 AND 99)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlists (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlists_user (user_id),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wishlist_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  wishlist_id  INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wishlist_items (wishlist_id, product_id),
  CONSTRAINT fk_wi_wishlist FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE,
  CONSTRAINT fk_wi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Promotions ─────────────────────────────

CREATE TABLE coupons (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code                VARCHAR(40) NOT NULL,
  description         VARCHAR(255) NULL,
  type                ENUM('percentage','fixed','free_shipping') NOT NULL,
  -- percentage: whole percent 1–100; fixed: minor units; free_shipping: 0
  value               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  free_shipping       TINYINT(1) NOT NULL DEFAULT 0,
  min_subtotal_minor  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  max_discount_minor  BIGINT UNSIGNED NULL,
  starts_at           DATETIME NULL,
  ends_at             DATETIME NULL,
  usage_limit         INT UNSIGNED NULL,
  usage_count         INT UNSIGNED NOT NULL DEFAULT 0,
  per_customer_limit  INT UNSIGNED NULL,
  scope               ENUM('all','products','collections','categories') NOT NULL DEFAULT 'all',
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  is_demo             TINYINT(1) NOT NULL DEFAULT 0,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coupons_code (code),
  CONSTRAINT ck_coupons_pct CHECK (type <> 'percentage' OR (value BETWEEN 1 AND 100)),
  CONSTRAINT ck_coupons_usage CHECK (usage_limit IS NULL OR usage_count <= usage_limit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_targets (
  coupon_id    INT UNSIGNED NOT NULL,
  target_type  ENUM('product','collection','category') NOT NULL,
  target_id    INT UNSIGNED NOT NULL,
  PRIMARY KEY (coupon_id, target_type, target_id),
  CONSTRAINT fk_ct_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Shipping ─────────────────────────────

CREATE TABLE shipping_zones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120) NOT NULL,
  -- Newline-separated, matched case-insensitively against the address city.
  cities       TEXT NOT NULL,
  provinces    TEXT NOT NULL,
  country      CHAR(2) NOT NULL DEFAULT 'PK',
  -- Fallback zone matches any address in the country not matched elsewhere.
  is_fallback  TINYINT(1) NOT NULL DEFAULT 0,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipping_rates (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  zone_id          INT UNSIGNED NOT NULL,
  name             VARCHAR(120) NOT NULL,
  price_minor      BIGINT UNSIGNED NOT NULL,
  free_over_minor  BIGINT UNSIGNED NULL,
  min_days         INT UNSIGNED NOT NULL DEFAULT 2,
  max_days         INT UNSIGNED NOT NULL DEFAULT 5,
  cod_available    TINYINT(1) NOT NULL DEFAULT 1,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  sort_order       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_rates_zone (zone_id),
  CONSTRAINT fk_rates_zone FOREIGN KEY (zone_id) REFERENCES shipping_zones (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE couriers (
  id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                   VARCHAR(80) NOT NULL,
  -- e.g. https://courier.example/track?cn={tracking}
  tracking_url_template  VARCHAR(255) NULL,
  is_active              TINYINT(1) NOT NULL DEFAULT 1,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_couriers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Commerce ─────────────────────────────

CREATE TABLE orders (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number            VARCHAR(20) NULL,
  user_id                 INT UNSIGNED NULL,
  email                   VARCHAR(191) NOT NULL,
  phone                   VARCHAR(32) NOT NULL,
  customer_name           VARCHAR(120) NOT NULL,
  status                  ENUM('pending','awaiting_payment','confirmed','processing','packed','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  payment_status          ENUM('unpaid','pending_verification','paid','partially_refunded','refunded','failed','voided') NOT NULL DEFAULT 'unpaid',
  payment_method          VARCHAR(40) NOT NULL,
  currency                CHAR(3) NOT NULL,
  subtotal_minor          BIGINT UNSIGNED NOT NULL,
  discount_minor          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  shipping_minor          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tax_minor               BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_minor             BIGINT UNSIGNED NOT NULL,
  refunded_minor          BIGINT UNSIGNED NOT NULL DEFAULT 0,
  coupon_code             VARCHAR(40) NULL,
  -- Immutable JSON snapshots taken at placement
  shipping_address        JSON NOT NULL,
  billing_address         JSON NOT NULL,
  shipping_method         VARCHAR(160) NOT NULL,
  customer_note           VARCHAR(1000) NULL,
  admin_note              TEXT NULL,
  -- One per checkout form render; duplicate submissions return the same order.
  idempotency_key         CHAR(64) NOT NULL,
  reservation_expires_at  DATETIME NULL,
  stock_released          TINYINT(1) NOT NULL DEFAULT 0,
  is_demo                 TINYINT(1) NOT NULL DEFAULT 0,
  -- Paid through the sandbox provider; excluded from analytics.
  is_test                 TINYINT(1) NOT NULL DEFAULT 0,
  placed_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at                 DATETIME NULL,
  cancelled_at            DATETIME NULL,
  shipped_at              DATETIME NULL,
  delivered_at            DATETIME NULL,
  updated_at              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_idem (idempotency_key),
  KEY ix_orders_status (status, placed_at),
  KEY ix_orders_payment (payment_status),
  KEY ix_orders_user (user_id, placed_at),
  KEY ix_orders_placed (placed_at),
  KEY ix_orders_email (email),
  KEY ix_orders_reservation (status, reservation_expires_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT ck_orders_total CHECK (total_minor = subtotal_minor - discount_minor + shipping_minor + tax_minor),
  CONSTRAINT ck_orders_refund CHECK (refunded_minor <= total_minor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id           INT UNSIGNED NOT NULL,
  product_id         INT UNSIGNED NULL,
  variant_id         INT UNSIGNED NULL,
  -- Snapshots — never updated after creation (except refunded_quantity)
  product_title      VARCHAR(191) NOT NULL,
  variant_label      VARCHAR(120) NOT NULL,
  sku                VARCHAR(64) NOT NULL,
  image_path         VARCHAR(255) NULL,
  unit_price_minor   BIGINT UNSIGNED NOT NULL,
  quantity           INT UNSIGNED NOT NULL,
  discount_minor     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  line_total_minor   BIGINT UNSIGNED NOT NULL,
  refunded_quantity  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_order_items_order (order_id),
  KEY ix_order_items_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL,
  CONSTRAINT fk_oi_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE SET NULL,
  CONSTRAINT ck_oi_refund CHECK (refunded_quantity <= quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id     INT UNSIGNED NOT NULL,
  from_status  VARCHAR(30) NULL,
  to_status    VARCHAR(30) NOT NULL,
  note         VARCHAR(1000) NULL,
  -- Shown to the customer on their order page when 1.
  visible      TINYINT(1) NOT NULL DEFAULT 1,
  actor_id     INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_osh_order (order_id, created_at),
  CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  variant_id   INT UNSIGNED NOT NULL,
  delta        INT NOT NULL,
  stock_after  INT NOT NULL,
  reason       ENUM('initial','restock','adjustment','damage','sale','cancellation','return','import') NOT NULL,
  note         VARCHAR(500) NULL,
  order_id     INT UNSIGNED NULL,
  actor_id     INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_im_variant (variant_id, created_at),
  KEY ix_im_order (order_id),
  KEY ix_im_created (created_at),
  CONSTRAINT fk_im_variant FOREIGN KEY (variant_id) REFERENCES product_variants (id) ON DELETE CASCADE,
  CONSTRAINT fk_im_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE SET NULL,
  CONSTRAINT fk_im_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id             INT UNSIGNED NOT NULL,
  provider             VARCHAR(40) NOT NULL,
  mode                 ENUM('sandbox','live') NOT NULL DEFAULT 'live',
  status               ENUM('pending','awaiting_verification','paid','failed','cancelled','expired','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
  amount_minor         BIGINT UNSIGNED NOT NULL,
  currency             CHAR(3) NOT NULL,
  provider_reference   VARCHAR(120) NULL,
  customer_reference   VARCHAR(120) NULL,
  receipt_path         VARCHAR(255) NULL,
  verified_by          INT UNSIGNED NULL,
  verified_at          DATETIME NULL,
  verification_note    VARCHAR(500) NULL,
  failure_reason       VARCHAR(255) NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_ref (provider, provider_reference),
  KEY ix_payments_order (order_id),
  KEY ix_payments_status (status),
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raw provider notifications; UNIQUE (provider, provider_event_id) makes
-- webhook processing idempotent.
CREATE TABLE payment_events (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider           VARCHAR(40) NOT NULL,
  provider_event_id  VARCHAR(120) NOT NULL,
  payment_id         INT UNSIGNED NULL,
  type               VARCHAR(60) NOT NULL,
  signature_valid    TINYINT(1) NOT NULL,
  payload            JSON NOT NULL,
  outcome            VARCHAR(40) NOT NULL DEFAULT 'received',
  error              VARCHAR(500) NULL,
  received_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at       DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_events (provider, provider_event_id),
  KEY ix_pe_payment (payment_id),
  CONSTRAINT fk_pe_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id         INT UNSIGNED NOT NULL,
  payment_id       INT UNSIGNED NULL,
  amount_minor     BIGINT UNSIGNED NOT NULL,
  currency         CHAR(3) NOT NULL,
  reason           VARCHAR(500) NOT NULL,
  method           ENUM('manual','provider') NOT NULL DEFAULT 'manual',
  reference        VARCHAR(120) NULL,
  -- [{order_item_id, quantity, restock}]
  items            JSON NOT NULL,
  actor_id         INT UNSIGNED NULL,
  idempotency_key  CHAR(64) NOT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refunds_idem (idempotency_key),
  KEY ix_refunds_order (order_id),
  KEY ix_refunds_created (created_at),
  CONSTRAINT fk_refunds_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL,
  CONSTRAINT ck_refunds_amount CHECK (amount_minor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE shipments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id         INT UNSIGNED NOT NULL,
  courier          VARCHAR(80) NOT NULL,
  tracking_number  VARCHAR(80) NOT NULL,
  tracking_url     VARCHAR(255) NULL,
  note             VARCHAR(500) NULL,
  shipped_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delivered_at     DATETIME NULL,
  created_by       INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_shipments_order (order_id),
  KEY ix_shipments_tracking (tracking_number),
  CONSTRAINT fk_shipments_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE coupon_redemptions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  coupon_id       INT UNSIGNED NOT NULL,
  order_id        INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NULL,
  -- lower-cased, so guest redemptions count toward per-customer limits
  email           VARCHAR(191) NOT NULL,
  discount_minor  BIGINT UNSIGNED NOT NULL,
  voided_at       DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cr_order (order_id),
  KEY ix_cr_coupon_email (coupon_id, email),
  KEY ix_cr_coupon_user (coupon_id, user_id),
  CONSTRAINT fk_cr_coupon FOREIGN KEY (coupon_id) REFERENCES coupons (id) ON DELETE RESTRICT,
  CONSTRAINT fk_cr_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Content ─────────────────────────────

CREATE TABLE site_settings (
  `key`       VARCHAR(80) NOT NULL,
  value       JSON NOT NULL,
  updated_by  INT UNSIGNED NULL,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banners (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  placement            ENUM('hero','promo') NOT NULL,
  eyebrow              VARCHAR(80) NULL,
  title                VARCHAR(160) NOT NULL,
  subtitle             VARCHAR(320) NULL,
  image_path           VARCHAR(255) NULL,
  image_alt            VARCHAR(191) NOT NULL DEFAULT '',
  cta_label            VARCHAR(60) NULL,
  cta_href             VARCHAR(255) NULL,
  secondary_cta_label  VARCHAR(60) NULL,
  secondary_cta_href   VARCHAR(255) NULL,
  sort_order           INT NOT NULL DEFAULT 0,
  is_active            TINYINT(1) NOT NULL DEFAULT 1,
  starts_at            DATETIME NULL,
  ends_at              DATETIME NULL,
  is_demo              TINYINT(1) NOT NULL DEFAULT 0,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_banners_place (placement, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_pages (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug             VARCHAR(120) NOT NULL,
  title            VARCHAR(191) NOT NULL,
  -- Sanitized HTML (allow-list)
  body             MEDIUMTEXT NOT NULL,
  seo_title        VARCHAR(191) NULL,
  seo_description  VARCHAR(320) NULL,
  status           ENUM('draft','active') NOT NULL DEFAULT 'active',
  updated_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pages_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question    VARCHAR(255) NOT NULL,
  answer      TEXT NOT NULL,
  sort_order  INT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email              VARCHAR(191) NOT NULL,
  status             ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  source             VARCHAR(40) NULL,
  unsubscribe_token  CHAR(64) NOT NULL,
  consent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at    DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_newsletter_email (email),
  UNIQUE KEY uq_newsletter_token (unsubscribe_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(191) NOT NULL,
  phone       VARCHAR(32) NULL,
  subject     VARCHAR(191) NOT NULL,
  message     TEXT NOT NULL,
  handled     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_contact_handled (handled, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Operations ─────────────────────────────

-- Queue for order notification emails. Auth emails (which carry tokens) are
-- sent directly and never stored.
CREATE TABLE email_outbox (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  to_email    VARCHAR(191) NOT NULL,
  template    VARCHAR(60) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  html        MEDIUMTEXT NOT NULL,
  text_body   MEDIUMTEXT NOT NULL,
  status      ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts    INT UNSIGNED NOT NULL DEFAULT 0,
  last_error  VARCHAR(500) NULL,
  send_after  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_outbox_status (status, send_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE import_jobs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id    INT UNSIGNED NULL,
  filename    VARCHAR(191) NOT NULL,
  total_rows  INT UNSIGNED NOT NULL,
  created     INT UNSIGNED NOT NULL DEFAULT 0,
  updated     INT UNSIGNED NOT NULL DEFAULT 0,
  failed      INT UNSIGNED NOT NULL DEFAULT 0,
  errors      JSON NOT NULL,
  status      VARCHAR(20) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ───────────────────────────── Governance ─────────────────────────────

-- Append-only: the application never updates or deletes rows here.
CREATE TABLE admin_audit_logs (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id     INT UNSIGNED NULL,
  actor_email  VARCHAR(191) NULL,
  action       VARCHAR(80) NOT NULL,
  entity_type  VARCHAR(60) NOT NULL,
  entity_id    VARCHAR(64) NULL,
  before_data  JSON NULL,
  after_data   JSON NULL,
  ip_address   VARCHAR(45) NULL,
  user_agent   VARCHAR(255) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_created (created_at),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_actor (actor_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
