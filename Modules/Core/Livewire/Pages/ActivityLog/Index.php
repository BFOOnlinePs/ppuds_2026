<?php

namespace Modules\Core\Livewire\Pages\ActivityLog;

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
use Modules\Core\Entities\User;
use Modules\Core\Exports\ActivityLogExport;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Core\Traits\ActivityLogReporting;
use Modules\Core\Traits\PrintsTableReportPdf;

/**
 * The system-wide activity log: every recorded action, whoever caused it.
 * The authentication-only view lives in AuthLog.
 */
class Index extends Component implements HasForms, HasTable
{
    use ActivityLogReporting;
    use InteractsWithForms;
    use InteractsWithTable;
    use PrintsTableReportPdf;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->activityQuery()->with('causer'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('causer.name')
                    ->label(__('User'))
                    ->getStateUsing(fn (Model $record): string => $record->causer?->name ?? __('System'))
                    ->description(fn (Model $record): ?string => $record->causer?->email)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHasMorph(
                        'causer',
                        [User::class],
                        fn (Builder $causer): Builder => $causer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                    ))
                    ->weight('bold'),

                TextColumn::make('log_name')
                    ->label(__('Log Name'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __($state) : '—')
                    ->sortable(),

                TextColumn::make('event')
                    ->label(__('Event'))
                    ->badge()
                    ->color(fn (?string $state): string => $this->eventColor($state))
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __($state) : '—')
                    ->sortable(),

                TextColumn::make('description')
                    ->label(__('Description'))
                    ->formatStateUsing(fn (?string $state): string => __((string) $state))
                    ->searchable()
                    ->wrap()
                    ->limit(80),

                TextColumn::make('subject_type')
                    ->label(__('Subject Type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __(class_basename($state)) : '—')
                    ->toggleable(),

                TextColumn::make('subject_id')
                    ->label(__('Subject Id'))
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip')
                    ->label(__('IP Address'))
                    ->getStateUsing(fn (Model $record): string => (string) ($this->activityProperties($record)['ip'] ?? '—'))
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

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('log_name')
                ->label(__('Log Name'))
                ->options(fn (): array => $this->logNameOptions())
                ->searchable()
                ->preload(),

            SelectFilter::make('event')
                ->label(__('Event'))
                ->options(fn (): array => $this->eventOptions())
                ->searchable()
                ->preload(),

            SelectFilter::make('causer_id')
                ->label(__('User'))
                ->options(fn (): array => $this->causerOptions())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->where('causer_id', $data['value'])->where('causer_type', (new User)->getMorphClass())
                    : $query),

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
                    'activity-log-'.now()->format('Y-m-d-His').'.xlsx',
                    WriterType::XLSX
                ))
                ->visible(fn (): bool => auth()->user()->can('Activity Log View List')),

            $this->printPdfAction()
                ->visible(fn (): bool => auth()->user()->can('Activity Log View List')),
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

                    KeyValue::make('context')
                        ->label(__('Request Details'))
                        ->default($this->requestProperties($record))
                        ->disabled(),
                ])
                ->modalSubmitAction(false),
        ];
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Activity Log');
    }

    public function render()
    {
        return view('core::livewire.pages.activity-log.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Activity Log'), 'url' => route('activity-logs.index')],
            ],
        ]);
    }
}
