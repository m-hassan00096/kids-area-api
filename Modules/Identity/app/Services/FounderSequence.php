<?php

namespace Modules\Identity\Services;

use App\Exceptions\BusinessException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class FounderSequence
{
    public function assertFirstIdAvailable(): void
    {
        $connection = DB::connection();
        $original = null;

        try {
            $settings = $connection->selectOne(
                'SELECT @@SESSION.information_schema_stats_expiry AS expiry, @@SESSION.auto_increment_increment AS step, @@SESSION.auto_increment_offset AS first_offset',
                [],
                false,
            );
            $original = (int) $settings->expiry;
            $connection->statement('SET SESSION information_schema_stats_expiry = 0');

            $metadata = $connection->selectOne(
                "SELECT AUTO_INCREMENT AS next_id FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'",
                [],
                false,
            );

            if ($metadata === null || (string) $metadata->next_id !== '1'
                || (int) $settings->first_offset !== 1 || (int) $settings->step < 1) {
                throw $this->refusal();
            }
        } catch (BusinessException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Inaccessible or ambiguous metadata is not permission to guess the ID.
            throw $this->refusal();
        } finally {
            if ($original !== null) {
                $connection->statement('SET SESSION information_schema_stats_expiry = '.$original);
            }
        }
    }

    private function refusal(): BusinessException
    {
        return new BusinessException(__('identity.founder_id'), errorCode: 'IDENTITY_FOUNDER_ID_UNAVAILABLE');
    }
}
