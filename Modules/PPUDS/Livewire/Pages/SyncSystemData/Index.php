<?php

namespace Modules\PPUDS\Livewire\Pages\SyncSystemData;

use App\View\Components\AppLayout;
use Livewire\Component;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Enums\CourseStatus;
use Modules\PPUDS\Jobs\ProcessCoursesSync;
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

    public bool $showCourseStatusReview = false;

    public bool $courseSyncing = false;

    public array $courseStatuses = [];

    public function mount(GeneralSettings $settings)
    {
        $this->academicYear = $settings->year ?? date('Y');
        $this->semester = $settings->semester_type?->value ?? '1';
        $this->logs = PpuApiService::getTerminalLogs();
        $this->loadCourseStatuses();
        $this->refreshCourseSyncingState();
    }

    public function syncCourses(PpuApiService $apiService)
    {
        $this->syncing = true;
        $userId = auth()->id();
        PpuApiService::clearTerminalLogs();
        $this->logs = [];

        PpuApiService::logToTerminal('═══════════════════════════════════════');
        PpuApiService::logToTerminal('   بدء مزامنة المساقات من نظام الجامعة');
        PpuApiService::logToTerminal('═══════════════════════════════════════');

        try {
            [$academicYear, $semester] = $this->resolveSyncSettings($apiService);

            if (! $academicYear || ! $semester) {
                PpuApiService::logToTerminal('تم إيقاف مزامنة المساقات بسبب عدم توفر السنة أو الفصل.');
                return;
            }

            ProcessCoursesSync::dispatch(
                (string) $academicYear,
                (string) $semester,
                $apiService->getAccessToken(),
                $userId,
            );

            $this->showCourseStatusReview = true;
            $this->courseSyncing = true;
            cache()->put($this->courseSyncingCacheKey($userId), true, now()->addHour());

            PpuApiService::logToTerminal("تم إرسال مزامنة المساقات للخلفية للسنة {$academicYear} / الفصل {$semester}.", $userId);
            PpuApiService::logToTerminal('بعد انتهاء مزامنة المساقات، راجع الحالات ثم اضغط إكمال مزامنة النظام.', $userId);
        } catch (\Exception $e) {
            PpuApiService::logToTerminal('✗ خطأ في مزامنة المساقات: ' . $e->getMessage());
        } finally {
            $this->syncing = false;
            $this->refreshLogs();
        }
    }

    public function startSync(PpuApiService $apiService)
    {
        $this->refreshCourseSyncingState();

        if ($this->courseSyncing) {
            PpuApiService::logToTerminal('انتظر انتهاء مزامنة المساقات قبل إكمال مزامنة النظام.');
            $this->refreshLogs();

            return;
        }

        if (! Course::query()->exists()) {
            PpuApiService::logToTerminal('ابدأ أولاً بمزامنة المساقات ثم راجع حالاتها قبل إكمال مزامنة النظام.');
            $this->showCourseStatusReview = true;
            $this->refreshLogs();

            return;
        }

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
        $this->loadCourseStatuses();
        $this->refreshCourseSyncingState();
    }

    public function updatedCourseStatuses($value, string $courseId): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        Course::whereKey($courseId)->update([
            'status' => $isActive ? CourseStatus::ACTIVE->value : CourseStatus::INACTIVE->value,
        ]);

        $this->courseStatuses[(int) $courseId] = $isActive;
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
        return view('ppuds::livewire.pages.sync-system-data.index', [
            'courses' => $this->showCourseStatusReview
                ? Course::query()->orderBy('course_code')->get()
                : collect(),
        ])
            ->layout(AppLayout::class, [
                'breadcrumbs' => [
                    ['title' => __('Home'), 'url' => route('home')],
                    ['title' => __('Sync System Data'), 'url' => route('sync-system-data.index')],
                ],
            ]);
    }

    private function loadCourseStatuses(): void
    {
        $courses = Course::query()
            ->select(['id', 'course_code', 'status'])
            ->orderBy('course_code')
            ->get();

        if ($courses->isEmpty()) {
            return;
        }

        $this->showCourseStatusReview = true;
        $this->courseStatuses = $courses
            ->mapWithKeys(fn (Course $course): array => [
                $course->id => $course->status === CourseStatus::ACTIVE,
            ])
            ->toArray();
    }

    private function refreshCourseSyncingState(): void
    {
        if (! cache()->get($this->courseSyncingCacheKey(), false)) {
            $this->courseSyncing = false;

            return;
        }

        $hasFinishedLog = collect($this->logs ?: PpuApiService::getTerminalLogs())
            ->contains(function (array $log): bool {
                $message = $log['message'] ?? '';

                return str_contains($message, 'انتهت مزامنة المساقات بنجاح')
                    || str_contains($message, 'يمكنك الآن مراجعة حالة المساقات');
            });

        if ($hasFinishedLog) {
            cache()->forget($this->courseSyncingCacheKey());
            $this->courseSyncing = false;

            return;
        }

        $this->courseSyncing = true;
    }

    private function courseSyncingCacheKey(?int $userId = null): string
    {
        return 'sync_courses_running_' . ($userId ?? auth()->id());
    }
}
