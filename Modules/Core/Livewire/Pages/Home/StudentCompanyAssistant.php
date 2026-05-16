<?php

namespace Modules\Core\Livewire\Pages\Home;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Services\StudentCompanySuggestionService;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSSettings;

class StudentCompanyAssistant extends Component
{
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
        $this->ensureCanCreate();

        $this->validate([
            'studentName' => ['required', 'string', 'min:2', 'max:120'],
        ], [
            'studentName.required' => 'يرجى كتابة اسم الطالب أو رقمه الجامعي.',
            'studentName.min' => 'اكتب حرفين على الأقل للبحث عن الطالب.',
        ]);

        $term = trim($this->studentName);

        $this->addMessage('user', $term);
        $this->resetSelection();

        $matches = $this->findStudents($term);

        if ($matches->isEmpty()) {
            $this->addMessage('assistant', 'لم أجد طالبًا مطابقًا لهذا الاسم أو الرقم الجامعي.');

            return;
        }

        $exactMatch = $this->exactStudentMatch($matches, $term);

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
        $this->ensureCanCreate();

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
        $this->ensureCanCreate();

        if (! $this->studentId || ! $this->registrationId) {
            $this->addMessage('assistant', 'اختر طالبًا واحصل على اقتراحات قبل تنفيذ الربط.');

            return;
        }

        $suggestion = collect($this->suggestions)->firstWhere('company_id', $companyId);

        if (! $suggestion) {
            $this->addMessage('assistant', 'هذا الاقتراح لم يعد متاحًا للربط.');

            return;
        }

        $result = DB::transaction(fn () => $this->saveStudentCompany($suggestion));
        $this->markSuggestionLinked($companyId);

        $message = $result === 'created'
            ? "تم ربط {$suggestion['company_name']} بالطالب {$this->selectedStudentName}."
            : "تم تحديث ربط {$suggestion['company_name']} بالطالب {$this->selectedStudentName}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    public function linkAllSuggestions(): void
    {
        $this->ensureCanCreate();

        if (! $this->studentId || ! $this->registrationId || $this->suggestions === []) {
            $this->addMessage('assistant', 'لا توجد اقتراحات جاهزة للربط.');

            return;
        }

        [$created, $updated] = DB::transaction(function () {
            $created = 0;
            $updated = 0;

            foreach ($this->suggestions as $suggestion) {
                $result = $this->saveStudentCompany($suggestion);
                $result === 'created' ? $created++ : $updated++;
            }

            return [$created, $updated];
        });

        foreach ($this->suggestions as $suggestion) {
            $this->markSuggestionLinked((int) $suggestion['company_id']);
        }

        $message = "تم ربط الاقتراحات بالطالب {$this->selectedStudentName}. الجديد: {$created}، المحدّث: {$updated}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    private function suggestForStudent(User $student): void
    {
        $registration = $this->registrationFor($student);

        if (! $registration) {
            $this->addMessage('assistant', 'وجدت الطالب، لكن لا يوجد له سجل تسجيل يمكن ربط الشركات به.');

            return;
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

    private function findStudents(string $term): Collection
    {
        return User::query()
            ->with('studentProfile.major')
            ->whereHas('studentProfile')
            ->where(function ($query) use ($term) {
                $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('name_en', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery->where('student_number', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->limit(6)
            ->get();
    }

    private function exactStudentMatch(Collection $matches, string $term): ?User
    {
        $normalizedTerm = Str::lower($term);

        return $matches->first(function (User $student) use ($normalizedTerm) {
            return Str::lower((string) $student->name) === $normalizedTerm
                || Str::lower((string) $student->name_en) === $normalizedTerm
                || (string) $student->studentProfile?->student_number === $normalizedTerm
                || Str::lower((string) $student->email) === $normalizedTerm;
        });
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

    private function registrationFor(User $student): ?Registration
    {
        $settings = app(PPUDSSettings::class);
        $semester = $settings->semester_type?->value;
        $year = $settings->year;

        $currentRegistration = Registration::query()
            ->with('course')
            ->where('student_id', $student->id)
            ->when($semester, fn ($query) => $query->where('semester', $semester))
            ->when($year, fn ($query) => $query->where('year', $year))
            ->latest('id')
            ->first();

        if ($currentRegistration) {
            return $currentRegistration;
        }

        $latestRegistration = Registration::query()
            ->with('course')
            ->where('student_id', $student->id)
            ->latest('id')
            ->first();

        if ($latestRegistration) {
            $this->addMessage('assistant', 'لم أجد تسجيلًا للفصل الحالي، لذلك سأستخدم آخر سجل تسجيل متاح للطالب.');
        }

        return $latestRegistration;
    }

    private function saveStudentCompany(array $suggestion): string
    {
        [$branchId, , $departmentId] = app(StudentCompanySuggestionService::class)->placementForCompany(
            (int) $suggestion['company_id'],
            $suggestion['branch_id'] ?? null,
            $suggestion['department_id'] ?? null,
        );

        $attributes = [
            'student_id' => $this->studentId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'status' => TrainingStatus::AVAILABLE,
            'created_by' => auth()->id(),
        ];

        $studentCompany = StudentCompany::withTrashed()
            ->where('registration_id', $this->registrationId)
            ->where('company_id', $suggestion['company_id'])
            ->first();

        if ($studentCompany) {
            if ($studentCompany->trashed()) {
                $studentCompany->restore();
            }

            $studentCompany->fill($attributes)->save();

            return 'updated';
        }

        StudentCompany::create($attributes + [
            'registration_id' => $this->registrationId,
            'company_id' => $suggestion['company_id'],
        ]);

        return 'created';
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

    private function ensureCanCreate(): void
    {
        abort_unless(auth()->user()?->can('StudentCompany Create'), 403);
    }

    public function render()
    {
        return view('core::livewire.pages.home.student-company-assistant');
    }
}
