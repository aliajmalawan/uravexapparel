<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Role → permission matrix (least privilege). Checked server-side by route
 * middleware and again inside sensitive service methods.
 */
final class Permissions
{
    public const ROLES = [
        'customer' => 'Customer',
        'content_editor' => 'Content Editor',
        'order_staff' => 'Order Staff',
        'manager' => 'Manager',
        'owner' => 'Owner',
    ];

    public const STAFF_ROLES = ['content_editor', 'order_staff', 'manager', 'owner'];

    private const MATRIX = [
        'content_editor' => [
            'dashboard.view',
            'content.manage',
            'products.view',
            'products.feature',
        ],
        'order_staff' => [
            'dashboard.view',
            'orders.view', 'orders.fulfil', 'orders.payments',
            'customers.view',
            'products.view',
            'inventory.view',
        ],
        'manager' => [
            'dashboard.view', 'analytics.view',
            'orders.view', 'orders.fulfil', 'orders.payments', 'orders.refund', 'orders.cancel',
            'customers.view', 'customers.manage', 'customers.export',
            'products.view', 'products.manage', 'products.feature', 'products.import',
            'catalog.manage',
            'inventory.view', 'inventory.adjust',
            'coupons.manage',
            'content.manage',
            'reports.view', 'reports.export',
            'audit.view',
            'messages.view',
            'sellers.manage',
        ],
        // owner: everything, including the permissions below.
    ];

    /** Permissions reserved to owners. */
    public const OWNER_ONLY = ['settings.manage', 'payments.configure', 'staff.manage', 'data.purge', 'payouts.manage'];

    public static function allows(string $role, string $permission): bool
    {
        if ($role === 'owner') {
            return true;
        }
        return in_array($permission, self::MATRIX[$role] ?? [], true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        $all = self::OWNER_ONLY;
        foreach (self::MATRIX as $perms) {
            $all = array_merge($all, $perms);
        }
        return array_values(array_unique($all));
    }

    public static function isStaff(string $role): bool
    {
        return in_array($role, self::STAFF_ROLES, true);
    }
}
