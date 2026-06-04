<?php

namespace Modules\Core\Livewire\Pages\Home;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Actions\StudentCompanyAssistant\FindCompaniesForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\FindStudentsForCompanyAssistant;
use Modules\Core\Actions\StudentCompanyAssistant\LinkSuggestedCompanyToStudent;
use Modules\Core\Actions\StudentCompanyAssistant\ResolveStudentCompanyRegistration;
use Modules\Core\Entities\User;
use Modules\Core\Services\StudentCompanySuggestionService;
use Modules\Core\Services\StudentTrainingChatService;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings as PPUDSSettings;

class StudentCompanyAssistant extends Component
{
    use AuthorizesRequests;

    public string $studentName = '';

    public array $messages = [];

    public array $studentMatches = [];

    public array $companyMatches = [];

    public array $suggestions = [];

    public array $studentContext = [];

    public ?int $studentId = null;

    public ?int $registrationId = null;

    public ?string $selectedStudentName = null;

    public ?string $selectedRegistrationLabel = null;

    public string $pendingStudentAction = 'info';

    public ?string $pendingQuestion = null;

    public function mount(): void
    {
        $this->messages = [
            [
                'role' => 'assistant',
                'text' => 'أهلاً، اسألني عن أي طالب أو عن معلومات تدريبه، وإذا أردت اقتراح شركات اكتب ذلك صراحة.',
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
        $this->studentName = '';

        $this->addMessage('user', $term);

        if ($this->wantsCompanyUnlink($term)) {
            $this->handleCompanyUnlinkRequest($term);

            return;
        }

        $wantsCompanySuggestions = $this->wantsCompanySuggestions($term);
        $wantsStudentInformation = $this->wantsStudentInformation($term) || (! $this->studentId && ! $wantsCompanySuggestions);

        if ($this->studentId && $this->registrationId && ! $wantsCompanySuggestions && ! $wantsStudentInformation && $this->handleCompanyLinkRequest($term)) {
            return;
        }

        if ($wantsStudentInformation) {
            $this->answerStudentQuestion($term);

            return;
        }

        $this->searchStudentForSuggestions($term);
    }

    private function searchStudentForSuggestions(string $term): void
    {
        $studentTerm = app(StudentTrainingChatService::class)->studentLookupTerm($term);

        if (blank($studentTerm) && $this->studentId) {
            $student = User::query()
                ->with('studentProfile.major')
                ->whereHas('studentProfile')
                ->find($this->studentId);

            if ($student) {
                $this->studentMatches = [];
                $this->companyMatches = [];
                $this->suggestions = [];
                $this->suggestForStudent($student);

                return;
            }
        }

        $this->resetSelection();
        $this->pendingStudentAction = 'suggest';
        $this->pendingQuestion = $term;

        $findStudents = app(FindStudentsForCompanyAssistant::class);
        $studentTerm = $studentTerm ?: $term;
        $matches = $findStudents->handle($studentTerm);

        if ($matches->isEmpty()) {
            $this->addMessage('assistant', 'لم أجد طالبًا مطابقًا لهذا الاسم أو الرقم الجامعي.');

            return;
        }

        $exactMatch = $findStudents->exactMatch($matches, $studentTerm);

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

    public function selectCompany(int $companyId): void
    {
        $this->authorize('StudentCompany Create');

        if (! $this->studentId || ! $this->registrationId) {
            $this->addMessage('assistant', 'اختر طالبًا قبل اختيار الشركة.');

            return;
        }

        $findCompanies = app(FindCompaniesForCompanyAssistant::class);
        $company = $findCompanies->find($companyId);

        if (! $company) {
            $this->addMessage('assistant', 'تعذر العثور على الشركة المختارة.');

            return;
        }

        $this->companyMatches = [];
        $this->linkSuggestion(
            $findCompanies->toSuggestion($company),
        );
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
        $this->companyMatches = [];

        if ($this->pendingStudentAction === 'info') {
            $this->answerStudentQuestion($this->pendingQuestion ?: 'معلومات الطالب', $student);

            return;
        }

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

        $this->linkSuggestion($suggestion);
    }

    private function linkSuggestion(array $suggestion): void
    {
        if (! $this->ensureCurrentRegistrationSelected()) {
            return;
        }

        $result = app(LinkSuggestedCompanyToStudent::class)->handle(
            $this->studentId,
            $this->registrationId,
            $suggestion,
            auth()->id(),
        );

        if ($result === 'already_exists') {
            $this->markSuggestionLinked((int) $suggestion['company_id']);

            $message = "الطالب {$this->selectedStudentName} مسجل مسبقًا في شركة {$suggestion['company_name']}، لذلك لم يتم إنشاء ربط جديد.";

            $this->addMessage('assistant', $message);
            Toaster::error($message);

            return;
        }

        $this->showLinkedSuggestion($suggestion);

        $message = "تم ربط {$suggestion['company_name']} بالطالب {$this->selectedStudentName}.";

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

        if (! $this->ensureCurrentRegistrationSelected()) {
            return;
        }

        $created = 0;
        $updated = 0;
        $alreadyExists = 0;
        $linkCompany = app(LinkSuggestedCompanyToStudent::class);

        foreach ($this->suggestions as $suggestion) {
            $result = $linkCompany->handle(
                $this->studentId,
                $this->registrationId,
                $suggestion,
                auth()->id(),
            );

            match ($result) {
                'created' => $created++,
                'already_exists' => $alreadyExists++,
                default => $updated++,
            };
        }

        foreach ($this->suggestions as $suggestion) {
            $this->markSuggestionLinked((int) $suggestion['company_id']);
        }

        $message = "تمت معالجة الاقتراحات للطالب {$this->selectedStudentName}. الجديد: {$created}، المسجل مسبقًا: {$alreadyExists}، المحدّث: {$updated}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    private function suggestForStudent(User $student): void
    {
        [$registration, $warning] = app(ResolveStudentCompanyRegistration::class)->handle($student);

        if (! $registration) {
            $this->addMessage('assistant', $warning ?: 'وجدت الطالب، لكن لا يوجد له سجل تسجيل يمكن ربط الشركات به.');

            return;
        }

        if ($warning) {
            $this->addMessage('assistant', $warning);
        }

        $this->studentId = $student->id;
        $this->registrationId = $registration->id;
        $this->selectedStudentName = $student->name;
        $this->selectedRegistrationLabel = $this->registrationLabel($registration);
        $this->studentContext = [
            'student_name' => $student->name,
            'student_number' => $student->studentProfile?->student_number,
            'major' => $student->studentProfile?->major?->name,
            'registration' => $this->selectedRegistrationLabel,
        ];

        $this->addMessage('assistant', "تم اختيار {$student->name}. أبحث الآن عن أفضل الشركات المناسبة.");

        $result = app(StudentCompanySuggestionService::class)->suggest($student, $registration);
        $this->suggestions = $result['suggestions'];

        $this->addMessage('assistant', $result['message']);
    }

    private function handleCompanyLinkRequest(string $term): bool
    {
        $findCompanies = app(FindCompaniesForCompanyAssistant::class);
        $companies = $findCompanies->handle($term);

        if ($companies->isEmpty()) {
            $this->companyMatches = [];
            $this->addMessage('assistant', 'لم أجد شركة مطابقة لهذا الاسم. اكتب اسم الشركة كما هو ظاهر في النظام أو اختر من الاقتراحات.');

            return true;
        }

        if ($companies->count() > 1) {
            $this->companyMatches = $companies
                ->map(fn ($company) => $findCompanies->payload($company))
                ->values()
                ->all();

            $this->addMessage('assistant', 'وجدت أكثر من شركة مطابقة. اختر الشركة التي تريد ربطها بالطالب.');

            return true;
        }

        $company = $companies->first();
        $this->companyMatches = [
            $findCompanies->payload($company),
        ];

        $this->addMessage('assistant', 'وجدت شركة مطابقة. اضغط على الشركة لتأكيد ربطها بالطالب.');

        return true;
    }

    private function handleCompanyUnlinkRequest(string $term): void
    {
        $this->authorize('StudentCompany Delete');

        $this->companyMatches = [];
        $this->suggestions = [];

        if (! $this->studentId || ! $this->registrationId) {
            $this->addMessage('assistant', 'اختر الطالب أولًا، ثم اطلب إلغاء ربطه بالشركة.');

            return;
        }

        if (! $this->ensureCurrentRegistrationSelected()) {
            return;
        }

        $studentCompany = $this->studentCompanyForUnlink($term);
        $companyName = $this->companyNameFromUnlinkTerm($term);

        if (! $studentCompany) {
            $message = filled($companyName)
                ? "لم أجد ربطًا حاليًا بين الطالب {$this->selectedStudentName} وشركة {$companyName}."
                : "لا توجد شركة تدريب مربوطة حاليًا بالطالب {$this->selectedStudentName}.";

            $this->addMessage('assistant', $message);
            Toaster::error($message);

            return;
        }

        $unlinkedCompanyName = $studentCompany->company?->name ?: 'الشركة الحالية';

        DB::transaction(function () use ($studentCompany) {
            $studentCompany->forceFill([
                'status' => TrainingStatus::DELETED,
            ])->save();

            $studentCompany->delete();
        });

        $this->refreshTrainingContextFromCurrentStudent();

        $message = "تم إلغاء ربط {$unlinkedCompanyName} بالطالب {$this->selectedStudentName}.";

        $this->addMessage('assistant', $message);
        Toaster::success($message);
    }

    private function answerStudentQuestion(string $question, ?User $student = null): void
    {
        $this->companyMatches = [];
        $this->suggestions = [];
        $this->pendingStudentAction = 'info';
        $this->pendingQuestion = $question;

        $service = app(StudentTrainingChatService::class);
        $result = $student
            ? $service->answerForStudent($question, $student->id)
            : $service->answer($question, $this->studentId);

        $this->studentMatches = $result['student_matches'] ?? [];

        if ($this->studentMatches !== []) {
            $this->addMessage('assistant', $result['message']);

            return;
        }

        if (filled($result['student_id'] ?? null)) {
            $this->studentId = (int) $result['student_id'];
            $this->registrationId = $this->currentOrLatestRegistrationId($this->studentId);
            $this->selectedStudentName = $result['student_name'] ?? null;
            $this->selectedRegistrationLabel = $result['registration_label'] ?? null;
            $this->studentContext = $result['context'] ?? [];
        }

        $this->addMessage('assistant', $result['message']);
    }

    private function currentOrLatestRegistrationId(int $studentId): ?int
    {
        $settings = app(PPUDSSettings::class);
        $currentRegistration = Registration::query()
            ->where('student_id', $studentId)
            ->when($settings->semester_type?->value, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($settings->year, fn ($query, $year) => $query->where('year', $year))
            ->latest('id')
            ->value('id');

        if ($currentRegistration) {
            return (int) $currentRegistration;
        }

        $latestRegistration = Registration::query()
            ->where('student_id', $studentId)
            ->latest('id')
            ->value('id');

        return $latestRegistration ? (int) $latestRegistration : null;
    }

    private function wantsStudentInformation(string $term): bool
    {
        $term = mb_strtolower($term);

        return str_contains($term, 'معلومات')
            || str_contains($term, 'تفاصيل')
            || str_contains($term, 'استعلام')
            || str_contains($term, 'تدريب')
            || str_contains($term, 'يتدرب')
            || str_contains($term, 'بتدرب')
            || str_contains($term, 'شركته')
            || str_contains($term, 'شركة الطالب')
            || str_contains($term, 'اسم الشركة')
            || ((str_contains($term, 'شركة') || str_contains($term, 'الشركة')) && (
                str_contains($term, 'ما')
                || str_contains($term, 'وين')
                || str_contains($term, 'اين')
                || str_contains($term, 'أين')
            ))
            || str_contains($term, 'حضور')
            || str_contains($term, 'غياب')
            || str_contains($term, 'دوام')
            || str_contains($term, 'مشرف')
            || str_contains($term, 'زيارة')
            || str_contains($term, 'زيارات')
            || str_contains($term, 'تقرير')
            || str_contains($term, 'تقارير')
            || str_contains($term, 'تقييم')
            || str_contains($term, 'علامة')
            || str_contains($term, 'حالة');
    }

    private function wantsCompanyUnlink(string $term): bool
    {
        $term = mb_strtolower($term);

        $hasUnlinkVerb = str_contains($term, 'إلغاء')
            || str_contains($term, 'الغاء')
            || str_contains($term, 'إلغي')
            || str_contains($term, 'الغي')
            || str_contains($term, 'فك')
            || str_contains($term, 'إزالة')
            || str_contains($term, 'ازالة');

        return $hasUnlinkVerb
            && (
                str_contains($term, 'ربط')
                || str_contains($term, 'شركة')
                || str_contains($term, 'الشركة')
            );
    }

    private function wantsCompanySuggestions(string $term): bool
    {
        $term = mb_strtolower($term);

        return str_contains($term, 'اقترح')
            || str_contains($term, 'اقتراح')
            || str_contains($term, 'رشح')
            || str_contains($term, 'ترشيح')
            || str_contains($term, 'أفضل شركات')
            || str_contains($term, 'افضل شركات')
            || str_contains($term, 'شركات مناسبة')
            || str_contains($term, 'شركة مناسبة');
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

    private function ensureCurrentRegistrationSelected(): bool
    {
        if ($this->currentRegistrationIsStillValid()) {
            return true;
        }

        $this->resetSelection();
        $this->addMessage('assistant', 'التسجيل المختار لم يعد مطابقًا للفصل والسنة في الإعدادات. اختر الطالب مرة أخرى.');

        return false;
    }

    private function currentRegistrationIsStillValid(): bool
    {
        if (! $this->studentId || ! $this->registrationId) {
            return false;
        }

        $settings = app(PPUDSSettings::class);

        return Registration::query()
            ->whereKey($this->registrationId)
            ->where('student_id', $this->studentId)
            ->where('semester', $settings->semester_type?->value)
            ->where('year', $settings->year)
            ->exists();
    }

    private function studentCompanyForUnlink(string $term): ?StudentCompany
    {
        $query = StudentCompany::query()
            ->with(['company', 'branch', 'department'])
            ->where('student_id', $this->studentId)
            ->where('registration_id', $this->registrationId)
            ->whereNotNull('company_id')
            ->latest('id');

        $companyName = $this->companyNameFromUnlinkTerm($term);

        if (filled($companyName)) {
            return (clone $query)
                ->whereHas('company', fn ($companyQuery) => $companyQuery->whereTranslationLike('name', '%'.$companyName.'%'))
                ->first();
        }

        return $query->first();
    }

    private function companyNameFromUnlinkTerm(string $term): string
    {
        $cleaned = preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]+/u', ' ', mb_strtolower($term)) ?? $term;
        $cleaned = trim(preg_replace('/\s+/u', ' ', $cleaned) ?? $cleaned);

        $stopWords = [
            'إلغاء', 'الغاء', 'إلغي', 'الغي', 'فك', 'إزالة', 'ازالة',
            'ربط', 'الطالب', 'طالب', 'الطالبة', 'طالبة', 'هذه', 'هذة',
            'هذا', 'هذي', 'هاي', 'بهذه', 'بهاي', 'الشركة', 'شركة',
            'عن', 'من', 'مع',
        ];

        $words = collect(preg_split('/\s+/u', $cleaned) ?: [])
            ->map(fn (string $word): string => $this->normalizeCompanyNameWord($word))
            ->filter(fn (string $word): bool => mb_strlen($word) >= 2 && ! in_array($word, $stopWords, true))
            ->values();

        return mb_substr($words->implode(' '), 0, 120);
    }

    private function normalizeCompanyNameWord(string $word): string
    {
        if (str_starts_with($word, 'بب')) {
            return mb_substr($word, 1);
        }

        if (str_starts_with($word, 'بال')) {
            return 'ال'.mb_substr($word, 3);
        }

        return $word;
    }

    private function refreshTrainingContextFromCurrentStudent(): void
    {
        if (! $this->studentId || ! $this->registrationId) {
            return;
        }

        $studentCompany = StudentCompany::query()
            ->with(['company', 'branch', 'department'])
            ->withAttendanceDays()
            ->withCount('leaveRequests')
            ->where('student_id', $this->studentId)
            ->where('registration_id', $this->registrationId)
            ->latest('id')
            ->first();

        if (! $studentCompany) {
            $this->studentContext = array_merge($this->studentContext, [
                'company' => null,
                'branch' => null,
                'department' => null,
                'status' => null,
                'attendance_days' => 0,
                'leave_requests' => 0,
            ]);

            return;
        }

        $this->studentContext = array_merge($this->studentContext, [
            'company' => $studentCompany->company?->name,
            'branch' => $studentCompany->branch?->name,
            'department' => $studentCompany->department?->name,
            'status' => $studentCompany->status?->getLabel(),
            'attendance_days' => (int) ($studentCompany->attendance_days ?? 0),
            'leave_requests' => (int) ($studentCompany->leave_requests_count ?? 0),
        ]);
    }

    private function resetSelection(): void
    {
        $this->studentMatches = [];
        $this->companyMatches = [];
        $this->suggestions = [];
        $this->studentContext = [];
        $this->studentId = null;
        $this->registrationId = null;
        $this->selectedStudentName = null;
        $this->selectedRegistrationLabel = null;
        $this->pendingStudentAction = 'info';
        $this->pendingQuestion = null;
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

    private function showLinkedSuggestion(array $suggestion): void
    {
        $suggestion['linked'] = true;

        $this->suggestions = collect($this->suggestions)
            ->reject(fn (array $existingSuggestion) => (int) $existingSuggestion['company_id'] === (int) $suggestion['company_id'])
            ->prepend($suggestion)
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
