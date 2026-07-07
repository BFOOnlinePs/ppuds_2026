<?php

namespace Modules\PPUDS\Livewire\Pages\SyncCompanySupervisors;

use App\View\Components\AppLayout;
use Livewire\Component;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Jobs\ProcessCompanySupervisorsUniversitySync;
use Modules\PPUDS\Services\PpuApiService;

class Index extends Component
{
    public array $logs = [];

    public bool $syncing = false;

    public int $companiesCount = 0;

    public int $supervisorPairsCount = 0;

    public function mount(): void
    {
        $this->logs = PpuApiService::getTerminalLogs();
        $this->loadSummary();
        $this->refreshSyncingState();
    }

    public function startSync(PpuApiService $apiService): void
    {
        $this->authorize('Sync System Data Sync');

        $this->refreshSyncingState();

        if ($this->syncing) {
            PpuApiService::logToTerminal('مزامنة مشرفي الشركات تعمل حالياً. انتظر انتهاء العملية الحالية.');
            $this->refreshLogs();

            return;
        }

        $userId = auth()->id();

        PpuApiService::clearTerminalLogs($userId);
        $this->logs = [];
        $this->syncing = true;
        cache()->put($this->syncingCacheKey($userId), true, now()->addHour());

        PpuApiService::logToTerminal('═══════════════════════════════════════', $userId);
        PpuApiService::logToTerminal('   بدء مزامنة الشركات مع API الجامعة', $userId);
        PpuApiService::logToTerminal('═══════════════════════════════════════', $userId);

        try {
            ProcessCompanySupervisorsUniversitySync::dispatch(
                $apiService->getAccessToken(),
                $userId,
            );

            PpuApiService::logToTerminal('تم إرسال مزامنة مشرفي الشركات للخلفية.', $userId);
            PpuApiService::logToTerminal('اترك queue:work يعمل وستظهر النتائج هنا تلقائياً.', $userId);
        } catch (\Exception $e) {
            cache()->forget($this->syncingCacheKey($userId));
            $this->syncing = false;

            PpuApiService::logToTerminal('✗ خطأ في بدء مزامنة مشرفي الشركات: '.$e->getMessage(), $userId);
        } finally {
            $this->refreshLogs();
        }
    }

    public function refreshLogs(): void
    {
        $this->logs = PpuApiService::getTerminalLogs();
        $this->refreshSyncingState();
    }

    public function render()
    {
        return view('ppuds::livewire.pages.sync-company-supervisors.index')
            ->layout(AppLayout::class, [
                'breadcrumbs' => [
                    ['title' => __('Home'), 'url' => route('home')],
                    ['title' => __('Sync Companies With University API'), 'url' => route('sync-company-supervisors.index')],
                ],
            ]);
    }

    private function loadSummary(): void
    {
        $companies = Company::query()
            ->with(['branches.supervisors'])
            ->whereHas('branches.supervisors')
            ->get();

        $this->companiesCount = $companies->count();
        $this->supervisorPairsCount = $companies
            ->sum(fn (Company $company): int => $company->companySupervisors()->count());
    }

    private function refreshSyncingState(): void
    {
        $this->syncing = (bool) cache()->get($this->syncingCacheKey(), false);
    }

    private function syncingCacheKey(?int $userId = null): string
    {
        return 'sync_company_supervisors_running_'.($userId ?? auth()->id());
    }
}
