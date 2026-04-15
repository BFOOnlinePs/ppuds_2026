<?php

namespace Modules\PPUDS\Livewire\Pages\Survey;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
use Livewire\Component;
use Modules\Core\Settings\GeneralSettings;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyAnswer;
use Modules\PPUDS\Enums\SurveyQuestionType;

class SurveyForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Survey $survey;

    public ?array $data = [];

    public function mount(Survey $survey)
    {
        $this->survey = $survey;
        $this->form->fill();
    }

    public function getSettings()
    {
        return app(GeneralSettings::class);
    }

    public function form(Form $form): Form
    {
        $schema = [];

        $this->survey->loadMissing([
            'questions.translations',
            'questions.options.translations',
        ]);

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

            $schema[] = \Filament\Forms\Components\Section::make()
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

        $hasSubmitted = SurveyAnswer::where('survey_id', $this->survey->id)
            ->where('submitted_by', $user->id)
            ->exists();

        if ($hasSubmitted) {
            // Notification::make()->danger()->title(__('You have already submitted this survey'))->send();

            return;
        }

        $formData = $this->form->getState();
        $answers = [];
        $now = now();

        foreach ($formData as $key => $value) {
            if (str_starts_with($key, 'question_') && $value !== null) {
                $questionId = (int) str_replace('question_', '', $key);
                $question = $this->survey->questions->where('id', $questionId)->first();

                if (is_array($value)) {
                    foreach ($value as $val) {
                        $answers[] = $this->prepareAnswerData($question, $val, $user->id, $now);
                    }
                } else {
                    $answers[] = $this->prepareAnswerData($question, $value, $user->id, $now);
                }
            }
        }

        if (! empty($answers)) {
            SurveyAnswer::insert($answers);
        }
    }

    protected function prepareAnswerData($question, $value, $userId, $timestamp)
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
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.survey-form');
    }
}
