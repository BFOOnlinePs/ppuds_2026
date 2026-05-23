<?php

namespace Modules\PPUDS\Livewire\Pages\Registration;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Course;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\SemesterType;
use Modules\PPUDS\Settings\GeneralSettings;

class Add extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(Registration::class)
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                Section::make(__('Academic Information'))
                                    ->icon('solar-book-bookmark-bold-duotone')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('student_id')
                                                ->label(__('Student'))
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon('solar-user-id-linear')
                                                ->options(fn () => User::with('studentProfile')
                                                    ->whereHas('roles', fn($q) => $q->where('name', 'Student'))
                                                    ->get()
                                                    ->mapWithKeys(fn (User $student) => [
                                                        $student->id => $this->formatStudentOption($student),
                                                    ]))
                                                ->getSearchResultsUsing(
                                                    fn(string $search) => User::with('studentProfile')
                                                        ->whereHas('roles', fn($q) => $q->where('name', 'Student'))
                                                        ->where(function ($query) use ($search) {
                                                            $query->where('name', 'like', "%{$search}%")
                                                                ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery->where('student_number', 'like', "%{$search}%"));
                                                        })
                                                        ->limit(10)
                                                        ->get()
                                                        ->mapWithKeys(fn (User $student) => [
                                                            $student->id => $this->formatStudentOption($student),
                                                        ])
                                                ),

                                            Select::make('course_id')
                                                ->label(__('Course'))
                                                ->required()
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon('solar-notebook-linear')
                                                ->options(Course::get()->pluck('name', 'id')),
                                        ]),
                                    ]),
                            ]),

                        // --- العمود الجانبي (يمين - يأخذ مساحة 1) ---
                        Group::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                // 1. قسم الفترة الزمنية
                                Section::make(__('Term Details'))
                                    ->icon('solar-calendar-date-bold-duotone')
                                    ->schema([
                                        Select::make('semester')
                                            ->label(__('Semester'))
                                            ->required()
                                            ->disabled()
                                            ->default(fn(GeneralSettings $settings) =>  $settings->semester_type->value)
                                            ->prefixIcon('solar-calendar-mark-linear')
                                            ->options(SemesterType::options())
                                            ->native(false),

                                        TextInput::make('year')
                                            ->label(__('Academic Year'))
                                            ->required()
                                            ->numeric()
                                            ->disabled()
                                            ->prefixIcon('solar-calendar-minimalistic-linear')
                                            ->default(fn(GeneralSettings $settings) =>  $settings->year)
                                            ->minValue(2000)
                                            ->maxValue(date('Y') + 1),
                                    ]),

                                // 2. قسم المشرف الأكاديمي
                                Section::make(__('Supervision'))
                                    ->icon('solar-user-speak-rounded-bold-duotone')
                                    ->schema([
                                        Select::make('supervisor_id')
                                            ->label(__('Academic Supervisor'))
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('solar-user-speak-rounded-linear')
                                            // جلب المشرفين فقط
                                            ->options(User::whereHas('roles', fn($q) => $q->where('name', 'Practical Training Supervisor'))->pluck('name', 'id')),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function formatStudentOption(User $student): string
    {
        $studentNumber = $student->studentProfile?->student_number;

        return $studentNumber
            ? "{$studentNumber} - {$student->name}"
            : $student->name;
    }

    protected function messages(): array
    {
        return [
            'data.student_id.required' => __('Please select a student.'),
            'data.course_id.required' => __('Please select a course.'),
            'data.semester.required' => __('Please select the semester.'),
            'data.year.required' => __('Please enter the academic year.'),
            'data.grade.numeric' => __('The grade must be a number.'),
        ];
    }

    public function save()
    {
        // $this->authorize("Registration Create");

        $this->validate();

        $registrationData = $this->data;
        $registrationData['created_by'] = auth()->id();

        Registration::create($registrationData);

        Toaster::success(__('Registration created successfully'));

        $this->redirect(route('registrations.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.registration.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Registrations'), 'url' => route('registrations.index')],
                ['title' => __('New Registration'), 'url' => '#'],
            ]
        ]);
    }
}
