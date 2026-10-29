<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Shipping
{
    /** Finds the zone for an address (city match → state match → fallback). States are matched by code or name. */
    public static function zoneFor(string $city, ?string $province, string $country = Region::COUNTRY): ?array
    {
        $zones = Database::get()->all('SELECT * FROM shipping_zones WHERE is_active = 1 AND country = ? ORDER BY sort_order, id', [$country]);
        $city = mb_strtolower(trim($city));
        $province = mb_strtolower(trim((string) $province));
        $fallback = null;
        foreach ($zones as $z) {
            $cities = self::list($z['cities']);
            if ($city !== '' && in_array($city, $cities, true)) {
                return $z;
            }
        }
        foreach ($zones as $z) {
            $provinces = self::list($z['provinces']);
            if ($province !== '' && (in_array($province, $provinces, true) || in_array(mb_strtolower(Region::stateName($province)), $provinces, true))) {
                return $z;
            }
            if ($z['is_fallback'] && $fallback === null) {
                $fallback = $z;
            }
        }
        return $fallback;
    }

    /** @return list<array> active rates for a zone */
    public static function rates(int $zoneId): array
    {
        return Database::get()->all('SELECT * FROM shipping_rates WHERE zone_id = ? AND is_active = 1 ORDER BY sort_order, price_minor', [$zoneId]);
    }

    /** @return list<array> rates available for an address */
    public static function ratesFor(string $city, ?string $province, string $country = Region::COUNTRY): array
    {
        $zone = self::zoneFor($city, $province, $country);
        return $zone ? self::rates((int) $zone['id']) : [];
    }

    /** Free threshold for a rate: rate override, else global setting (0 = none). */
    public static function freeOver(array $rate): ?int
    {
        if ($rate['free_over_minor'] !== null) {
            return (int) $rate['free_over_minor'] ?: null;
        }
        $global = (int) Settings::get('shipping.free_threshold_minor', 0);
        return $global > 0 ? $global : null;
    }

    /** Lowest standard rate across all zones, for "estimated shipping" in the cart. */
    public static function estimate(): ?array
    {
        return Database::get()->one(
            'SELECT r.* FROM shipping_rates r JOIN shipping_zones z ON z.id = r.zone_id WHERE r.is_active = 1 AND z.is_active = 1 ORDER BY r.price_minor LIMIT 1'
        );
    }

    /** @return list<string> */
    public static function list(?string $text): array
    {
        return array_values(array_filter(array_map(static fn ($s) => mb_strtolower(trim($s)), preg_split('/[\r\n,]+/', (string) $text) ?: [])));
    }

    public static function trackingUrl(string $courier, string $tracking): ?string
    {
        $tpl = Database::get()->value('SELECT tracking_url_template FROM couriers WHERE name = ?', [$courier]);
        return $tpl ? str_replace('{tracking}', rawurlencode($tracking), (string) $tpl) : null;
    }
}
