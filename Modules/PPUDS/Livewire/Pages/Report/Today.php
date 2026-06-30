<?php

namespace Modules\PPUDS\Livewire\Pages\Report;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentReport;
use Modules\PPUDS\Livewire\Concerns\SearchesStudentAttendanceRelationships;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Today extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use SearchesStudentAttendanceRelationships;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->todayReportsQuery())
            ->columns([
                TextColumn::make('student_number')
                    ->label(__('Student Number'))
                    ->getStateUsing(fn (StudentReport $record): ?string => $record->studentAttendance?->studentCompany?->student?->studentProfile?->student_number)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyStudentReportStudentSearch($query, $search))
                    ->weight('bold'),

                TextColumn::make('student_name')
                    ->label(__('Student Name'))
                    ->getStateUsing(fn (StudentReport $record): HtmlString|string => $this->studentDisplayColumnState($record))
                    ->html()
                    ->url(fn (StudentReport $record): ?string => $this->studentDetailsUrl($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyStudentReportStudentSearch($query, $search))
                    ->wrap(),

                TextColumn::make('company')
                    ->label(__('Company'))
                    ->getStateUsing(fn (StudentReport $record): ?string => $record->studentAttendance?->studentCompany?->company?->name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $this->applyStudentReportCompanySearch($query, $search))
                    ->wrap(),

                TextColumn::make('branch')
                    ->label(__('Branch'))
                    ->getStateUsing(fn (StudentReport $record): ?string => $record->studentAttendance?->studentCompany?->branch?->name)
                    ->toggleable(),

                TextColumn::make('department')
                    ->label(__('Department'))
                    ->getStateUsing(fn (StudentReport $record): ?string => $record->studentAttendance?->studentCompany?->department?->name)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('studentAttendance.attendance_date')
                    ->label(__('Attendance Date'))
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('report_text')
                    ->label(__('Report Text'))
                    ->html()
                    ->limit(70)
                    ->wrap(),

                TextColumn::make('company_feedback_status')
                    ->label(__('Company Feedback'))
                    ->getStateUsing(fn (StudentReport $record): string => $this->hasText($record->company_feedback) ? __('Yes') : __('No'))
                    ->badge()
                    ->color(fn (string $state): string => $state === __('Yes') ? 'success' : 'gray')
                    ->toggleable(),

                TextColumn::make('academic_feedback_status')
                    ->label(__('Academic Feedback'))
                    ->getStateUsing(fn (StudentReport $record): string => $this->hasText($record->academic_feedback) ? __('Yes') : __('No'))
                    ->badge()
                    ->color(fn (string $state): string => $state === __('Yes') ? 'success' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Submitted At'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('submit_latitude')
                    ->label(__('Location'))
                    ->icon('heroicon-m-map-pin')
                    ->color('primary')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? __('View Map') : '---')
                    ->url(fn (StudentReport $record): ?string => filled($record->submit_latitude) && filled($record->submit_longitude)
                        ? "https://www.google.com/maps/search/?api=1&query={$record->submit_latitude},{$record->submit_longitude}"
                        : null)
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions($this->getTableActions())
            ->bulkActions($this->getTableBulkActions())
            ->defaultSort('created_at', 'desc');
    }

    protected function todayReportsQuery(): Builder
    {
        [$fromDate, $untilDate] = $this->submittedDateRange();

        return StudentReport::query()
            ->with([
                'createdBy',
                'studentAttendance.studentCompany.student.studentProfile',
                'studentAttendance.studentCompany.student.media',
                'studentAttendance.studentCompany.company.translations',
                'studentAttendance.studentCompany.branch.translations',
                'studentAttendance.studentCompany.department.translations',
            ])
            ->when($fromDate, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $fromDate))
            ->when($untilDate, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $untilDate))
            ->whereHas(
                'studentAttendance.studentCompany',
                fn (Builder $studentCompanyQuery): Builder => $this->applyStudentCompanyVisibilityScope($studentCompanyQuery)
            );
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('submitted_date')
                ->label(__('Submitted At'))
                ->form([
                    DatePicker::make('from')
                        ->label(__('From Date'))
                        ->default(now()->toDateString())
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),

                    DatePicker::make('until')
                        ->label(__('Until Date'))
                        ->default(now()->toDateString())
                        ->native(false)
                        ->format('Y-m-d')
                        ->displayFormat('Y-m-d'),
                ])
                ->columns(2)
                ->query(fn (Builder $query): Builder => $query)
                ->indicateUsing(function (array $data): array {
                    $indicators = [];

                    if (! empty($data['from'])) {
                        $indicators[] = Indicator::make(__('From Date').': '.Carbon::parse($data['from'])->toDateString())
                            ->removeField('from');
                    }

                    if (! empty($data['until'])) {
                        $indicators[] = Indicator::make(__('Until Date').': '.Carbon::parse($data['until'])->toDateString())
                            ->removeField('until');
                    }

                    return $indicators;
                }),

            Filter::make('student')
                ->label(__('Student'))
                ->form([
                    TextInput::make('search')
                        ->label(__('Number / Name'))
                        ->live(debounce: 500),
                ])
                ->query(fn (Builder $query, array $data): Builder => filled($data['search'] ?? null)
                    ? $this->applyStudentReportStudentSearch($query, $data['search'])
                    : $query),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => Company::query()
                    ->with('translations')
                    ->tap(fn (Builder $query) => $this->applyCompanyVisibilityScope($query))
                    ->orderBy('id')
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->whereHas(
                        'studentAttendance.studentCompany',
                        fn (Builder $studentCompanyQuery): Builder => $studentCompanyQuery->where('company_id', $data['value'])
                    )
                    : $query),

            Filter::make('feedback_status')
                ->label(__('Report Status'))
                ->form([
                    Select::make('status')
                        ->label(__('Report Status'))
                        ->options([
                            'with_company_feedback' => __('Company Feedback').' - '.__('Yes'),
                            'without_company_feedback' => __('Company Feedback').' - '.__('No'),
                            'with_academic_feedback' => __('Academic Feedback').' - '.__('Yes'),
                            'without_academic_feedback' => __('Academic Feedback').' - '.__('No'),
                        ])
                        ->native(false),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return match ($data['status'] ?? null) {
                        'with_company_feedback' => $query->whereNotNull('company_feedback')->where('company_feedback', '!=', ''),
                        'without_company_feedback' => $query->where(fn (Builder $query): Builder => $query->whereNull('company_feedback')->orWhere('company_feedback', '')),
                        'with_academic_feedback' => $query->whereNotNull('academic_feedback')->where('academic_feedback', '!=', ''),
                        'without_academic_feedback' => $query->where(fn (Builder $query): Builder => $query->whereNull('academic_feedback')->orWhere('academic_feedback', '')),
                        default => $query,
                    };
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->tooltip(__('View Details'))
                ->modalHeading(__('Report Details'))
                ->form(fn (StudentReport $record): array => [
                    Grid::make(2)->schema([
                        TextInput::make('student')
                            ->label(__('Student Name'))
                            ->default($record->studentAttendance?->studentCompany?->student?->name)
                            ->disabled(),

                        TextInput::make('student_number')
                            ->label(__('Student Number'))
                            ->default($record->studentAttendance?->studentCompany?->student?->studentProfile?->student_number)
                            ->disabled(),

                        TextInput::make('company')
                            ->label(__('Company'))
                            ->default($record->studentAttendance?->studentCompany?->company?->name)
                            ->disabled(),

                        TextInput::make('attendance_date')
                            ->label(__('Attendance Date'))
                            ->default($record->studentAttendance?->attendance_date?->format('Y-m-d'))
                            ->disabled(),
                    ]),

                    RichEditor::make('report_text')
                        ->label(__('Report Text'))
                        ->default($record->report_text)
                        ->disabled()
                        ->toolbarButtons([]),

                    RichEditor::make('company_feedback')
                        ->label(__('Company Feedback'))
                        ->default($record->company_feedback)
                        ->disabled()
                        ->toolbarButtons([]),

                    RichEditor::make('academic_feedback')
                        ->label(__('Academic Feedback'))
                        ->default($record->academic_feedback)
                        ->disabled()
                        ->toolbarButtons([]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn (): bool => auth()->user()->can('StudentReport View')
                    || auth()->user()->can('Report View')
                    || auth()->user()->can('Report View List')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function (StudentReport $record): void {
                    $record->delete();
                    Toaster::success(__('Report deleted successfully'));
                })
                ->visible(fn (): bool => auth()->user()->can('StudentReport Delete')),
        ];
    }

    protected function getTableBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn (): mixed => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn (): bool => auth()->user()->can('StudentReport Delete')),
            ]),
        ];
    }

    protected function applyStudentReportStudentSearch(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'studentAttendance.studentCompany',
            fn (Builder $studentCompanyQuery): Builder => $this->applyStudentSearchToStudentCompanyQuery($studentCompanyQuery, $search)
        );
    }

    protected function applyStudentReportCompanySearch(Builder $query, string $search): Builder
    {
        return $query->whereHas(
            'studentAttendance.studentCompany',
            fn (Builder $studentCompanyQuery): Builder => $this->applyCompanySearchToStudentCompanyQuery($studentCompanyQuery, $search)
        );
    }

    protected function studentDisplayColumnState(StudentReport $record): HtmlString|string
    {
        return $record->studentAttendance?->studentCompany?->student?->getUserDisplayHtmlAttribute() ?? '---';
    }

    protected function studentDetailsUrl(StudentReport $record): ?string
    {
        $student = $record->studentAttendance?->studentCompany?->student;

        if (! $student || ! auth()->user()?->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function submittedDateRange(): array
    {
        $state = $this->getTableFilterState('submitted_date') ?? [];

        $from = $state['from'] ?? now()->toDateString();
        $until = $state['until'] ?? now()->toDateString();

        return [
            filled($from) ? Carbon::parse($from)->toDateString() : null,
            filled($until) ? Carbon::parse($until)->toDateString() : null,
        ];
    }

    protected function hasText(?string $value): bool
    {
        return filled(trim(strip_tags((string) $value)));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.report.today')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Today Reports'), 'url' => route('reports.today')],
            ],
        ]);
    }
}
