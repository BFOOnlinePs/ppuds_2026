<?php

namespace Modules\PPUDS\Livewire\Pages\CompanyStatistics;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Core\Traits\PrintsTableReportPdf;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Exports\CompanyStatisticsExport;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

/**
 * إحصائية الشركات: عدد الطلاب المتدربين في كل شركة موزعين على التخصصات،
 * مع المجموع لكل شركة والإجمالي لكل تخصص.
 */
class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use PrintsTableReportPdf;
    use ScopesStudentCompanyVisibility;

    private ?Collection $majors = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->statisticsQuery())
            ->columns([
                TextColumn::make('name')
                    ->label(__('Company'))
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    )),

                ...$this->majors()->map(
                    fn (Major $major): TextColumn => TextColumn::make("major_{$major->id}_count")
                        ->label($major->name)
                        ->alignCenter()
                        ->sortable()
                        ->summarize(Sum::make()->label(__('Total')))
                )->all(),

                TextColumn::make('students_count')
                    ->label(__('Total'))
                    ->weight('bold')
                    ->alignCenter()
                    ->sortable()
                    ->summarize(Sum::make()->label(__('Total'))),
            ])
            ->defaultSort('students_count', 'desc')
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('export')
                    ->label(__('Export'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new CompanyStatisticsExport($this->getTableQueryForExport(), $this->majors()),
                        'company-statistics-'.now()->format('Y-m-d-His').'.xlsx',
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('Report View List')),

                $this->printPdfAction()
                    ->visible(fn () => auth()->user()->can('Report View List')),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    /**
     * الشركات التي فيها طلاب ضمن الفلاتر، مع عدد طلاب كل تخصص والمجموع.
     * فلاتر السنة والفصل والحالة تُطبَّق على الطلاب المعدودين لا على الشركة،
     * لذلك تُقرأ هنا وتدخل في كل عدّ.
     */
    protected function statisticsQuery(): Builder
    {
        $counts = ['studentCompanies as students_count' => fn (Builder $query): Builder => $this->scopePlacements($query)];

        foreach ($this->majors() as $major) {
            $counts["studentCompanies as major_{$major->id}_count"] = fn (Builder $query): Builder => $this->scopePlacements($query)
                ->whereHas('student.studentProfile', fn (Builder $profileQuery) => $profileQuery->where('major_id', $major->id));
        }

        return $this->applyCompanyVisibilityScope(Company::query())
            ->with('translations')
            ->whereHas('studentCompanies', fn (Builder $query): Builder => $this->scopePlacements($query))
            ->withCount($counts);
    }

    /** التدريب الحالي لكل تسجيل، فلا يُعدّ الطالب مرتين إذا انتقل بين شركتين. */
    protected function scopePlacements(Builder $query): Builder
    {
        $year = $this->tableFilters['year']['year'] ?? null;
        $semesterType = $this->tableFilters['semester_type']['semester_type'] ?? null;
        $statuses = array_filter((array) ($this->tableFilters['status']['status'] ?? []));

        return $this->applyStudentCompanyVisibilityScope($query->currentPerRegistration())
            ->when(filled($year), fn (Builder $query) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('year', $year)))
            ->when(filled($semesterType), fn (Builder $query) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('semester', $semesterType)))
            ->when($statuses !== [], fn (Builder $query) => $query->whereIn($query->qualifyColumn('status'), $statuses));
    }

    protected function majors(): Collection
    {
        return $this->majors ??= Major::query()->with('translations')->orderBy('id')->get();
    }

    protected function getTableFilters(): array
    {
        // هذه الفلاتر لا تعدّل استعلام الشركات مباشرة، بل تُقرأ في scopePlacements.
        return [
            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ]),

            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value),
                ]),

            Filter::make('status')
                ->form([
                    Select::make('status')
                        ->label(__('Training Status'))
                        ->options(TrainingStatus::options())
                        ->multiple(),
                ]),
        ];
    }

    /** صفوف الشركات ثم صف الإجمالي، كما في الجدول وملف الإكسل. */
    protected function tableReportPdfRows(Builder $query, array $columns): Collection
    {
        $companies = $query->get();

        return $companies
            ->map(fn (Company $company): array => array_map(
                fn (Column $column): string => $this->printablePdfCellValue($column, $company),
                array_values($columns),
            ))
            ->push(array_map(
                fn (string $name): string => $name === 'name' ? __('Total') : (string) $companies->sum($name),
                array_keys($columns),
            ));
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Statistics');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company-statistics.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Statistics'), 'url' => route('company-statistics.index')],
            ],
        ]);
    }
}
