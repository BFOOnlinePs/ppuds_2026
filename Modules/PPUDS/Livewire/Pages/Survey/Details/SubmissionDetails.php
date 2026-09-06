<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Collection;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;

class SubmissionDetails extends Component implements HasForms
{
    use InteractsWithForms;

    public Survey $survey;

    public User $user;

    public ?StudentCompany $studentCompany = null;

    public ?array $data = [];

    public function mount(Survey $survey, User $user, ?StudentCompany $studentCompany = null)
    {
        abort_unless($this->canViewSurveyDetails(), 403);

        $this->survey = $survey->load([
            'questions' => fn ($query) => $query->orderBy('sort_order'),
            'questions.options' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $this->user = $user->loadMissing('studentProfile');

        $this->studentCompany = $studentCompany?->loadMissing([
            'student.studentProfile',
            'company',
            'branch',
            'department',
        ]);

        abort_unless($this->hasSubmission(), 404);

        $this->form->fill($this->answerState());
    }

    public function form(Form $form): Form
    {
        $schema = [];

        foreach ($this->survey->questions as $question) {
            $fieldName = "question_{$question->id}";
            $options = $question->options->mapWithKeys(fn ($option) => [$option->id => $option->text])->toArray();

            $field = match ((int) $question->type) {
                SurveyQuestionType::TEXT->value => TextInput::make($fieldName),
                SurveyQuestionType::TEXTAREA->value => Textarea::make($fieldName),
                SurveyQuestionType::RADIO->value => Radio::make($fieldName)->options($options)->inline(),
                SurveyQuestionType::CHECKBOX->value => CheckboxList::make($fieldName)->options($options)->columns(['default' => 1, 'sm' => 2, 'md' => 3]),
                SurveyQuestionType::SELECT->value => Select::make($fieldName)->options($options),
                SurveyQuestionType::MULTI_SELECT->value => Select::make($fieldName)->options($options)->multiple(),
                SurveyQuestionType::DATE->value => DatePicker::make($fieldName)->native(false),
                SurveyQuestionType::FILE->value => FileUpload::make($fieldName)->directory('surveys'),
                SurveyQuestionType::RATING->value => Radio::make($fieldName)->options(SurveyQuestionType::ratingScaleOptions())->inline(),
                default => TextInput::make($fieldName),
            };

            $field
                ->label($question->content)
                ->disabled()
                ->dehydrated(false);

            $schema[] = \Filament\Forms\Components\Section::make()
                ->schema([$field])
                ->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl mb-6',
                ]);
        }

        return $form->schema($schema)->statePath('data')->columns(1);
    }

    protected function answerState(): array
    {
        $answers = SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->where('submitted_by', $this->user->id)
            ->when($this->studentCompany, fn ($query) => $query->where('student_company_id', $this->studentCompany->id))
            ->get()
            ->groupBy('survey_question_id');

        $state = [];

        foreach ($this->survey->questions as $question) {
            $questionAnswers = $answers->get($question->id, collect());
            $fieldName = "question_{$question->id}";
            $type = (int) $question->type;

            $state[$fieldName] = match ($type) {
                SurveyQuestionType::CHECKBOX->value,
                SurveyQuestionType::MULTI_SELECT->value => $this->selectedOptions($questionAnswers),
                SurveyQuestionType::RADIO->value,
                SurveyQuestionType::SELECT->value => $questionAnswers->first()?->selected_option_id,
                SurveyQuestionType::FILE->value => $this->textAnswers($questionAnswers),
                default => $questionAnswers->first()?->text_answer,
            };
        }

        return $state;
    }

    protected function selectedOptions(Collection $answers): array
    {
        return $answers
            ->pluck('selected_option_id')
            ->filter()
            ->map(fn ($value) => (string) $value)
            ->values()
            ->toArray();
    }

    protected function textAnswers(Collection $answers): array
    {
        return $answers
            ->pluck('text_answer')
            ->filter()
            ->values()
            ->toArray();
    }

    public function submittedAt(): ?string
    {
        return SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->where('submitted_by', $this->user->id)
            ->when($this->studentCompany, fn ($query) => $query->where('student_company_id', $this->studentCompany->id))
            ->latest()
            ->first()
            ?->created_at
            ?->format('Y-m-d H:i');
    }

    protected function hasSubmission(): bool
    {
        return SurveyAnswer::query()
            ->where('survey_id', $this->survey->id)
            ->where('submitted_by', $this->user->id)
            ->when($this->studentCompany, fn ($query) => $query->where('student_company_id', $this->studentCompany->id))
            ->exists();
    }

    protected function canViewSurveyDetails(): bool
    {
        return auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ]) ?? false;
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.details.submission-details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Surveys'), 'url' => route('surveys.index')],
                ['title' => __('Survey Details'), 'url' => route('surveys.details', $this->survey)],
                ['title' => __('Submission Details'), 'url' => $this->studentCompany
                    ? route('surveys.submission-details', [$this->survey, $this->user, $this->studentCompany])
                    : route('surveys.submission-details', [$this->survey, $this->user])],
            ],
        ]);
    }
}
