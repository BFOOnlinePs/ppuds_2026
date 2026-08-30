<?php

namespace Modules\PPUDS\Livewire\Pages\SupervisorReport\Details\Activity;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Exports\ActivityLogExport;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Core\Traits\ActivityLogReporting;
use Modules\Core\Traits\PrintsTableReportPdf;
use Modules\PPUDS\Services\SupervisorReportService;

/**
 * Every activity-log entry caused by one supervisor. Rendered as a tab of the
 * detailed supervisor report, and usable on its own.
 */
class Index extends Component implements HasForms, HasTable
{
    use ActivityLogReporting;
    use InteractsWithForms;
    use InteractsWithTable;
    use PrintsTableReportPdf;

    public ?int $supervisorId = null;

    public function mount(?int $supervisorId = null): void
    {
        $this->supervisorId = $supervisorId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->activityQuery()->with('causer'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('log_name')
                    ->label(__('Log Name'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __($state) : '—'),

                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn (?string $state): string => $this->eventColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __($state) : '—'),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->formatStateUsing(fn (?string $state): string => __((string) $state))
                    ->wrap()
                    ->limit(80),

                TextColumn::make('subject_type')
                    ->label(__('Subject Type'))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __(class_basename($state)) : '—')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('subject_id')
                    ->label(__('Subject Id'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('changes')
                    ->label(__('Changes'))
                    ->getStateUsing(fn (Model $record): string => $this->changesSummary($record))
                    ->wrap()
                    ->limit(90)
                    ->toggleable(),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions($this->getTableHeaderActions())
            ->actions($this->getTableActions())
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Narrows every activity-log helper on the trait — including the filter
     * option lists — to this one supervisor.
     */
    protected function activityQuery(): Builder
    {
        if (! $this->supervisorId) {
            return $this->activityModel()::query()->whereRaw('1 = 0');
        }

        return app(SupervisorReportService::class)->activitiesQuery($this->supervisorId);
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('event')
                ->label(__('Event'))
                ->options(fn (): array => $this->eventOptions())
                ->searchable()
                ->preload(),

            SelectFilter::make('log_name')
                ->label(__('Log Name'))
                ->options(fn (): array => $this->logNameOptions())
                ->searchable()
                ->preload(),

            SelectFilter::make('subject_type')
                ->label(__('Subject Type'))
                ->options(fn (): array => $this->subjectTypeOptions())
                ->searchable()
                ->preload(),

            Filter::make('date_range')
                ->label(__('Date Range'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->native(false)
                        ->displayFormat('Y-m-d')
                        ->afterOrEqual('from'),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data): Builder => $this->applyDateRange($query, $data))
                ->indicateUsing(fn (array $data): array => $this->dateRangeIndicators($data)),
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
                    new ActivityLogExport($this->getTableQueryForExport()),
                    'supervisor-activities-'.now()->format('Y-m-d-His').'.xlsx',
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
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->modalHeading(__('Activity Log'))
                ->form(fn (Model $record): array => [
                    TextInput::make('description')
                        ->label(__('Description'))
                        ->default(__((string) $record->description))
                        ->disabled(),

                    TextInput::make('subject')
                        ->label(__('Subject Type'))
                        ->default($record->subject_type ? __(class_basename($record->subject_type)).' #'.$record->subject_id : '—')
                        ->disabled(),

                    KeyValue::make('old')
                        ->label(__('Old Values'))
                        ->default($this->flattenProperties($record, 'old'))
                        ->disabled(),

                    KeyValue::make('attributes')
                        ->label(__('New Values'))
                        ->default($this->flattenProperties($record, 'attributes'))
                        ->disabled(),
                ])
                ->modalSubmitAction(false),
        ];
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Supervisor Activity Log');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor-report.details.activity.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Supervisor Report'), 'url' => route('supervisor-reports.index')],
            ],
        ]);
    }
}
