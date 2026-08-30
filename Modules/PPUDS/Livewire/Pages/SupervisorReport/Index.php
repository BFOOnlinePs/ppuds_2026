<?php

namespace Modules\PPUDS\Livewire\Pages\SupervisorReport;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Exports\SupervisorSummaryExport;
use Modules\PPUDS\Services\SupervisorReportService;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\Core\Traits\PrintsTableReportPdf;

/**
 * Supervisor report — summary view: one row per supervisor with the totals
 * for everything they are responsible for (students, placements, field
 * visits and recorded activity). Each row links to the detailed report.
 */
class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use PrintsTableReportPdf;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(SupervisorReportService::class)->supervisorsQuery($this->reportFilters()))
            ->columns([
                UserColumn::make('name')
                    ->label(__('Supervisor'))
                    ->user(fn (User $record): User => $record)
                    ->subtitle(fn (User $record): ?string => $record->email)
                    ->linksToSupervisor()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('phone')
                    ->label(__('Phone'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('roles.name')
                    ->label(__('Roles'))
                    ->badge()
                    ->separator(',')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('supervised_students_count')
                    ->label(__('Supervised Students Count'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->color('primary')
                    ->summarize(Sum::make('supervised_students_count')->label(__('Total'))),

                TextColumn::make('supervised_trainings_count')
                    ->label(__('Trainings'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('supervised_companies_count')
                    ->label(__('Companies'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('field_visits_count')
                    ->label(__('Field Visits Count'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->color('primary')
                    ->summarize(Sum::make('field_visits_count')->label(__('Total'))),

                TextColumn::make('field_visit_minutes')
                    ->label(__('Duration (Mins)'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('visited_students_count')
                    ->label(__('Visited Students'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_field_visit_at')
                    ->label(__('Last Field Visit'))
                    ->date('Y-m-d')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('activities_count')
                    ->label(__('Activities Count'))
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->color('primary'),

                TextColumn::make('last_activity_at')
                    ->label(__('Last Activity'))
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->bulkActions([])
            ->defaultSort('field_visits_count', 'desc');
    }

    protected function getTableFilters(): array
    {
        $settings = app(GeneralSettings::class);

        return [
            SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(fn (): array => User::query()
                    ->whereHas(
                        'roles',
                        fn (Builder $roles): Builder => $roles->whereIn('name', app(SupervisorReportService::class)->supervisorRoleNames())
                    )
                    ->orWhereHas('supervisedRegistrations')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereKey($data['value'])
                    : $query),

            // The term and date filters narrow the aggregates rather than the
            // rows, so they are applied inside the report query instead.
            Filter::make('term')
                ->label(__('Semester'))
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default($settings->year)
                        ->placeholder(date('Y'))
                        ->live(debounce: 500),

                    Select::make('semester')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default($settings->semester_type->value)
                        ->live(),
                ])
                ->columns(2)
                ->query(fn (Builder $query): Builder => $query)
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if (filled($data['year'] ?? null)) {
                        $indicators[] = Indicator::make(__('Academic Year').': '.$data['year'])->removeField('year');
                    }

                    if (filled($data['semester'] ?? null)) {
                        $label = SemesterType::tryFrom((int) $data['semester'])?->getLabel() ?? $data['semester'];
                        $indicators[] = Indicator::make(__('Semester Type').': '.$label)->removeField('semester');
                    }

                    return $indicators;
                }),

            Filter::make('date_range')
                ->label(__('Date Range'))
                ->form([
                    DatePicker::make('date_from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->live(),

                    DatePicker::make('date_to')
                        ->label(__('To Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->afterOrEqual('date_from')
                        ->live(),
                ])
                ->columns(2)
                ->query(fn (Builder $query): Builder => $query)
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if (filled($data['date_from'] ?? null)) {
                        $indicators[] = Indicator::make(__('From Date').': '.Carbon::parse($data['date_from'])->toDateString())
                            ->removeField('date_from');
                    }

                    if (filled($data['date_to'] ?? null)) {
                        $indicators[] = Indicator::make(__('To Date').': '.Carbon::parse($data['date_to'])->toDateString())
                            ->removeField('date_to');
                    }

                    return $indicators;
                }),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('Export'))
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->action(fn () => app(ExcelServiceInterface::class)->download(
                    new SupervisorSummaryExport($this->getTableQueryForExport()),
                    'supervisor-summary-report-'.now()->format('Y-m-d-His').'.xlsx',
                    WriterType::XLSX
                ))
                ->visible(fn (): bool => auth()->user()->can('Supervisor Report View List')),

            $this->printPdfAction()
                ->visible(fn (): bool => auth()->user()->can('Supervisor Report View List')),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('details')
                ->button()
                ->label(__('Detailed Report'))
                ->icon('solar-document-text-bold-duotone')
                ->color('info')
                ->url(fn (User $record): string => route('supervisor-reports.details', $record->id))
                ->visible(fn (): bool => auth()->user()->can('Supervisor Report View List')),
        ];
    }

    /**
     * The filter state the report query needs. Read here rather than applied
     * through the filters themselves, because the term and date values narrow
     * the aggregate sub-selects instead of the supervisor rows.
     */
    protected function reportFilters(): array
    {
        $term = $this->getTableFilterState('term') ?? [];
        $dateRange = $this->getTableFilterState('date_range') ?? [];

        return [
            'year' => $term['year'] ?? null,
            'semester' => $term['semester'] ?? null,
            'date_from' => filled($dateRange['date_from'] ?? null) ? Carbon::parse($dateRange['date_from'])->toDateString() : null,
            'date_to' => filled($dateRange['date_to'] ?? null) ? Carbon::parse($dateRange['date_to'])->toDateString() : null,
        ];
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Supervisor Summary Report');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Supervisor Report'), 'url' => route('supervisor-reports.index')],
            ],
        ]);
    }
}
