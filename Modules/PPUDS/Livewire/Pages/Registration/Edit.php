<?php

namespace Modules\PPUDS\Livewire\Pages\Registration;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
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
use Modules\PPUDS\Enums\Enums\SemesterType;

class Edit extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public $record; // متغير لحمل سجل التسجيل

    public function mount($registration)
    {
        $this->record = Registration::findOrFail($registration);

        $this->form->fill($this->record->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record) // ربط النموذج بالسجل الحالي
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // --- العمود الرئيسي (يسار) ---
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([
                                // 1. قسم بيانات الطالب والمساق
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
                                                ->options(User::whereHas('roles', fn($q) => $q->where('name', 'Student'))->pluck('name', 'id'))
                                                ->getSearchResultsUsing(fn (string $search) => User::whereHas('roles', fn($q) => $q->where('name', 'Student'))
                                                    ->where('name', 'like', "%{$search}%")
                                                    ->limit(50)
                                                    ->pluck('name', 'id')
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

                        // --- العمود الجانبي (يمين) ---
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
                                            // تمت إزالة disabled للسماح بتصحيح الخطأ، يمكنك إعادتها إذا أردت
                                            // ->disabled()
                                            ->prefixIcon('solar-calendar-mark-linear')
                                            ->options(SemesterType::options())
                                            ->native(false),

                                        TextInput::make('year')
                                            ->label(__('Academic Year'))
                                            ->required()
                                            ->numeric()
                                            // تمت إزالة disabled للسماح بتصحيح الخطأ
                                            // ->disabled()
                                            ->prefixIcon('solar-calendar-minimalistic-linear')
                                            ->minValue(2000)
                                            ->maxValue(date('Y') + 1),
                                    ]),

                                // 2. قسم المشرف الأكاديمي
                                Section::make(__('Supervision'))
                                    ->icon('solar-user-speak-rounded-bold-duotone')
                                    ->schema([
                                        Select::make('supervisor_id')
                                            ->label(__('Academic Supervisor'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->prefixIcon('solar-user-speak-rounded-linear')
                                            ->options(User::whereHas('roles', fn($q) => $q->where('name', 'Practical Training Supervisor'))->pluck('name', 'id')),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function messages(): array
    {
        return [
            'data.student_id.required' => __('Please select a student.'),
            'data.course_id.required' => __('Please select a course.'),
            'data.semester.required' => __('Please select the semester.'),
            'data.year.required' => __('Please enter the academic year.'),
            'data.supervisor_id.required' => __('Please assign an academic supervisor.'),
            'data.grade.numeric' => __('The grade must be a number.'),
        ];
    }

    public function save()
    {
        // $this->authorize("Registration Update");

        $this->validate();

        // عملية التحديث
        $this->record->update($this->data);

        Toaster::success(__('Registration updated successfully'));

        $this->redirect(route('registrations.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.registration.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Registrations'), 'url' => route('registrations.index')],
                ['title' => __('Edit Registration'), 'url' => '#'],
            ]
        ]);
    }
}
