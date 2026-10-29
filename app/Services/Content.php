<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Content
{
    public static function banners(string $placement, int $limit = 5): array
    {
        return Database::get()->all(
            'SELECT * FROM banners WHERE placement = ? AND is_active = 1
               AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())
             ORDER BY sort_order, id LIMIT ' . max(1, $limit),
            [$placement]
        );
    }

    public static function page(string $slug): ?array
    {
        return Database::get()->one("SELECT * FROM content_pages WHERE slug = ? AND status = 'active'", [$slug]);
    }

    public static function faqs(): array
    {
        return Database::get()->all('SELECT * FROM faqs WHERE is_active = 1 ORDER BY sort_order, id');
    }
}
