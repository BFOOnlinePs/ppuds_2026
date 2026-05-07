<?php

namespace Modules\PPUDS\Livewire\Pages\SyncSystemData;

use App\View\Components\AppLayout;
use Livewire\Component;
use Modules\PPUDS\Services\PpuApiService;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component
{
    public array $logs = [];

    public bool $syncing = false;

    public string $academicYear;

    public string $semester;

    public function mount(GeneralSettings $settings)
    {
        $this->academicYear = $settings->year ?? date('Y');
        $this->semester = $settings->semester_type?->value ?? '1';
        $this->logs = PpuApiService::getTerminalLogs();
    }

    public function startSync(PpuApiService $apiService)
    {
        $this->syncing = true;
        PpuApiService::clearTerminalLogs();
        $this->logs = [];

        PpuApiService::logToTerminal('═══════════════════════════════════════');
        PpuApiService::logToTerminal('   بدء عملية المزامنة مع نظام الجامعة');
        PpuApiService::logToTerminal('═══════════════════════════════════════');

        try {
            $apiService->syncMajors();
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ خطأ في مزامنة التخصصات: ' . $e->getMessage());
        }

        try {
            $apiService->syncStudents($this->academicYear, $this->semester);
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ خطأ في مزامنة الطلاب: ' . $e->getMessage());
        }

        PpuApiService::logToTerminal('═══════════════════════════════════════');
        PpuApiService::logToTerminal('   انتهت عملية المزامنة');
        PpuApiService::logToTerminal('═══════════════════════════════════════');

        $this->syncing = false;
        $this->logs = PpuApiService::getTerminalLogs();
    }

    public function refreshLogs()
    {
        $this->logs = PpuApiService::getTerminalLogs();
    }

    public function render()
    {
        return view('ppuds::livewire.pages.sync-system-data.index')
            ->layout(AppLayout::class, [
                'breadcrumbs' => [
                    ['title' => __('Home'), 'url' => route('home')],
                    ['title' => __('Sync System Data'), 'url' => route('sync-system-data.index')],
                ],
            ]);
    }
}
