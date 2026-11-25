<?php

namespace Modules\Identity\Support;

final class IdentityPermissions
{
    public const CATALOGUE = [
        'identity.users.view' => 'عرض المستخدمين',
        'identity.users.create' => 'إنشاء مستخدم',
        'identity.users.update' => 'تعديل المستخدمين',
        'identity.users.delete' => 'حذف المستخدمين',
        'identity.roles.index' => 'عرض قائمة الأدوار',
        'identity.roles.store' => 'إنشاء دور',
        'identity.roles.show' => 'عرض تفاصيل الدور',
        'identity.roles.update' => 'تعديل الأدوار والصلاحيات',
        'identity.roles.delete' => 'حذف الأدوار',
        'identity.audit.view' => 'عرض سجل التدقيق',
    ];

    public const ESSENTIAL = [
        'identity.users.view',
        'identity.users.create',
        'identity.users.update',
        'identity.roles.index',
        'identity.roles.show',
        'identity.roles.update',
    ];

    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }
}
