<?php

namespace Modules\PPUDS\Livewire\Pages\Supervisor\Details\FieldVisit;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\FieldVisit;
use Modules\PPUDS\Enums\SemesterType;
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
            ->query(fn () => FieldVisit::query()
                ->where('supervisor_id', $this->supervisorId)
                ->with([
                    'studentCompany.student',
                    'studentCompany.registration.course',
                    'studentCompany.company',
                    'studentCompany.branch',
                    'supervisor',
                ])
            )
            ->columns([
                UserColumn::make('studentCompany.student.name')
                    ->label(__('Student'))
                    ->user(fn (FieldVisit $record) => $record->studentCompany?->student)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('studentCompany.company.name')
                    ->label(__('Company'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'studentCompany.company.translations',
                        fn (Builder $query) => $query->where('name', 'like', "%{$search}%")
                    ))
                    ->placeholder('—')
                    ->color('primary')
                    ->url(fn (FieldVisit $record) => auth()->user()->can('Company Details List') && $record->studentCompany?->company_id
                        ? route('companies.details', $record->studentCompany->company_id)
                        : null
                    ),

                TextColumn::make('visiting_place')
                    ->label(__('Visiting Place'))
                    ->searchable()
                    ->limit(30)
                    ->icon('solar-map-point-bold-duotone'),

                TextColumn::make('visit_date')
                    ->label(__('Visit Date'))
                    ->date('Y-m-d')
                    ->sortable()
                    ->icon('solar-calendar-date-bold-duotone'),

                TextColumn::make('visit_time')
                    ->label(__('Visit Time'))
                    ->time('H:i')
                    ->icon('solar-clock-circle-bold-duotone'),

                TextColumn::make('visit_duration')
                    ->label(__('Duration (Mins)'))
                    ->numeric()
                    ->sortable()
                    ->suffix(' '.__('Mins')),

                TextColumn::make('studentCompany.registration.course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions($this->getTableActions())
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('company')
                ->label(__('Company'))
                ->options(Company::with('translations')->get()->pluck('name', 'id'))
                ->query(function (Builder $query, array $data) {
                    return $query->when($data['value'], function ($query, $companyId) {
                        $query->whereHas('studentCompany', fn ($query) => $query->where('company_id', $companyId));
                    });
                })
                ->searchable()
                ->preload(),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->placeholder(date('Y'))
                        ->prefixIcon('solar-calendar-search-bold-duotone'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'],
                        fn (Builder $query, $year) => $query->whereHas('studentCompany.registration', fn ($query) => $query->where('year', $year))
                    );
                }),

            Filter::make('semester_type')
                ->form([
                    \Filament\Forms\Components\Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value)
                        ->prefixIcon('solar-bookmark-circle-bold-duotone'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'],
                        fn (Builder $query, $semesterType) => $query->whereHas('studentCompany.registration', fn ($query) => $query->where('semester', $semesterType))
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
                ->form(fn (FieldVisit $record) => [
                    Grid::make(2)->schema([
                        TextInput::make('student_name')
                            ->label(__('Student'))
                            ->default($record->studentCompany?->student?->name)
                            ->disabled()
                            ->prefixIcon('solar-user-id-bold-duotone'),

                        TextInput::make('company_name')
                            ->label(__('Company'))
                            ->default($record->studentCompany?->company?->name)
                            ->disabled()
                            ->prefixIcon('solar-city-bold-duotone'),

                        TextInput::make('visiting_place')
                            ->label(__('Visiting Place'))
                            ->default($record->visiting_place)
                            ->disabled()
                            ->prefixIcon('solar-map-point-bold-duotone'),

                        DatePicker::make('visit_date')
                            ->label(__('Visit Date'))
                            ->default($record->visit_date)
                            ->disabled()
                            ->prefixIcon('solar-calendar-date-bold-duotone'),

                        TimePicker::make('visit_time')
                            ->label(__('Visit Time'))
                            ->default($record->visit_time)
                            ->disabled()
                            ->prefixIcon('solar-clock-circle-bold-duotone'),

                        TextInput::make('visit_duration')
                            ->label(__('Duration'))
                            ->default($record->visit_duration)
                            ->disabled()
                            ->suffix(__('Minutes')),

                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->default($record->notes)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
                ])
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('FieldVisit View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (FieldVisit $record) => route('field-visits.edit', $record->id))
                ->visible(fn () => auth()->user()->can('FieldVisit Update')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.supervisor.details.field-visit.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Field Visits By Supervisor'), 'url' => route('supervisors.details', $this->supervisorId)],
            ],
        ]);
    }
}
