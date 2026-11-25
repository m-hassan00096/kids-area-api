<?php

namespace Modules\Identity\Actions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Services\StaffSession;
use RuntimeException;
use SensitiveParameter;

final class LogoutAction
{
    public function __construct(private readonly StaffSession $sessions) {}

    public function execute(#[SensitiveParameter] Request $request): void
    {
        $binding = $this->sessions->requireBinding($request, $this->sessions->now());
        $id = $request->session()->getId();
        try {
            DB::transaction(function () use ($binding, $id): void {
                $row = DB::table('sessions')->where('id', $id)->lockForUpdate()->first();
                if ($row === null || (int) $row->auth_user_id !== (int) $binding->auth_user_id
                    || (int) $row->auth_version !== (int) $binding->auth_version
                    || (int) $row->auth_last_activity <= $this->sessions->now() - StaffSession::IDLE_SECONDS) {
                    throw new AuthenticationException;
                }

                if (DB::table('sessions')->where('id', $id)->delete() !== 1) {
                    throw new RuntimeException('Current staff session was not retired.');
                }
            }, 1);
        } catch (AuthenticationException $exception) {
            $this->sessions->retire($request);
            throw $exception;
        }

        $this->sessions->retire($request);
    }
}
