-- Manual crypto payments: the exact amount/asset/network/address quoted to the
-- customer at order time (immutable snapshot used for verification).
ALTER TABLE payments
  ADD COLUMN pay_asset   VARCHAR(10)  NULL AFTER currency,
  ADD COLUMN pay_network VARCHAR(20)  NULL AFTER pay_asset,
  ADD COLUMN pay_amount  VARCHAR(40)  NULL AFTER pay_network,
  ADD COLUMN pay_rate    VARCHAR(40)  NULL AFTER pay_amount,
  ADD COLUMN pay_address VARCHAR(120) NULL AFTER pay_rate;
