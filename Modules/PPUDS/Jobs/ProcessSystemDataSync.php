<?php

namespace Modules\PPUDS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\PPUDS\Services\PpuApiService;

class ProcessSystemDataSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    protected string $academicYear;

    protected string $semesterNo;

    protected string $token;

    protected ?int $initiatorId;

    public function __construct(string $academicYear, string $semesterNo, string $token, ?int $initiatorId = null)
    {
        $this->academicYear = $academicYear;
        $this->semesterNo = $semesterNo;
        $this->token = $token;
        $this->initiatorId = $initiatorId;
    }

    public function handle(PpuApiService $apiService): void
    {
        PpuApiService::logToTerminal('بدأ تنفيذ المزامنة في الخلفية.', $this->initiatorId);

        try {
            $apiService->syncSystemData(
                $this->academicYear,
                $this->semesterNo,
                $this->token,
                $this->initiatorId
            );
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ فشل تنفيذ المزامنة في الخلفية: ' . $e->getMessage(), $this->initiatorId);
            Log::error('ProcessSystemDataSync Error: ' . $e->getMessage());

            throw $e;
        } finally {
            PpuApiService::logToTerminal('═══════════════════════════════════════', $this->initiatorId);
            PpuApiService::logToTerminal('   انتهت عملية المزامنة', $this->initiatorId);
            PpuApiService::logToTerminal('═══════════════════════════════════════', $this->initiatorId);
        }
    }
}
