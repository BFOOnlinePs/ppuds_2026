<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\TrainingPlan;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\PPUDS\Entities\Registration;
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
    public function studentCompanies(): Collection
    {
        return $this->applyStudentCompanyVisibilityScope(
            StudentCompany::query()
                ->where('student_id', $this->studentId)
                ->when($this->studentCompanyId, fn ($query) => $query->whereKey($this->studentCompanyId))
        )
            ->with(['registration.subjects', 'registration.course.translations', 'student.studentProfile'])
            ->get();
    }

    #[Computed]
    public function plans(): Collection
    {
        return $this->studentCompanies
            ->map(fn (StudentCompany $studentCompany) => $studentCompany->trainingPlan())
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * المواد الجامعية للطالب (المدخلة يدوياً لكل تسجيل) مجمّعة حسب المساق،
     * لتُعرض داخل قسم خطة المساق نفسه.
     */
    #[Computed]
    public function registrationSubjects(): Collection
    {
        if (! auth()->user()->can('RegistrationSubject View')) {
            return collect();
        }

        return $this->studentCompanies
            ->pluck('registration')
            ->filter()
            ->unique('id')
            ->groupBy('course_id')
            ->map(fn (Collection $registrations) => [
                'course' => $registrations->first()->course,
                'subjects' => $registrations->flatMap(fn (Registration $registration) => $registration->subjects),
            ])
            ->filter(fn (array $group) => $group['subjects']->isNotEmpty());
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.training-plan.index');
    }
}
