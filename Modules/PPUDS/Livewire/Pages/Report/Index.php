<?php

namespace Modules\PPUDS\Livewire\Pages\Report;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
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
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\Note;
use Modules\PPUDS\Entities\StudentCompany;
use Modules\PPUDS\Enums\AttendanceStatus;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Settings\GeneralSettings;
use Modules\PPUDS\Support\ScopesStudentCompanyVisibility;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use ScopesStudentCompanyVisibility;

    public function table(Table $table)
    {
        $mainTable = (new StudentCompany)->getTable();

        return $table
            ->query(fn () => StudentCompany::query()->with(['registration', 'student', 'student.studentProfile', 'company', 'branch', 'branch.workingHours', 'department', 'attendances'])
                ->withAttendanceDays()
                ->withActualWorkingHours()
                ->tap(fn (Builder $query) => $this->applyStudentCompanyVisibilityScope($query)))

            ->columns([
                TextColumn::make('student.studentProfile.student_number')
                    ->label(__('Student Number'))
                    ->weight('bold'),

                TextColumn::make('student.name')
                    ->label(__('Student Name'))
                    ->summarize(Count::make('student.name')),

                TextColumn::make('student.studentProfile.gender')
                    ->label(__('Gender')),

                TextColumn::make('company.name')
                    ->label(__('Company')),

                TextColumn::make('attendance_days')
                    ->label(__('Attendance Days'))
                    ->summarize(Sum::make('attendance_days')),

                TextColumn::make('branch.required_training_days')
                    ->label(__('Required Training Days (Until Training End)'))
                    ->wrapHeader()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('branch.attended_training_days')
                    ->label(__('Attended Days (Until Today)'))
                    ->wrapHeader()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('actual_working_hours')
                    ->label(__('Actual Working Hours'))
                    ->summarize(Sum::make('actual_working_hours'))
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ->filtersFormColumns(3)
            ->actions($this->getTableActions())
            ->headerActions([
                // CreateAction::make('create')
                //     ->label(__('Add Note'))
                //     ->url(route('notes.add'))
                //     ->visible(fn() => auth()->user()->can('Note Create'))
            ])
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            // 1. فلتر رقم الطالب (أو اسمه)
            Filter::make('student_number')
                ->label(__('Student Number'))
                ->form([
                    TextInput::make('number')
                        ->label(__('Number / Name')) // تم تعديل الاسم ليوضح أنه يبحث بالاثنين
                        ->live(debounce: 500), // لجعل البحث سريعاً ومباشراً
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['number'])) {
                        $query->where(function (Builder $q) use ($data) {
                            $q->whereHas('student.studentProfile', fn ($sq) => $sq->where('student_number', 'like', "%{$data['number']}%"))
                                ->orWhereHas('student', fn ($sq) => $sq->where('name', 'like', "%{$data['number']}%"));
                        });
                    }
                }),

            // 2. فلتر الجنس (استخدام SelectFilter أنظف وأسرع)
            SelectFilter::make('student_gender')
                ->label(__('Gender'))
                ->options(StudentGender::options())
                ->searchable()
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['value'])) { // في SelectFilter نستخدم 'value'
                        $query->whereHas('student.studentProfile', fn ($q) => $q->where('gender', $data['value']));
                    }
                }),

            // 3. فلتر الشركة (تعديل مهم جداً للأداء)
            Filter::make('company_name')
                ->label(__('Company Name'))
                ->form([
                    Select::make('company')
                        ->label(__('Company'))
                        ->options(fn (): array => $this->applyCompanyVisibilityScope(Company::query())
                            ->get()
                            ->pluck('name', 'id')
                            ->toArray())
                        ->searchable(),
                ])
                ->query(function (Builder $query, array $data) {
                    if (! empty($data['company'])) {
                        $query->whereHas('company', fn ($q) => $q->where('id', $data['company']));
                    }
                }),

            Filter::make('attendance_days')
                ->form([
                    TextInput::make('from_days')
                        ->label(__('Attendance Days From'))
                        ->numeric()
                        ->live(debounce: 500),
                    TextInput::make('to_days')
                        ->label(__('To Attendance Days'))
                        ->numeric()
                        ->live(debounce: 500),
                ])
                ->columns(2)
                ->query(function (Builder $query, array $data): Builder {
                    $from = is_numeric($data['from_days'] ?? null) ? (int) $data['from_days'] : null;
                    $to = is_numeric($data['to_days'] ?? null) ? (int) $data['to_days'] : null;

                    if ($from === null && $to === null) {
                        return $query;
                    }

                    return $query
                        ->when($from !== null, fn ($q) => $q->whereHas('attendances', function (Builder $subQ) {
                            $subQ->where('status', AttendanceStatus::UNDETERMINED);
                        }, '>=', $from)
                        )
                        ->when($to !== null, fn ($q) => $q->whereHas('attendances', function (Builder $subQ) {
                            $subQ->where('status', AttendanceStatus::UNDETERMINED);
                        }, '<=', $to)
                        );
                }),

            Filter::make('year')
                ->form([
                    TextInput::make('year')
                        ->label(__('Academic Year'))
                        ->numeric()
                        ->default(app(GeneralSettings::class)->year)
                        ->live(debounce: 500)
                        ->placeholder(date('Y')),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['year'] ?? null,
                        fn (Builder $q, $year) => $q->whereHas('registration', fn ($regQ) => $regQ->where('year', $year))
                    );
                }),

            // 6. فلتر نوع الفصل
            Filter::make('semester_type')
                ->form([
                    Select::make('semester_type')
                        ->label(__('Semester Type'))
                        ->options(SemesterType::options())
                        ->default(app(GeneralSettings::class)->semester_type->value),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['semester_type'] ?? null, // الحماية من الأخطاء
                        fn (Builder $q, $semester_type) => $q->whereHas('registration', fn ($regQ) => $regQ->where('semester', $semester_type))
                    );
                }),
        ];
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
                    ->after(fn () => Toaster::success(__('Selected notes deleted successfully')))
                    ->visible(fn () => auth()->user()->can('Note Delete')),
            ]),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            InfoAction::make('info')
                ->label('')
                ->visible(fn () => auth()->user()->can('Registration Info')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.note.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('My Notes'), 'url' => route('notes.index')],
            ],
        ]);
    }
}
