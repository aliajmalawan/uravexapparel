<?php

declare(strict_types=1);

use App\Core\Database;

/**
 * Production-safe starter data. Idempotent: only inserts what is missing and
 * never overwrites edits made in the admin.
 *
 *  - Shipping zones/rates for the United States (edit prices in Admin → Settings → Shipping)
 *  - Couriers (manual tracking; add tracking URL templates in admin)
 *  - Policy/content pages and FAQs (TEMPLATES — review with your legal advisor before launch)
 */
final class BaseSeeder
{
    public function run(Database $db): void
    {
        if (!(int) $db->value('SELECT COUNT(*) FROM shipping_zones')) {
            // Prices in the store currency's minor unit (cents for USD/USDT).
            $remote = $db->insert(
                'INSERT INTO shipping_zones (name, cities, provinces, country, sort_order) VALUES (?, ?, ?, \'US\', 1)',
                ['Alaska, Hawaii & territories', '', "ak\nhi\npr\naa\nae\nap"]
            );
            $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available, sort_order) VALUES (?, ?, ?, ?, ?, 0, 1)', [$remote, 'Standard shipping', 1499, 7, 10]);
            $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available, sort_order) VALUES (?, ?, ?, ?, ?, 0, 2)', [$remote, 'Express shipping', 2999, 3, 5]);
            $rest = $db->insert(
                'INSERT INTO shipping_zones (name, cities, provinces, country, is_fallback, sort_order) VALUES (?, ?, ?, \'US\', 1, 2)',
                ['Contiguous United States', '', '']
            );
            $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available, sort_order) VALUES (?, ?, ?, ?, ?, 0, 1)', [$rest, 'Standard shipping', 699, 5, 7]);
            $db->insert('INSERT INTO shipping_rates (zone_id, name, price_minor, min_days, max_days, cod_available, sort_order) VALUES (?, ?, ?, ?, ?, 0, 2)', [$rest, 'Express shipping', 1499, 2, 3]);
        }

        foreach ([
            'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
            'UPS' => 'https://www.ups.com/track?tracknum={tracking}',
            'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr={tracking}',
            'DHL Express' => 'https://www.dhl.com/us-en/home/tracking/tracking-express.html?tracking-id={tracking}',
        ] as $c => $tpl) {
            $db->exec('INSERT IGNORE INTO couriers (name, tracking_url_template) VALUES (?, ?)', [$c, $tpl]);
        }

        $pages = [
            'about' => ['About URAVEX', '<p>URAVEX Apparel is a clothing manufacturer and exporter trusted by international labels. We bring the same standards of fabric selection, pattern-making and finishing directly to customers in Pakistan.</p><h2>Our craft</h2><p>Every piece is cut, sewn and quality-checked in our own facilities. We favour durable natural fibres, precise tailoring and finishes that hold up to everyday wear.</p><h2>Manufacturing &amp; exports</h2><p>Looking for a manufacturing partner? <a href="/contact">Contact our team</a> to discuss private label and bulk production.</p>'],
            'shipping-policy' => ['Shipping Policy', '<p><strong>Template — review before launch.</strong></p><h2>Delivery areas</h2><p>We deliver across Pakistan through trusted courier partners.</p><h2>Delivery times</h2><p>Major cities: 2–3 working days. Other areas: 3–5 working days. Orders placed after 3pm or on Sundays/public holidays are dispatched the next working day.</p><h2>Charges</h2><p>Delivery charges are shown at checkout for your city. Free delivery applies above the threshold shown in your bag.</p><h2>Tracking</h2><p>You will receive a tracking number by email once your order ships. You can also <a href="/track-order">track your order</a> online.</p>'],
            'returns-policy' => ['Returns & Exchanges', '<p><strong>Template — review before launch.</strong></p><h2>Exchanges</h2><p>Unworn, unwashed items with original tags can be exchanged within 7 days of delivery for a different size or colour, subject to availability.</p><h2>Refunds</h2><p>Faulty or incorrect items are refunded or replaced. Refunds for prepaid orders are returned to the original payment method; cash-on-delivery orders are refunded by bank transfer.</p><h2>How to request</h2><p>Contact us with your order number and photos (for faulty items). We will arrange collection or share the return address.</p><h2>Non-returnable items</h2><p>Items marked final sale, underwear and accessories that have been used.</p>'],
            'privacy-policy' => ['Privacy Policy', '<p><strong>Template — review with your legal advisor before launch.</strong></p><h2>What we collect</h2><p>Your name, email, phone number and delivery address to process orders; account details if you register; and basic technical data (IP address, browser) for security.</p><h2>How we use it</h2><p>To fulfil and deliver orders, provide customer support, prevent fraud and — only with your consent — send marketing emails.</p><h2>Sharing</h2><p>We share delivery details with courier partners and payment details with payment providers only as needed to complete your order. We never sell your data.</p><h2>Your rights</h2><p>You can view and update your details in your account, unsubscribe from marketing at any time, and ask us to delete your account.</p><h2>Security</h2><p>Passwords are stored as one-way hashes. We never store card numbers.</p>'],
            'terms' => ['Terms & Conditions', '<p><strong>Template — review with your legal advisor before launch.</strong></p><h2>Orders</h2><p>An order is accepted once confirmed by email. Prices are in Pakistani Rupees (PKR) and include applicable taxes unless stated.</p><h2>Payment</h2><p>We accept the payment methods shown at checkout. Bank-transfer orders are confirmed after payment is verified.</p><h2>Availability</h2><p>Items are subject to availability. If an item becomes unavailable after you order, we will contact you and refund any payment for that item.</p><h2>Liability</h2><p>Our liability is limited to the value of the order to the extent permitted by law.</p>'],
        ];
        foreach ($pages as $slug => [$title, $body]) {
            $db->exec('INSERT IGNORE INTO content_pages (slug, title, body) VALUES (?, ?, ?)', [$slug, $title, $body]);
        }

        if (!(int) $db->value('SELECT COUNT(*) FROM faqs')) {
            $faqs = [
                ['How long does delivery take?', "Major cities usually receive orders in 2–3 working days; other areas in 3–5 working days."],
                ['How can I pay?', 'We accept USDT (TRC20 or ERC20), Bitcoin and Ether. After placing your order you see the exact amount and our wallet address. Cash on delivery is not available.'],
                ['How do bank transfers work?', "Choose Bank Transfer at checkout, transfer the total to the account shown, then submit your transaction ID. We confirm your order once the payment is verified."],
                ['Can I exchange an item?', 'Unworn items with tags can be exchanged within 7 days of delivery. See our Returns & Exchanges policy for details.'],
                ['How do I find my size?', 'Each product page lists fit notes. If you are between sizes, message us and our team will help.'],
                ['How can I track my order?', "Use the tracking link in your shipping email, visit My Account → Orders, or use Track Order with your order number and email."],
            ];
            foreach ($faqs as $i => [$q, $a]) {
                $db->insert('INSERT INTO faqs (question, answer, sort_order) VALUES (?, ?, ?)', [$q, $a, $i]);
            }
        }
    }
}
