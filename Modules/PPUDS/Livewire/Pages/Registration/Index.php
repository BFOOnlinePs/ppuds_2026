<?php

namespace Modules\PPUDS\Livewire\Pages\Registration;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table)
    {
        return $table
            ->query(
                fn () => Registration::query()
                    ->with(['student', 'course', 'supervisor'])
                    ->when(auth()->user()->hasRole(UserRole::STUDENT->value), function ($query) {
                        $query->where('student_id', auth()->user()->id);
                    })
            )
            ->columns([
                // 1. عمود الطالب
                TextColumn::make('student.name')
                    ->label(__('Student'))
                    ->searchable()
                    ->sortable()
                    ->icon('solar-user-id-bold-duotone')
                    ->weight('bold'),

                // 2. عمود المساق
                TextColumn::make('course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('info'),
                // ❌ تم إزالة searchable و sortable لأن العمود غير موجود في الداتابيس (مترجم)

                // 3. عمود الفصل والسنة
                TextColumn::make('semester')
                    ->label(__('Term'))
                    ->icon('solar-calendar-date-linear'),
                // تم إزالة sortable لتجنب المشاكل حالياً

                // 4. عمود المشرف
                SelectColumn::make('supervisor_id')
                    ->label(__('Supervisor'))
                    ->options(User::whereHas('roles', fn ($q) => $q->where('name', UserRole::PRACTICAL_TRAINING_SUPERVISOR->value))->pluck('name', 'id'))
                    ->toggleable(),

                // 5. عمود العلامة
                //                TextColumn::make('grade')
                //                    ->label(__('Grade'))
                //                    ->placeholder('-')
                //                    ->badge()
                //                    ->color(fn ($state) => $state >= 60 ? 'success' : ($state === null ? 'gray' : 'danger'))
                //                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Registered At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions($this->getTableActions())
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('Add Registration'))
                    ->url(route('registrations.add'))
                    ->visible(fn () => auth()->user()->can('Registration Create')),
            ])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('course_id')
                ->label(__('Course'))
                ->options(Course::get()->pluck('name', 'id'))
                ->searchable()
                ->preload(),

            SelectFilter::make('semester')
                ->label(__('Semester'))
                ->options(fn (): array => $this->getRegistrationSemesterOptions())
                ->default(app(GeneralSettings::class)->semester_type?->value)
                ->native(false),

            Filter::make('year')
                ->label(__('Academic Year'))
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        filled($data['year'] ?? null),
                        fn (Builder $query) => $query->where('year', (int) $data['year'])
                    );
                }),

            // 4. فلتر المشرف
            SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(User::whereHas('roles', fn ($q) => $q->where('name', 'Practical Training Supervisor'))->pluck('name', 'id'))
                ->searchable(),
        ];
    }

    private function getRegistrationFilterOptionsQuery(): Builder
    {
        return Registration::query()
            ->when(auth()->user()?->hasRole(UserRole::STUDENT->value), function (Builder $query) {
                $query->where('student_id', auth()->id());
            });
    }

    private function getRegistrationSemesterOptions(): array
    {
        $options = $this->getRegistrationFilterOptionsQuery()
            ->whereNotNull('semester')
            ->distinct()
            ->orderBy('semester')
            ->pluck('semester')
            ->mapWithKeys(function ($semester): array {
                $semester = $this->semesterValue($semester);

                return [
                    $semester => $this->semesterLabel($semester),
                ];
            })
            ->toArray();

        $currentSemester = $this->semesterValue(app(GeneralSettings::class)->semester_type);

        if ($currentSemester !== null) {
            $options[$currentSemester] = $this->semesterLabel($currentSemester);
        }

        ksort($options);

        return $options ?: SemesterType::options();
    }

    private function getRegistrationYearOptions(): array
    {
        $options = $this->getRegistrationFilterOptionsQuery()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();

        $currentYear = app(GeneralSettings::class)->year;

        if ($currentYear !== null) {
            $options[(string) $currentYear] = (string) $currentYear;
        }

        krsort($options, SORT_NATURAL);

        return $options;
    }

    public function getTableBulkAction(): array
    {
        return [
            BulkActionGroup::make([
                BulkAction::make('delete')
                    ->label(__('Delete Selected'))
                    ->icon('solar-trash-bin-trash-bold')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->after(fn () => Toaster::success(__('Selected registrations deleted successfully'))),
            ]),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Registration Info')),
            ViewAction::make('view')
                ->label('')
                ->tooltip(__('View Details'))
                ->form(function (Forms\Form $form, $record) {
                    return $form->schema([
                        Grid::make(2)->schema([
                            TextInput::make('student.name')
                                ->label(__('Student'))
                                ->default($record->student->name ?? '-')
                                ->disabled(),
                            TextInput::make('course.name')
                                ->label(__('Course'))
                                ->default($record->course->name ?? '-')
                                ->disabled(),
                            TextInput::make('semester')
                                ->label(__('Semester'))
                                ->default(fn () => $this->semesterLabel($record->semester))
                                ->disabled(),
                            TextInput::make('year')
                                ->label(__('Year'))
                                ->default($record->year)
                                ->disabled(),
                            TextInput::make('university_score')
                                ->label(__('University Score'))
                                ->default($record->university_score)
                                ->disabled(),
                            TextInput::make('company_score')
                                ->label(__('Company Score'))
                                ->default($record->company_score)
                                ->disabled(),
                            TextInput::make('grade')
                                ->label(__('Final Grade'))
                                ->default($record->grade)
                                ->disabled()
                                ->extraInputAttributes(['class' => 'text-primary-600 font-bold']),
                        ]),
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Registration View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn (Registration $record) => route('registrations.edit', $record->id))
                ->visible(fn () => auth()->user()->can('Registration Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Registration deleted successfully'));
                })
                ->visible(fn () => auth()->user()->can('Registration Delete')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.registration.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Registrations List'), 'url' => route('registrations.index')],
            ],
        ]);
    }

    private function semesterValue(SemesterType|int|string|null $semester): ?int
    {
        if ($semester instanceof SemesterType) {
            return $semester->value;
        }

        return $semester === null ? null : (int) $semester;
    }

    private function semesterLabel(SemesterType|int|string|null $semester): string
    {
        if ($semester instanceof SemesterType) {
            return $semester->getLabel() ?? (string) $semester->value;
        }

        $semesterValue = $this->semesterValue($semester);

        return $semesterValue === null
            ? '-'
            : (SemesterType::tryFrom($semesterValue)?->getLabel() ?? (string) $semesterValue);
    }
}
