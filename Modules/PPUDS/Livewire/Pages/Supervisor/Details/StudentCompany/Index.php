<?php

namespace Modules\PPUDS\Livewire\Pages\Supervisor\Details\StudentCompany;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\TrainingStatus;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public ?int $supervisorId = null;

    public function mount(?int $supervisorId = null)
    {
        $this->supervisorId = $supervisorId;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->supervisedStudentCompaniesQuery()
                ->with([
                    'student.studentProfile',
                    'registration.course',
                    'company',
                    'branch',
                    'department',
                ])
            )
            ->columns([
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn (StudentCompany $record) => $record->student?->studentProfile?->student_number)
                    ->url(fn (StudentCompany $record) => auth()->user()->can('Student Details List') ? route('students.details', $record->student_id) : null),

                TextColumn::make('company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('—')
                    ->color('primary')
                    ->url(fn (StudentCompany $record) => auth()->user()->can('Company Details List') ? route('companies.details', $record->company_id) : null),

                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('department.name')
                    ->label(__('Department'))
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

                TextColumn::make('registration.semester')
                    ->label(__('Semester'))
                    ->toggleable(),

                TextColumn::make('registration.year')
                    ->label(__('Year')),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->actions($this->getTableActions())
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('status')
                ->label(__('Training Status'))
                ->options(TrainingStatus::class)
                ->native(false),

            SelectFilter::make('company_id')
                ->label(__('Company'))
                ->options(Company::with('translations')->get()->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            SelectFilter::make('course')
                ->label(__('Course'))
                ->options(Course::get()->pluck('name', 'id'))
                ->query(function (Builder $query, array $data) {
                    return $query->when($data['value'], function ($query, $courseId) {
                        $query->whereHas('registration', fn ($query) => $query->where('course_id', $courseId));
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
                        fn (Builder $query, $year) => $query->whereHas('registration', fn ($query) => $query->where('year', $year))
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
                        fn (Builder $query, $semesterType) => $query->whereHas('registration', fn ($query) => $query->where('semester', $semesterType))
                    );
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(fn (StudentCompany $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->student?->name)
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
                ->visible(fn () => auth()->user()->can('StudentCompany View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (StudentCompany $record) => route('student-companies.edit', $record->id))
                ->visible(fn () => auth()->user()->can('StudentCompany Update')),
        ];
    }

    protected function supervisedStudentCompaniesQuery(): Builder
    {
        return StudentCompany::query()
            ->when(
                $this->supervisorId,
                fn (Builder $query) => $query->whereHas('registration', fn (Builder $query) => $query->where('supervisor_id', $this->supervisorId)),
                fn (Builder $query) => $query->whereRaw('1 = 0')
            );
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor.details.student-company.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Supervised Students'), 'url' => route('supervisors.details', $this->supervisorId)],
            ],
        ]);
    }
}
