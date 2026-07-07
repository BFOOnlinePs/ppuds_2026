<?php

namespace Modules\PPUDS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PPUDS\Services\PpuApiService;

class ProcessCompanySupervisorsUniversitySync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    protected string $token;

    protected ?int $initiatorId;

    public function __construct(string $token, ?int $initiatorId = null)
    {
        $this->token = $token;
        $this->initiatorId = $initiatorId;
    }

    public function handle(PpuApiService $apiService): void
    {
        PpuApiService::logToTerminal('بدأ تنفيذ مزامنة مشرفي الشركات في الخلفية.', $this->initiatorId);

        try {
            $apiService->syncCompanySupervisorsToUniversity(
                $this->token,
                $this->initiatorId,
            );
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ فشل تنفيذ مزامنة مشرفي الشركات في الخلفية: '.$e->getMessage(), $this->initiatorId);
            Log::error('ProcessCompanySupervisorsUniversitySync Error: '.$e->getMessage());

            throw $e;
        } finally {
            if ($this->initiatorId) {
                cache()->forget("sync_company_supervisors_running_{$this->initiatorId}");
            }

            PpuApiService::logToTerminal('═══════════════════════════════════════', $this->initiatorId);
            PpuApiService::logToTerminal('   انتهت مزامنة مشرفي الشركات', $this->initiatorId);
            PpuApiService::logToTerminal('═══════════════════════════════════════', $this->initiatorId);
        }
    }
}
