-- Allow 4-letter currency codes such as USDT (stablecoins) in addition to ISO-4217.
ALTER TABLE products MODIFY currency VARCHAR(5) NOT NULL DEFAULT 'PKR';
ALTER TABLE orders   MODIFY currency VARCHAR(5) NOT NULL;
ALTER TABLE payments MODIFY currency VARCHAR(5) NOT NULL;
ALTER TABLE refunds  MODIFY currency VARCHAR(5) NOT NULL;
