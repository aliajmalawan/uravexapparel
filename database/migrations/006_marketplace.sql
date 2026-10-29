-- Multi-seller marketplace: seller shops, product review, per-item seller
-- earnings (snapshotted at checkout), an append-only earnings ledger and
-- withdrawal requests. Sellers only ever EARN from paid customer orders —
-- there is no seller deposit / "recharge" flow.

CREATE TABLE sellers (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id             INT UNSIGNED NOT NULL,
  shop_name           VARCHAR(120) NOT NULL,
  slug                VARCHAR(140) NOT NULL,
  description         TEXT NOT NULL,
  contact_phone       VARCHAR(32) NULL,
  status              ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  status_note         VARCHAR(500) NULL,
  -- NULL = use the store-wide commission (Admin → Sellers → Settings).
  commission_percent  DECIMAL(5,2) NULL,
  payout_address      VARCHAR(120) NULL,
  applied_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at         DATETIME NULL,
  reviewed_by         INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sellers_user (user_id),
  UNIQUE KEY uq_sellers_slug (slug),
  UNIQUE KEY uq_sellers_name (shop_name),
  KEY ix_sellers_status (status),
  CONSTRAINT fk_sellers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT ck_sellers_commission CHECK (commission_percent IS NULL OR (commission_percent >= 0 AND commission_percent <= 90))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NULL seller = URAVEX's own (house) product.
ALTER TABLE products
  ADD COLUMN seller_id INT UNSIGNED NULL AFTER id,
  -- Seller products: none (not submitted) → pending → approved | rejected. House products are approved.
  ADD COLUMN review_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'approved' AFTER status,
  ADD COLUMN review_note VARCHAR(500) NULL AFTER review_status,
  ADD KEY ix_products_seller (seller_id, status),
  ADD KEY ix_products_review (review_status),
  ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT;

-- Snapshot at checkout: who sold the line and how the money splits.
ALTER TABLE order_items
  ADD COLUMN seller_id INT UNSIGNED NULL AFTER variant_id,
  ADD COLUMN commission_minor BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER line_total_minor,
  ADD COLUMN seller_amount_minor BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER commission_minor,
  ADD KEY ix_order_items_seller (seller_id),
  ADD CONSTRAINT fk_oi_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT;

CREATE TABLE seller_withdrawals (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id      INT UNSIGNED NOT NULL,
  amount_minor   BIGINT UNSIGNED NOT NULL,
  currency       VARCHAR(5) NOT NULL,
  network        VARCHAR(20) NOT NULL,
  address        VARCHAR(120) NOT NULL,
  status         ENUM('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  txid           VARCHAR(100) NULL,
  admin_note     VARCHAR(500) NULL,
  requested_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at   DATETIME NULL,
  processed_by   INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_withdrawals_txid (txid),
  KEY ix_withdrawals_seller (seller_id, status),
  KEY ix_withdrawals_status (status, requested_at),
  CONSTRAINT fk_withdrawals_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  CONSTRAINT ck_withdrawals_amount CHECK (amount_minor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Append-only. Balance = SUM(amount_minor). available_at NULL or in the
-- future = pending (not withdrawable yet).
CREATE TABLE seller_ledger (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  seller_id       INT UNSIGNED NOT NULL,
  type            ENUM('sale','refund','withdrawal','withdrawal_reversal','adjustment') NOT NULL,
  amount_minor    BIGINT NOT NULL,
  currency        VARCHAR(5) NOT NULL,
  order_id        INT UNSIGNED NULL,
  order_item_id   INT UNSIGNED NULL,
  withdrawal_id   INT UNSIGNED NULL,
  available_at    DATETIME NULL,
  note            VARCHAR(255) NULL,
  created_by      INT UNSIGNED NULL,
  -- Set only on 'sale' rows: one sale credit per order line (idempotent
  -- against repeated payment confirmations). NULLs never collide.
  sale_item_id    INT UNSIGNED NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ledger_sale (sale_item_id),
  KEY ix_ledger_seller (seller_id, available_at),
  KEY ix_ledger_order (order_id),
  CONSTRAINT fk_ledger_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_withdrawal FOREIGN KEY (withdrawal_id) REFERENCES seller_withdrawals (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seller-provided tracking for their own items in a (possibly multi-seller) order.
CREATE TABLE seller_shipments (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id         INT UNSIGNED NOT NULL,
  seller_id        INT UNSIGNED NOT NULL,
  courier          VARCHAR(80) NOT NULL,
  tracking_number  VARCHAR(80) NOT NULL,
  tracking_url     VARCHAR(255) NULL,
  shipped_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seller_shipments (order_id, seller_id),
  CONSTRAINT fk_ss_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_seller FOREIGN KEY (seller_id) REFERENCES sellers (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
