<?php

namespace Modules\Core\Livewire\Pages\Home;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\LinkSuggestedCompanyToStudent;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\Core\Entities\User;
use Modules\Core\Services\StudentCompanySuggestionService;
use Modules\PPUDS\Entities\Registration;

class StudentCompanyAssistant extends Component
{
    use AuthorizesRequests;

    public string $studentName = '';

    public array $messages = [];

    public array $studentMatches = [];

    public array $suggestions = [];

    public ?int $studentId = null;

    public ?int $registrationId = null;

    public ?string $selectedStudentName = null;

    public ?string $selectedRegistrationLabel = null;

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => 'اكتب اسم الطالب أو رقمه الجامعي، وسأقترح شركات مناسبة ثم يمكنك ربطها مباشرة بسجل الطالب.',
            ],
        ];
    }

    public function send(): void
    {
        $this->authorize('StudentCompany Create');

        $this->validate([
            'studentName' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'studentName.required' => 'يرجى كتابة اسم الطالب أو رقمه الجامعي.',
            'studentName.min' => 'اكتب حرفين على الأقل للبحث عن الطالب.',
        ]);

        $term = trim($this->studentName);

        $this->addMessage('user', $term);
        $this->resetSelection();

        $findStudents = app(FindStudentsForCompanyAssistant::class);
        $matches = $findStudents->handle($term);

        if ($matches->isEmpty()) {
            $this->addMessage('assistant', 'لم أجد طالبًا مطابقًا لهذا الاسم أو الرقم الجامعي.');

            return;
        }

        $exactMatch = $findStudents->exactMatch($matches, $term);

        if (! $exactMatch && $matches->count() > 1) {
            $this->studentMatches = $matches
                ->map(fn (User $student) => $this->studentMatchPayload($student))
                ->values()
                ->all();

            $this->addMessage('assistant', 'وجدت أكثر من طالب. اختر الطالب المطلوب من النتائج التالية.');

            return;
        }

        $this->suggestForStudent($exactMatch ?? $matches->first());
    }

    public function selectStudent(int $studentId): void
    {
        $this->authorize('StudentCompany Create');

        $student = User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->find($studentId);

        if (! $student) {
            $this->addMessage('assistant', 'تعذر العثور على الطالب المختار.');

            return;
        }

        $this->studentMatches = [];
        $this->suggestForStudent($student);
    }

    public function linkCompany(int $companyId): void
    {
        $this->authorize('StudentCompany Create');

        if (! $this->studentId || ! $this->registrationId) {
            $this->addMessage('assistant', 'اختر طالبًا واحصل على اقتراحات قبل تنفيذ الربط.');

            return;
        }

        $suggestion = collect($this->suggestions)->firstWhere('company_id', $companyId);

        if (! $suggestion) {
            $this->addMessage('assistant', 'هذا الاقتراح لم يعد متاحًا للربط.');

            return;
        }

        $result = app(LinkSuggestedCompanyToStudent::class)->handle(
            $this->studentId,
            $this->registrationId,
            $suggestion,
            auth()->id(),
        );

        $this->markSuggestionLinked($companyId);

        $message = $result === 'created'
            ? "تم ربط {$suggestion['company_name']} بالطالب {$this->selectedStudentName}."
            : "تم تحديث ربط {$suggestion['company_name']} بالطالب {$this->selectedStudentName}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    public function linkAllSuggestions(): void
    {
        $this->authorize('StudentCompany Create');

        if (! $this->studentId || ! $this->registrationId || $this->suggestions === []) {
            $this->addMessage('assistant', 'لا توجد اقتراحات جاهزة للربط.');

            return;
        }

        $created = 0;
        $updated = 0;
        $linkCompany = app(LinkSuggestedCompanyToStudent::class);

        foreach ($this->suggestions as $suggestion) {
            $result = $linkCompany->handle(
                $this->studentId,
                $this->registrationId,
                $suggestion,
                auth()->id(),
            );

            $result === 'created' ? $created++ : $updated++;
        }

        foreach ($this->suggestions as $suggestion) {
            $this->markSuggestionLinked((int) $suggestion['company_id']);
        }

        $message = "تم ربط الاقتراحات بالطالب {$this->selectedStudentName}. الجديد: {$created}، المحدّث: {$updated}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    private function suggestForStudent(User $student): void
    {
        [$registration, $warning] = app(ResolveStudentCompanyRegistration::class)->handle($student);

        if (! $registration) {
            $this->addMessage('assistant', 'وجدت الطالب، لكن لا يوجد له سجل تسجيل يمكن ربط الشركات به.');

            return;
        }

        if ($warning) {
            $this->addMessage('assistant', $warning);
        }

        $this->studentId = $student->id;
        $this->registrationId = $registration->id;
        $this->selectedStudentName = $student->name;
        $this->selectedRegistrationLabel = $this->registrationLabel($registration);

        $this->addMessage('assistant', "تم اختيار {$student->name}. أبحث الآن عن أفضل الشركات المناسبة.");

        $result = app(StudentCompanySuggestionService::class)->suggest($student, $registration);
        $this->suggestions = $result['suggestions'];

        $this->addMessage('assistant', $result['message']);
    }

    private function studentMatchPayload(User $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'major' => $student->studentProfile?->major?->name,
            'email' => $student->email,
        ];
    }

    private function registrationLabel(Registration $registration): string
    {
        $semester = $registration->semester?->getLabel() ?? $registration->semester?->value;
        $course = $registration->course?->name ?? 'بدون مقرر';

        return "{$course} - {$semester}/{$registration->year}";
    }

    private function resetSelection(): void
    {
        $this->studentMatches = [];
        $this->suggestions = [];
        $this->studentId = null;
        $this->registrationId = null;
        $this->selectedStudentName = null;
        $this->selectedRegistrationLabel = null;
    }

    private function markSuggestionLinked(int $companyId): void
    {
        $this->suggestions = collect($this->suggestions)
            ->map(function (array $suggestion) use ($companyId) {
                if ((int) $suggestion['company_id'] === $companyId) {
                    $suggestion['linked'] = true;
                }

                return $suggestion;
            })
            ->values()
            ->all();
    }

    private function addMessage(string $role, string $text): void
    {
        $this->messages[] = compact('role', 'text');
    }

    public function render()
    {
        return view('core::livewire.pages.home.student-company-assistant');
    }
}
