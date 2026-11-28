<?php

namespace Modules\People\Support;

final class CustomerPermissions
{
    public const CATALOGUE = [
        'people.customers.show' => 'عرض بيانات العميل',
        'people.customers.store' => 'إضافة عميل',
        'people.customers.update' => 'تعديل بيانات العميل',
    ];

    public static function keys(): array
    {
        return array_keys(self::CATALOGUE);
    }
}
