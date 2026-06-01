<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\StudentProfile;
use Modules\PPUDS\Enums\StudentGender;
use Modules\PPUDS\Services\PpuApiService;
use Modules\PPUDS\Settings\GeneralSettings;

class Index extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table)
    {
        return $table
            ->query(fn () => StudentProfile::query()->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('Arabic Name'))
                    ->searchable()
                    ->url(fn (StudentProfile $record) => route('students.details', $record->user_id))
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('user.name_en')
                    ->label(__('English Name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.phone')
                    ->label(__('Phone'))
                    ->searchable()
                    ->sortable(),

            ])
            ->filters($this->getTableFilters())
            ->actions(
                $this->getTableActions()
            )
            // ->headerActions([

            //     Action::make('sync_student')
            //         ->label(__('Sync Student'))
            //         ->icon('heroicon-o-arrow-path')
            //         ->action(function (PpuApiService $service) {
            //             $status = $service->syncStudents(app(GeneralSettings::class)->year, app(GeneralSettings::class)->semester_type->value);
            //             if ($status) {
            //                 Toaster::success(__('Sync Major') . ' ' . ($status ? __('Success') : __('Failed')));
            //             }
            //         }),

            //     //                CreateAction::make('create')
            //     //                    ->label(__('Add Student'))
            //     //                    ->url(route('students.add'))
            //     //                    ->visible(fn() => auth()->user()->can('Student Create'))
            // ])
            ->bulkActions([]);
    }

    protected function getTableFilters(): array
    {
        return [
            Filter::make('user_email')
                ->form([
                    Forms\Components\TextInput::make('email')
                        ->label(__('Email')),
                ])
                ->query(function ($query, array $data) {
                    if (empty($data['email'])) {
                        return $query;
                    }

                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('email', 'like', '%'.$data['email'].'%');
                    });
                }),
            Filter::make('user_name')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name')),
                ])
                ->query(function ($query, array $data) {
                    return $query->whereHas('user', function ($q) use ($data) {
                        $q->where('name', 'like', '%'.$data['name'].'%');
                    });
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            ViewAction::make('view')
                ->label('')
                ->modalHeading(__('Student Details'))
                ->form(function ($record) {
                    return [
                        Grid::make(3)
                            ->schema([
                                Grid::make(1)
                                    ->columnSpan(2)
                                    ->schema([
                                        Section::make(__('Account Information'))
                                            ->icon('heroicon-o-user')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('name_ar')
                                                    ->label(__('Name (Arabic)'))
                                                    ->default($record->user->name ?? '-')
                                                    ->disabled(),

                                                TextInput::make('name_en')
                                                    ->label(__('Name (English)'))
                                                    ->default($record->user->name_en ?? '-')
                                                    ->disabled(),

                                                TextInput::make('email')
                                                    ->label(__('Email Address'))
                                                    ->default($record->user->email ?? '-')
                                                    ->columnSpanFull()
                                                    ->disabled(),
                                            ]),

                                        // القسم 2: المعلومات الأكاديمية (من جدول student_profiles)
                                        Section::make(__('Academic Information'))
                                            ->icon('heroicon-o-academic-cap')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('student_number')
                                                    ->label(__('Student Number'))
                                                    ->default($record->student_number)
                                                    ->disabled(),

                                                // عرض اسم التخصص بدلاً من الرقم
                                                TextInput::make('major_name')
                                                    ->label(__('Major'))
                                                    ->default($record->major->name ?? '-')
                                                    ->disabled(),

                                                TextInput::make('enrollment_year')
                                                    ->label(__('Enrollment Year'))
                                                    ->default($record->enrollment_year)
                                                    ->disabled(),

                                                TextInput::make('semester_level')
                                                    ->label(__('Semester Level'))
                                                    ->default($record->semester_level)
                                                    ->disabled(),
                                            ]),
                                    ]),

                                // العمود الثالث: البيانات الشخصية
                                Grid::make(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Section::make(__('Personal Details'))
                                            ->icon('heroicon-o-identification')
                                            ->schema([
                                                Forms\Components\DatePicker::make('dob')
                                                    ->label(__('Date of Birth'))
                                                    ->default($record->dob)
                                                    ->disabled(),

                                                Forms\Components\Select::make('gender')
                                                    ->label(__('Gender'))
                                                    ->options(collect(StudentGender::cases())->pluck('name', 'value'))
                                                    ->default($record->gender)
                                                    ->disabled(),

                                                TextInput::make('tawjihi_gpa')
                                                    ->label(__('Tawjihi GPA'))
                                                    ->default($record->tawjihi_gpa)
                                                    ->suffix('%')
                                                    ->disabled(),

                                                // عرض الأدوار
                                                TextInput::make('roles')
                                                    ->label(__('Roles'))
                                                    ->default($record->user->roles->pluck('name')->implode(', '))
                                                    ->disabled(),
                                            ]),

                                        Section::make(__('Social Links'))
                                            ->icon('heroicon-o-link')
                                            ->schema([
                                                TextInput::make('linkedin_url')
                                                    ->label(__('LinkedIn'))
                                                    ->default($record->linkedin_url)
                                                    ->disabled(),

                                                TextInput::make('behance_url')
                                                    ->label(__('Behance'))
                                                    ->default($record->behance_url)
                                                    ->disabled(),

                                                TextInput::make('github_url')
                                                    ->label(__('GitHub'))
                                                    ->default($record->github_url)
                                                    ->disabled(),
                                            ]),
                                    ]),
                            ]),
                    ];
                })
                ->modalSubmitAction(false)
                ->visible(fn () => auth()->user()->can('Student View')),

            Action::make('details')
                ->label('')
                ->icon('heroicon-o-user')
                ->url(fn ($record) => route('students.details', $record->user_id))
                ->visible(fn () => auth()->user()->can('Student Details List')),

            //            EditAction::make('edit')
            //                ->url(fn(StudentProfile $record) => route('students.edit', $record->user_id))
            //                ->visible(fn() => auth()->user()->can('Student Update')),
            DeleteAction::make('delete')
                ->visible(fn () => auth()->user()->can('Student Delete')),
        ];
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
            ],
        ]);
    }
}
