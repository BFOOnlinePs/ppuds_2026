<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\TrainingPlan;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * تبويب خطة التدريب: قراءة فقط، وبنطاق تدريبات الطالب التي يحق للمستخدم رؤيتها.
 * studentCompanyId يحصر العرض في تدريب واحد عند فتحه من صفحة تفاصيل التدريب.
 */
class Index extends Component
{
    use ScopesStudentCompanyVisibility;

    public ?int $studentId = null;

    public ?int $studentCompanyId = null;

    public function mount(?int $studentId = null, ?int $studentCompanyId = null)
    {
        $this->authorize('TrainingPlan View');

        $this->studentId = $studentId;
        $this->studentCompanyId = $studentCompanyId;
    }

    #[Computed]
    public function plans(): Collection
    {
        return $this->applyStudentCompanyVisibilityScope(
            StudentCompany::query()
                ->where('student_id', $this->studentId)
                ->when($this->studentCompanyId, fn ($query) => $query->whereKey($this->studentCompanyId))
        )
            ->with(['registration', 'student.studentProfile'])
            ->get()
            ->map(fn (StudentCompany $studentCompany) => $studentCompany->trainingPlan())
            ->filter()
            ->unique('id')
            ->values();
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.training-plan.index');
    }
}
