-- The store ships within the United States: new addresses and shipping
-- zones default to US, and existing zones move to the US so they keep
-- matching (edit names/rates in Admin → Settings → Shipping).
ALTER TABLE addresses ALTER COLUMN country SET DEFAULT 'US';
ALTER TABLE shipping_zones ALTER COLUMN country SET DEFAULT 'US';
UPDATE shipping_zones SET country = 'US';
