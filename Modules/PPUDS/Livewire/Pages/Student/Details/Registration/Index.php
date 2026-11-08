<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details\Registration;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\Core\Filament\Tables\Columns\UserColumn;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Entities\RegistrationSubject;
use Modules\PPUDS\Enums\SemesterType;

// ✅ تأكد من استدعاء مودل الكورس

class Index extends Component implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    public ?int $studentId = null;

    public function mount(?int $studentId = null)
    {
        $this->studentId = $studentId;
    }

    public function table(Table $table)
    {
        return $table
            ->query(fn() => $this->studentRegistrationQuery()->with(['student', 'course', 'supervisor']))
            ->columns([

                TextColumn::make('course.name')
                    ->label(__('Course'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('semester')
                    ->label(__('Term'))
                    ->formatStateUsing(function ($state, $record) {
                        return $this->semesterLabel($state) . ' - ' . $record->year;
                    })
                    ->icon('solar-calendar-date-linear'),

                UserColumn::make('supervisor.name')
                    ->label(__('Supervisor'))
                    ->user(fn ($record) => $record->supervisor)
                    ->linksToSupervisor()
                    ->toggleable(),

                TextColumn::make('semester')
                    ->label(__('Semester')),

                TextColumn::make('year')
                    ->label(__('Year')),

                TextColumn::make('created_at')
                    ->label(__('Registered At'))
                    ->dateTime('Y-m-d')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters($this->getTableFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([$this->subjectsTableAction()])
            ->bulkActions($this->getTableBulkAction());
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('course_id')
                ->label(__('Course'))
                ->options(fn(): array => Course::query()
                    ->whereIn('id', $this->studentRegistrationQuery()->select('course_id'))
                    ->get()
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->preload(),

            SelectFilter::make('semester')
                ->label(__('Semester'))
                ->options(SemesterType::class)
                ->native(false),

            Filter::make('year')
                ->form([
                    TextInput::make('year')->label(__('Year'))->numeric()
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        filled($data['year'] ?? null),
                        fn(Builder $query) => $query->where('year', $data['year'])
                    );
                }),

            SelectFilter::make('supervisor_id')
                ->label(__('Supervisor'))
                ->options(fn(): array => User::query()
                    ->whereIn('id', $this->studentRegistrationQuery()
                        ->whereNotNull('supervisor_id')
                        ->select('supervisor_id'))
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable(),
        ];
    }

    private function studentRegistrationQuery(): Builder
    {
        return Registration::query()
            ->when(
                $this->studentId,
                fn(Builder $query) => $query->where('student_id', $this->studentId),
                fn(Builder $query) => $query->whereRaw('1 = 0')
            );
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
                    ->action(fn(Collection $records) => $records->each->delete())
                    ->after(fn() => Toaster::success(__('Selected registrations deleted successfully')))
                    ->visible(fn() => auth()->user()->can('Registration Delete')),
            ])
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn() => auth()->user()->can('Registration Info')),
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
                                ->default(fn() => $this->semesterLabel($record->semester))
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
                        ])
                    ]);
                })
                ->modalSubmitAction(false)
                ->visible(fn() => auth()->user()->can('Registration View')),

            EditAction::make('edit')
                ->label('')
                ->tooltip(__('Edit'))
                ->url(fn(Registration $record) => route('registrations.edit', $record->id))
                ->visible(fn() => auth()->user()->can('Registration Update')),

            DeleteAction::make('delete')
                ->label('')
                ->tooltip(__('Delete'))
                ->action(function ($record) {
                    $record->delete();
                    Toaster::success(__('Registration deleted successfully'));
                })
                ->visible(fn() => auth()->user()->can('Registration Delete'))
        ];
    }

    /**
     * المواد الجامعية التي درسها الطالب لهذه المرحلة التدريبية (التسجيل)، تُدخل يدوياً.
     * مربوط وحده بالجدول لأن getTableActions غير مستخدمة لهذا الجدول.
     */
    protected function subjectsTableAction(): Action
    {
        return Action::make('subjects')
            ->label('')
            ->size('xl')
            ->color('info')
            ->icon('solar-book-2-bold-duotone')
            ->tooltip(__('University Subjects'))
            ->modalHeading(fn(Registration $record) => __('University Subjects') . ' - ' . ($record->course?->name ?? '-'))
            ->modalWidth('3xl')
            ->fillForm(fn(Registration $record) => [
                'subjects' => $record->subjects()
                    ->get(['id', 'name', 'code'])
                    ->map(fn(RegistrationSubject $subject) => $subject->only(['id', 'name', 'code']))
                    ->all(),
            ])
            ->form(fn() => [
                Repeater::make('subjects')
                    ->label(__('University Subjects'))
                    ->schema([
                        Hidden::make('id'),
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label(__('Subject Name'))
                                ->required()
                                ->maxLength(255)
                                // المادة المحفوظة لا تُعدَّل إلا بصلاحية التعديل.
                                ->disabled(fn(Get $get) => filled($get('id')) && ! auth()->user()->can('RegistrationSubject Update')),
                            TextInput::make('code')
                                ->label(__('Subject Code'))
                                ->maxLength(255)
                                ->disabled(fn(Get $get) => filled($get('id')) && ! auth()->user()->can('RegistrationSubject Update')),
                        ]),
                    ])
                    ->defaultItems(0)
                    ->addable(fn() => auth()->user()->can('RegistrationSubject Create'))
                    ->deletable(fn() => auth()->user()->can('RegistrationSubject Delete'))
                    ->reorderable(false)
                    ->addActionLabel(__('Add Subject')),
            ])
            ->modalSubmitAction(fn($action) => auth()->user()->canAny([
                'RegistrationSubject Create',
                'RegistrationSubject Update',
                'RegistrationSubject Delete',
            ]) ? $action : false)
            ->action(function (Registration $record, array $data) {
                $this->syncSubjects($record, $data['subjects'] ?? []);
                Toaster::success(__('Subjects saved successfully'));
            })
            ->visible(fn() => auth()->user()->can('RegistrationSubject View'));
    }

    /**
     * إخفاء أزرار الإضافة والحذف في الواجهة ليس حماية، لذلك تُفحص صلاحية كل عملية هنا.
     * المعرّفات تُطابق مواد هذا التسجيل فقط، فلا يمكن تعديل مادة تسجيل آخر.
     */
    private function syncSubjects(Registration $registration, array $items): void
    {
        $user = auth()->user();
        $existing = $registration->subjects()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $item) {
            $id = $item['id'] ?? null;

            if (filled($id)) {
                $subject = $existing->get((int) $id);

                if (! $subject) {
                    continue;
                }

                $keptIds[] = $subject->id;

                if ($user->can('RegistrationSubject Update') && array_key_exists('name', $item)) {
                    $subject->update([
                        'name' => $item['name'],
                        'code' => $item['code'] ?? null,
                    ]);
                }

                continue;
            }

            if ($user->can('RegistrationSubject Create') && filled($item['name'] ?? null)) {
                $registration->subjects()->create([
                    'name' => $item['name'],
                    'code' => $item['code'] ?? null,
                    'created_by' => $user->id,
                ]);
            }
        }

        if ($user->can('RegistrationSubject Delete')) {
            $existing->except($keptIds)->each->delete();
        }
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.registration.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Registrations List'), 'url' => route('registrations.index')],
            ]
        ]);
    }

    private function semesterLabel(SemesterType|int|string|null $semester): string
    {
        if ($semester instanceof SemesterType) {
            return $semester->getLabel() ?? (string) $semester->value;
        }

        if ($semester === null) {
            return '-';
        }

        $semester = (int) $semester;

        return SemesterType::tryFrom($semester)?->getLabel() ?? (__('Semester') . ' ' . $semester);
    }
}
