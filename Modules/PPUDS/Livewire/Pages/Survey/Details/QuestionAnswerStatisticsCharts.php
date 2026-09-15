<?php

namespace Modules\PPUDS\Livewire\Pages\Survey\Details;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Str;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Enums\UserRole;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Survey;
use Modules\PPUDS\Entities\SurveyQuestion;
use Modules\PPUDS\Enums\SurveyQuestionType;
use Modules\PPUDS\Exports\SurveyStatisticsExport;

class QuestionAnswerStatisticsCharts extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?int $surveyId = null;

    protected ?Survey $survey = null;

    public function canViewCharts(): bool
    {
        return ! (auth()->user()?->hasRole(UserRole::STUDENT->value) ?? false);
    }

    public function chartWidgets(): array
    {
        if (! $this->canViewCharts()) {
            return [];
        }

        if (! $this->surveyId) {
            return [];
        }

        return SurveyQuestion::query()
            ->where('survey_id', $this->surveyId)
            ->whereIn('type', $this->optionQuestionTypes())
            ->whereHas('options')
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($questionId) => QuestionAnswerStatisticsChart::make([
                'surveyId' => $this->surveyId,
                'questionId' => (int) $questionId,
            ]))
            ->all();
    }

    public function exportStatisticsAction(): Action
    {
        return Action::make('exportStatistics')
            ->label(__('Export Statistics'))
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->action(fn () => app(ExcelServiceInterface::class)->download(
                new SurveyStatisticsExport($this->survey()),
                $this->exportFilename(),
                WriterType::XLSX
            ))
            ->visible(fn (): bool => $this->canViewCharts() && $this->survey() !== null);
    }

    protected function survey(): ?Survey
    {
        if ($this->survey !== null) {
            return $this->survey;
        }

        if (! $this->surveyId) {
            return null;
        }

        return $this->survey = Survey::find($this->surveyId);
    }

    protected function exportFilename(): string
    {
        $slug = Str::slug((string) $this->survey()?->title);

        return 'survey-statistics-'.($slug ?: $this->surveyId).'-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function optionQuestionTypes(): array
    {
        return [
            SurveyQuestionType::RADIO->value,
            SurveyQuestionType::CHECKBOX->value,
            SurveyQuestionType::SELECT->value,
            SurveyQuestionType::MULTI_SELECT->value,
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.survey.details.question-answer-statistics-charts');
    }
}
