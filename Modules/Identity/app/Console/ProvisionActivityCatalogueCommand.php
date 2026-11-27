<?php

namespace Modules\Identity\Console;

use App\Exceptions\BusinessException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Identity\Actions\ProvisionActivityCatalogueAction;
use Throwable;

final class ProvisionActivityCatalogueCommand extends Command
{
    protected $signature = 'identity:provision-activity-catalogue {--confirm-reviewed-upgrade : Confirm the reviewed upgrade and quiesced staff traffic}';

    protected $description = 'Apply the fixed Activity Catalogue defaults once on a reviewed existing installation';

    public function handle(ProvisionActivityCatalogueAction $action): int
    {
        if (! $this->option('confirm-reviewed-upgrade')) {
            $this->error(__('identity.activity_provision_confirmation'));

            return self::FAILURE;
        }

        try {
            $applied = $action->execute();
        } catch (BusinessException $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        } catch (Throwable $failure) {
            Log::error('Activity catalogue provisioning failed.', ['exception_class' => $failure::class]);
            $this->error(__('identity.activity_provision_failed'));

            return self::FAILURE;
        }

        $this->info(__($applied ? 'identity.activity_provision_completed' : 'identity.activity_provision_already_completed'));

        return self::SUCCESS;
    }
}
