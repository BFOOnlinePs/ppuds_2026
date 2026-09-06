<?php

namespace Modules\PPUDS\Livewire\Pages\FinalDeliveryReport;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\Core\Traits\PrintsTableReportPdf;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Exports\FinalDeliveryReportExport;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\HasSupervisorFilter;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use HasSupervisorFilter;
    use PrintsTableReportPdf;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => StudentCompany::query()
                ->with([
                    'branch',
                    'company',
                    'department',
                    'registration',
                    'student.studentProfile',
                ])
                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query)))
            ->columns([
                TextColumn::make('student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->weight('bold')
                    ->searchable(),

                UserColumn::make('student.name')
                    ->label(__('Student Name'))
                    ->user(fn (StudentCompany $record) => $record->student)
                    ->summarize(Count::make('student.name')),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    )),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable(),

                TextColumn::make('department.name')
                    ->label(__('Department'))
                    ->toggleable(),

                TextColumn::make('status')
                    ->label(__('Delivery Status'))
                    ->badge(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('registration.year')
                    ->label(__('Year'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->headerActions([
                Action::make('export')
                    ->label(__('Export'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new FinalDeliveryReportExport($this->getTableQueryForExport()),
                        $this->exportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('Report View List')),

                $this->printPdfAction()
                    ->visible(fn () => auth()->user()->can('Report View List')),
            ])
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('number')
                        ->label(__('Number / Name'))
                        ->live(debounce: 500),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['number'])) {
                        $query->where(function (Builder $query) use ($data) {
                            $query->whereHas(
                                'student.studentProfile',
                                fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$data['number']}%")
                            )->orWhereHas(
                                'student',
                                fn (Builder $studentQuery) => $studentQuery->where('name', 'like', "%{$data['number']}%")
                            );
                        });
                    }
                }),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            SelectFilter::make('status')
                ->label(__('Delivery Status'))
                ->options(TrainingStatus::options()),

            $this->supervisorSelectFilter('registration'),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'] ?? null,
                        fn (Builder $query, $year) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('year', $year))
                    );
                }),

            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'] ?? null,
                        fn (Builder $query, $semesterType) => $query->whereHas('registration', fn (Builder $registrationQuery) => $registrationQuery->where('semester', $semesterType))
                    );
                }),
        ];
    }

    protected function exportFilename(): string
    {
        return 'final-delivery-report-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function tableReportPdfTitle(): string
    {
        return __('Final Delivery Report');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.final-delivery-report.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Reports'), 'url' => route('reports.index')],
                ['title' => __('Final Delivery Report'), 'url' => route('final-delivery-reports.index')],
            ],
        ]);
    }
}
