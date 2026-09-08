<?php

namespace App\Enums;

/**
 * The catalog of admin-side permissions.
 *
 * This enum is the source of truth rather than a `permissions` table: a permission the code
 * checks exists by construction, and there is no seeder that can drift from what the middleware
 * actually asks for. Grants live in `user_permissions`, one row per account per permission.
 */
enum Permission: string
{
    case ConsoleAccess = 'console.access';

    case SummaryView = 'summary.view';
    case OperationsView = 'operations.view';

    case CategoriesView = 'categories.view';
    case CategoriesManage = 'categories.manage';

    case EventsView = 'events.view';
    case EventsModerate = 'events.moderate';

    case TalentsView = 'talents.view';
    case TalentsModerate = 'talents.moderate';

    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';
    case CatalogMastersManage = 'catalog_masters.manage';

    case OrdersView = 'orders.view';
    case OrdersManage = 'orders.manage';

    case UsersView = 'users.view';
    case UsersViewStaff = 'users.view_staff';
    case UsersManage = 'users.manage';

    /** The screen this permission belongs to, used to group the management UI. */
    public function group(): string
    {
        return match ($this) {
            self::ConsoleAccess => 'general',
            self::SummaryView, self::OperationsView => 'operations',
            self::CategoriesView, self::CategoriesManage => 'categories',
            self::EventsView, self::EventsModerate => 'events',
            self::TalentsView, self::TalentsModerate => 'talents',
            self::CatalogView, self::CatalogManage, self::CatalogMastersManage => 'catalog',
            self::OrdersView, self::OrdersManage => 'orders',
            self::UsersView, self::UsersViewStaff, self::UsersManage => 'users',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::ConsoleAccess => 'Sign in to the admin console',
            self::SummaryView => 'View the dashboard summary',
            self::OperationsView => 'View refunds and webhooks',
            self::CategoriesView => 'View categories',
            self::CategoriesManage => 'Create, edit and delete categories',
            self::EventsView => 'View submitted events',
            self::EventsModerate => 'Verify, reject and suspend events',
            self::TalentsView => 'View talents',
            self::TalentsModerate => 'Verify talents and manage talent categories',
            self::CatalogView => 'View catalog releases',
            self::CatalogManage => 'Review, assign and distribute releases',
            self::CatalogMastersManage => 'Manage catalog master data',
            self::OrdersView => 'View ticket transactions',
            self::OrdersManage => 'Cancel orders and settle refunds',
            self::UsersView => 'View the member directory',
            self::UsersViewStaff => 'See staff accounts in the directory',
            self::UsersManage => 'Create, edit and delete accounts',
        };
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
