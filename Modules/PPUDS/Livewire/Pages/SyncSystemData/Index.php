<?php

namespace Modules\PPUDS\Livewire\Pages\SyncSystemData;

use App\View\Components\AppLayout;
use Livewire\Component;
use Modules\PPUDS\Jobs\ProcessSystemDataSync;
use Modules\PPUDS\Services\PpuApiService;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component
{
    public array $logs = [];

    public bool $syncing = false;

    public string $academicYear;

    public string $semester;

    public bool $useUniversitySettings = true;

    public function mount(GeneralSettings $settings)
    {
        $this->academicYear = $settings->year ?? date('Y');
        $this->semester = $settings->semester_type?->value ?? '1';
        $this->logs = PpuApiService::getTerminalLogs();
    }

    public function startSync(PpuApiService $apiService)
    {
        $this->syncing = true;
        $userId = auth()->id();
        PpuApiService::clearTerminalLogs();
        $this->logs = [];

        PpuApiService::logToTerminal('═══════════════════════════════════════');
        PpuApiService::logToTerminal('   بدء عملية المزامنة مع نظام الجامعة');
        PpuApiService::logToTerminal('═══════════════════════════════════════');

        try {
            [$academicYear, $semester] = $this->resolveSyncSettings($apiService);

            if (! $academicYear || ! $semester) {
                PpuApiService::logToTerminal('تم إيقاف المزامنة بسبب عدم توفر السنة أو الفصل.');
                return;
            }

            ProcessSystemDataSync::dispatch(
                (string) $academicYear,
                (string) $semester,
                $apiService->getAccessToken(),
                $userId,
            );

            PpuApiService::logToTerminal("تم إرسال المزامنة للخلفية للسنة {$academicYear} / الفصل {$semester}.", $userId);
            PpuApiService::logToTerminal('اترك queue:work يعمل وستظهر النتائج هنا تلقائياً.', $userId);
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ خطأ في المزامنة الكاملة: ' . $e->getMessage());
        } finally {
            $this->syncing = false;
            $this->logs = PpuApiService::getTerminalLogs();
        }
    }

    public function refreshLogs()
    {
        $this->logs = PpuApiService::getTerminalLogs();
    }

    private function resolveSyncSettings(PpuApiService $apiService): array
    {
        if (! $this->useUniversitySettings) {
            PpuApiService::logToTerminal("تم اعتماد الإعدادات اليدوية: السنة {$this->academicYear} / الفصل {$this->semester}");

            return [$this->academicYear, $this->semester];
        }

        $settings = $apiService->getCurrentSemesterSettings();

        if (! $settings) {
            return [null, null];
        }

        $this->academicYear = $settings['academicYear'];
        $this->semester = $settings['semesterNo'];

        return [$this->academicYear, $this->semester];
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
