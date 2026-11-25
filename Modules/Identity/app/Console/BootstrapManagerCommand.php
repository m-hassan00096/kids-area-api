<?php

namespace Modules\Identity\Console;

use App\Exceptions\BusinessException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Identity\Actions\BootstrapManagerAction;
use Throwable;

class BootstrapManagerCommand extends Command
{
    protected $signature = 'identity:bootstrap-manager';

    protected $description = 'Provision the initial Manager account once, after the Identity seeders';

    public function handle(BootstrapManagerAction $action): int
    {
        if (! $this->input->isInteractive() || ! $this->hasTerminal()) {
            $this->error(__('identity.interactive_required'));

            return self::FAILURE;
        }

        try {
            $credentials = [
                'name' => $this->ask(__('identity.name')),
                'username' => $this->ask(__('identity.username')),
                'email' => $this->ask(__('identity.email')),
                'phone' => $this->ask(__('identity.phone')),
                'password' => $this->secret(__('identity.password'), false),
                'password_confirmation' => $this->secret(__('identity.password_confirmation'), false),
            ];

            $action->execute($credentials);
        } catch (ValidationException) {
            $this->error(__('identity.invalid_data'));

            return self::FAILURE;
        } catch (BusinessException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            // Never report raw exception strings, bindings or credential context.
            Log::error('Identity founder provisioning failed.', ['exception_class' => $exception::class]);
            $this->error(__('identity.bootstrap_failed'));

            return self::FAILURE;
        } finally {
            unset($credentials);
        }

        $this->info(__('identity.bootstrap_success'));

        return self::SUCCESS;
    }

    protected function hasTerminal(): bool
    {
        return defined('STDIN') && stream_isatty(STDIN);
    }
}
