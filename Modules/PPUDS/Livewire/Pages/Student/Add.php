<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentProfile;

class Add extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                Section::make(__('Account Information'))
                                    ->description(__('Manage user login details'))
                                    ->icon('heroicon-o-user')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('Name (Arabic)')) // أو Name فقط
                                            ->required()
                                            ->maxLength(255),

                                        // الاسم بالانجليزي (الجديد)
                                        TextInput::make('name_en')
                                            ->label(__('Name (English)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label(__('Email Address'))
                                            ->email()
                                            ->required()
                                            ->unique('users', 'email'),

                                        TextInput::make('phone')
                                            ->label(__('Phone'))
                                            ->numeric()
                                            ->unique('users', 'phone')
                                            ->required(),

                                        TextInput::make('password')
                                            ->label(__('Password'))
                                            ->password()
                                            ->revealable()
                                            ->required()
                                            ->confirmed(),

                                        TextInput::make('password_confirmation')
                                            ->label(__('Confirm Password'))
                                            ->password()
                                            ->revealable()
                                            ->required(),
                                    ]),

                                Section::make(__('Academic Information'))
                                    ->description(__('Student university details'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('student_number')
                                            ->label(__('Student Number'))
                                            ->required()
                                            ->unique(config('ppuds.table_prefix').'student_profiles', 'student_number')
                                            ->numeric(),

                                        Select::make('major_id')
                                            ->label(__('Major'))
                                            ->options(function () {
                                                return Major::get()->pluck('name', 'id');
                                            })
                                            ->createOptionForm([
                                                TextInput::make('reference_code')
                                                    ->label(__('Reference code'))
                                                    ->required(),

                                                TextInput::make('name')
                                                    ->label(__('Name'))
                                                    ->required(),

                                                Textarea::make('description')
                                                    ->label(__('Description')),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $data['created_by'] = auth()->id();

                                                return Major::create($data)->getKey();
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        TextInput::make('enrollment_year')
                                            ->label(__('Enrollment Year'))
                                            ->numeric()
                                            ->minValue(2000)
                                            ->maxValue(date('Y') + 1)
                                            ->default(date('Y')),

                                        Select::make('semester_level')
                                            ->label(__('Semester Level'))
                                            ->options(array_combine(range(1, 10), range(1, 10))) // قائمة من 1 إلى 10
                                            ->nullable(),
                                    ]),
                            ]),

                        Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('Personal Details'))
                                    ->icon('heroicon-o-identification')
                                    ->schema([
                                        DatePicker::make('dob')
                                            ->label(__('Date of Birth'))
                                            ->maxDate(now()),

                                        Select::make('gender')
                                            ->label(__('Gender'))
                                            ->options([
                                                'male' => __('Male'),
                                                'female' => __('Female'),
                                            ]),

                                        TextInput::make('tawjihi_gpa')
                                            ->label(__('Tawjihi GPA'))
                                            ->numeric()
                                            ->maxValue(100)
                                            ->suffix('%'),
                                    ]),

                                Section::make(__('Social Links'))
                                    ->icon('heroicon-o-link')
                                    ->schema([
                                        TextInput::make('linkedin_url')
                                            ->label(__('LinkedIn'))
                                            ->url()
                                            ->maxLength(255),

                                        TextInput::make('behance_url')
                                            ->label(__('Behance'))
                                            ->url()
                                            ->maxLength(255),

                                        TextInput::make('github_url')
                                            ->label(__('GitHub'))
                                            ->url()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {

            $userData = [
                'name' => $this->data['name'],
                'email' => $this->data['email'],
                'phone' => $this->data['phone'],
                'password' => Hash::make($this->data['password']),
            ];

            $user = User::create($userData);
            $user->generateAvatar();

            $user->assignRole(UserRole::STUDENT->value);

            $profileData = collect($this->data)
                ->except(['name', 'email', 'password', 'password_confirmation', 'roles'])
                ->toArray();

            StudentProfile::create(array_merge($profileData, [
                'user_id' => $user->id,
                'cv_status' => 1,
            ]));

            // Notification::make()->title('Saved successfully')->success()->send();
        });

        // إعادة التوجيه بعد الحفظ
        return redirect()->route('students.index');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Add Student'), 'url' => route('students.add')],
            ],
        ]);
    }
}
