<?php

namespace Modules\PPUDS\Livewire\Pages\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Maatwebsite\Excel\Excel as WriterType;
use Masmerise\Toaster\Toaster;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Interfaces\ExcelServiceInterface;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Exports\RegisteredStudentsWithoutCompanyExport;
use Modules\PPUDS\Exports\StudentCompaniesExport;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => StudentCompany::query()
                ->with(['student.media', 'student.studentProfile', 'registration.student.studentProfile', 'registration', 'registration.course', 'company', 'branch'])
                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query)))
            ->columns([
                TextColumn::make('registration.student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'registration.student.studentProfile',
                        fn (Builder $studentProfileQuery): Builder => $studentProfileQuery->where('student_number', 'like', "%{$search}%")
                    ))
                    ->placeholder('---'),

                TextColumn::make('registration.student.name')
                    ->label(__('Student'))
                    ->getStateUsing(fn (StudentCompany $record): HtmlString|string => $this->studentDisplayColumnState($record))
                    ->html()
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->url(fn (StudentCompany $record): ?string => $this->studentDetailsUrl($record)),

                TextColumn::make('registration.student.phone')
                    ->label(__('Phone'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'registration.student',
                        fn (Builder $studentQuery): Builder => $studentQuery->where('phone', 'like', "%{$search}%")
                    ))
                    ->placeholder('---'),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->url(fn (StudentCompany $record): ?string => $record->company_id && auth()->user()->can('Company Details List') ? route('companies.details', $record->company_id) : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('—')
                    ->color('primary'),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('registration.course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('registration.year')
                    ->label(__('Year'))
                    ->toggleable(),

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->icon('solar-clock-circle-bold-duotone')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->actions($this->getTableActions())
            ->headerActions([
                Action::make('export_student_companies')
                    ->label(__('Export Student Companies'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new StudentCompaniesExport($this->getTableQueryForExport()),
                        $this->exportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('StudentCompany View List')),

                Action::make('export_registered_students_without_company')
                    ->label(__('Export Registered Students Without Company'))
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->action(fn () => app(ExcelServiceInterface::class)->download(
                        new RegisteredStudentsWithoutCompanyExport($this->registeredStudentsWithoutCompanyQuery()),
                        $this->registeredStudentsWithoutCompanyExportFilename(),
                        WriterType::XLSX
                    ))
                    ->visible(fn () => auth()->user()->can('StudentCompany View List') && ! $this->shouldScopeCompanySupervisorStudentCompanies()),

                Action::make('importPlacements')
                    ->label(__('Import Placements'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(route('student-companies.import'))
                    ->visible(fn () => auth()->user()->can('StudentCompany Create')),

                \Modules\Core\Filament\Forms\Components\CreateAction::make('create')
                    ->label(__('Add Student Company'))
                    ->url(route('student-companies.add'))
                    ->visible(fn () => auth()->user()->can('StudentCompany Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function studentDisplayColumnState(StudentCompany $record): HtmlString|string
    {
        return $record->student?->user_display_html
            ?? $record->registration?->student?->user_display_html
            ?? '---';
    }

    protected function studentDetailsUrl(StudentCompany $record): ?string
    {
        $student = $record->student ?? $record->registration?->student;

        if (! $student || ! auth()->user()?->can('Student Details List')) {
            return null;
        }

        return route('students.details', $student->id);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('student_number')
                        ->label(__('Student Number'))
                        ->prefixIcon('solar-user-id-bold-duotone')
                        ->live(debounce: 500),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['student_number'],
                        fn (Builder $query, string $studentNumber) => $query->whereHas(
                            'registration.student.studentProfile',
                            fn (Builder $studentProfileQuery) => $studentProfileQuery->where('student_number', 'like', "%{$studentNumber}%")
                        )
                    );
                }),

            // فلتر الحالة
            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::class)
                ->native(false),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            SelectFilter::make('course')
                ->label(__('Course'))
                ->options(Course::get()->pluck('name', 'id'))
                ->query(function (Builder $query, array $data) {
                    return $query->when($data['value'], function ($q, $courseId) {
                        $q->whereHas('registration', fn ($regQ) => $regQ->where('course_id', $courseId));
                    });
                })
                ->searchable(),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->prefixIcon('solar-calendar-search-bold-duotone')
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $q, $year) => $q->whereHas('registration', fn ($regQ) => $regQ->where('year', $year))
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
                        $data['semester_type'],
                        fn (Builder $q, $semester_type) => $q->whereHas('registration', fn ($regQ) => $regQ->where('semester', $semester_type))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('StudentCompany Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (StudentCompany $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->registration?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('status')
                            ->label(__('Status'))
                            ->default($record->status?->getLabel())
                            ->disabled()
                            ->prefixIcon('solar-flag-bold-duotone'),

                        TextInput::make('course_name')
                            ->label(__('Course'))
                            ->default($record->registration?->course?->name)
                            ->disabled()
                            ->prefixIcon('solar-book-bold-duotone'),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('StudentCompany View')), // تأكد من اسم الصلاحية

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (StudentCompany $record) => route('student-companies.edit', $record->id)) // تأكد من اسم الراوت
                ->visible(fn () => auth()->user()->can('StudentCompany Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Student company record deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('StudentCompany Delete')),
        ];
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold-duotone')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected records deleted successfully')))
                    ->visible(fn () => auth()->user()->can('StudentCompany Delete')),
            ]),
        ];
    }

    protected function registeredStudentsWithoutCompanyQuery(): Builder
    {
        return Registration::query()
            ->where(fn (Builder $query) => $this->applyCurrentSemester($query))
            ->whereNotIn(
                'id',
                StudentCompany::query()
                    ->whereNotNull('company_id')
                    ->select('registration_id')
            );
    }

    protected function applyCurrentSemester(Builder $query): void
    {
        $settings = app(GeneralSettings::class);

        $query
            ->where('semester', $settings->semester_type->value)
            ->where('year', $settings->year);

        if ($this->shouldScopeToSupervisor()) {
            $query->where('supervisor_id', auth()->id());
        }
    }

    protected function shouldScopeToSupervisor(): bool
    {
        return auth()->user()?->hasAnyRole([
            UserRole::PRACTICAL_TRAINING_SUPERVISOR->value,
            'Academic Supervisor',
            'University Supervisor',
        ]) && ! auth()->user()?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ADMIN->value,
        ]);
    }

    protected function registeredStudentsWithoutCompanyExportFilename(): string
    {
        return 'registered-students-without-company-'.now()->format('Y-m-d-His').'.xlsx';
    }

    protected function exportFilename(): string
    {
        return 'student-companies-'.now()->format('Y-m-d-His').'.xlsx';
    }

    public function render()
    {
        // تأكد من إنشاء ملف الـ Blade هذا
        return view('ppuds::livewire.pages.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Student Companies'), 'url' => route('student-companies.index')],
            ],
        ]);
    }
}
