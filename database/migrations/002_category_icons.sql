-- Icon shown for each category in the storefront category rail (Admin → Categories).
ALTER TABLE categories ADD COLUMN icon VARCHAR(30) NULL AFTER image_url;
