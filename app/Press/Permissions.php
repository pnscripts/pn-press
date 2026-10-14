<?php

namespace App\Press;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Capabilities, WordPress style. The admin role passes every check (see AppServiceProvider Gate::before);
 * editors get the content capabilities.
 */
class Permissions
{
    public const MANAGE_PLUGINS = 'manage plugins';

    public const MANAGE_THEMES = 'manage themes';

    public const MANAGE_SETTINGS = 'manage settings';

    public const MANAGE_USERS = 'manage users';

    public const MANAGE_MENUS = 'manage menus';

    public const MANAGE_PAGES = 'manage pages';

    public const MANAGE_MEDIA = 'manage media';

    public const ALL = [
        self::MANAGE_PLUGINS, self::MANAGE_THEMES, self::MANAGE_SETTINGS, self::MANAGE_USERS,
        self::MANAGE_MENUS, self::MANAGE_PAGES, self::MANAGE_MEDIA,
    ];

    public const EDITOR = [self::MANAGE_PAGES, self::MANAGE_MEDIA, self::MANAGE_MENUS];

    /** Create the roles and permissions. Safe to run repeatedly. */
    public static function sync(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ALL as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('admin', 'web')->syncPermissions(self::ALL);
        Role::findOrCreate('editor', 'web')->syncPermissions(self::EDITOR);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
