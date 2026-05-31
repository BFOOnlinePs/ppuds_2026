<?php

namespace Modules\PPUDS\Livewire\Pages\Survey;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Support\HandlesCompanySupervisorSurveyEvaluations;

class SurveyForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use HandlesCompanySupervisorSurveyEvaluations;

    public Survey $survey;

    public ?array $data = [];

    public function mount(Survey $survey)
    {
        $this->survey = $survey;
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        $schema = [];

        $this->survey->loadMissing([
            'questions.translations',
            'questions.options.translations',
        ]);

        if ($this->isCompanySupervisorEvaluation()) {
            $schema[] = Section::make(__('Student Evaluation'))
                ->schema([
                    Select::make('student_company_id')
                        ->label(__('Student To Evaluate'))
                        ->options(fn (): array => $this->companyStudentOptions())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required(),
                ])
                ->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl mb-6',
                ]);
        }

        foreach ($this->survey->questions->sortBy('sort_order') as $question) {
            $fieldName = "question_{$question->id}";
            $options = $question->options->mapWithKeys(function ($option) {
                return [$option->id => $option->text];
            })->toArray();

            $field = match ((int) $question->type) {
                SurveyQuestionType::TEXT->value         => TextInput::make($fieldName),
                SurveyQuestionType::TEXTAREA->value     => Textarea::make($fieldName),
                SurveyQuestionType::RADIO->value        => Radio::make($fieldName)->options($options)->inline(),
                SurveyQuestionType::CHECKBOX->value     => CheckboxList::make($fieldName)->options($options)->columns(['default' => 1, 'sm' => 2, 'md' => 3]),
                SurveyQuestionType::SELECT->value       => Select::make($fieldName)->options($options),
                SurveyQuestionType::MULTI_SELECT->value => Select::make($fieldName)->options($options)->multiple(),
                SurveyQuestionType::DATE->value         => DatePicker::make($fieldName)->native(false),
                SurveyQuestionType::FILE->value         => FileUpload::make($fieldName)->directory('surveys'),
                SurveyQuestionType::RATING->value       => Radio::make($fieldName)->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])->inline(),
                default => TextInput::make($fieldName),
            };

            $field->label($question->content)
                ->required($question->is_required);

            $schema[] = Section::make()
                ->schema([
                    $field
                ])
                ->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 rounded-xl mb-6'
                ]);
        }

        return $form->schema($schema)->statePath('data')->columns(1);
    }

    public function submit()
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $formData = $this->form->getState();
        $studentCompany = null;

        if ($this->isCompanySupervisorEvaluation()) {
            $studentCompanyId = (int) ($formData['student_company_id'] ?? 0);
            $studentCompany = $this->currentSurveyStudentCompaniesQuery($this->survey, $user->id)
                ->find($studentCompanyId);

            if (! $studentCompany) {
                Toaster::error(__('Selected student is not available for evaluation'));

                return;
            }

            if ($this->survey->hasBeenSubmittedBy($user->id, $studentCompany->id)) {
                Toaster::error(__('This student has already been evaluated for this survey'));

                return;
            }
        } elseif ($this->survey->hasBeenSubmittedBy($user->id)) {
            Toaster::error(__('You have already submitted this survey.'));

            return;
        }

        $answers = [];
        $now = now();

        foreach ($formData as $key => $value) {
            if (str_starts_with($key, 'question_') && $value !== null) {
                $questionId = (int) str_replace('question_', '', $key);
                $question = $this->survey->questions->where('id', $questionId)->first();

                if (is_array($value)) {
                    foreach ($value as $val) {
                        $answers[] = $this->prepareAnswerData($question, $val, $user->id, $now, $studentCompany);
                    }
                } else {
                    $answers[] = $this->prepareAnswerData($question, $value, $user->id, $now, $studentCompany);
                }
            }
        }

        if (! empty($answers)) {
            SurveyAnswer::insert($answers);
        }

        Toaster::success(__('Survey submitted successfully'));

        $this->data = [];
        $this->form->fill();
    }

    protected function prepareAnswerData($question, $value, $userId, $timestamp, ?StudentCompany $studentCompany = null)
    {
        $type = (int) $question->type;

        $isOptionType = in_array($type, [
            SurveyQuestionType::RADIO->value,
            SurveyQuestionType::CHECKBOX->value,
            SurveyQuestionType::SELECT->value,
            SurveyQuestionType::MULTI_SELECT->value,
        ]);

        return [
            'survey_id' => $this->survey->id,
            'survey_question_id' => $question->id,
            'selected_option_id' => $isOptionType ? (int) $value : null,
            'text_answer' => $isOptionType ? null : $value,
            'submitted_by' => $userId,
            'student_company_id' => $studentCompany?->id,
            'evaluated_student_id' => $studentCompany?->student_id,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public function hasSubmitted(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($this->isCompanySupervisorEvaluation()) {
            return $this->totalCompanyStudentsCount() > 0
                && $this->pendingCompanyStudentsCount() === 0;
        }

        return $this->survey->hasBeenSubmittedBy($user->id);
    }

    public function isCompanySupervisorEvaluation(): bool
    {
        return $this->shouldEvaluateStudentsForSurvey($this->survey, auth()->user());
    }

    public function totalCompanyStudentsCount(): int
    {
        $user = auth()->user();

        if (! $user || ! $this->isCompanySupervisorEvaluation()) {
            return 0;
        }

        return $this->currentSurveyStudentCompaniesQuery($this->survey, $user->id)->count();
    }

    public function pendingCompanyStudentsCount(): int
    {
        $user = auth()->user();

        if (! $user || ! $this->isCompanySupervisorEvaluation()) {
            return 0;
        }

        return $this->pendingStudentCompaniesForSupervisorQuery($this->survey, $user->id)->count();
    }

    public function evaluatedCompanyStudentsCount(): int
    {
        return max($this->totalCompanyStudentsCount() - $this->pendingCompanyStudentsCount(), 0);
    }

    protected function companyStudentOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $this->pendingStudentCompaniesForSupervisorQuery($this->survey, $user->id)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (StudentCompany $studentCompany): array => [
                $studentCompany->id => $this->studentCompanyOptionLabel($studentCompany),
            ])
            ->toArray();
    }

    protected function studentCompanyOptionLabel(StudentCompany $studentCompany): string
    {
        $studentName = trim((string) $studentCompany->student?->name) ?: __('Student').' #'.$studentCompany->student_id;
        $studentNumber = trim((string) $studentCompany->student?->studentProfile?->student_number);
        $companyName = trim((string) $studentCompany->company?->name);
        $departmentName = trim((string) $studentCompany->department?->name);

        return collect([$studentName, $studentNumber, $companyName, $departmentName])
            ->filter()
            ->implode(' - ');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.survey-form');
    }
}
