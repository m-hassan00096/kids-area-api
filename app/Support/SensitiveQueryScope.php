<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Connection;

final class SensitiveQueryScope
{
    public static function run(Connection $connection, Closure $operation): mixed
    {
        $logging = $connection->logging();
        $dispatcher = $connection->getEventDispatcher();
        $connection->disableQueryLog();
        $connection->unsetEventDispatcher();

        try {
            return $operation();
        } finally {
            if ($dispatcher !== null) {
                $connection->setEventDispatcher($dispatcher);
            }
            if ($logging) {
                $connection->enableQueryLog();
            }
        }
    }
}
