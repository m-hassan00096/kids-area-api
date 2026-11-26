<?php

namespace Modules\Activities\Support;

final class ActivityPermissions
{
    public const CATALOGUE = [
        'activities.index' => 'عرض قائمة الأنشطة',
        'activities.show' => 'عرض تفاصيل النشاط',
        'activities.store' => 'إضافة نشاط',
        'activities.update' => 'تعديل نشاط',
        'activities.delete' => 'حذف نشاط',
    ];

    public const RECEPTION = ['activities.index', 'activities.show'];

    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }
}
