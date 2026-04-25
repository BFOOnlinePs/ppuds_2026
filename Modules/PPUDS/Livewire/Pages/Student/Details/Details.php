<?php

namespace Modules\PPUDS\Livewire\Pages\Student\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Component; // استيراد ضروري
use Modules\Core\Entities\User;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];

    public int $userId;

    public function mount($user)
    {
        $this->userId = is_object($user) ? $user->id : (int) $user;

        $this->form->fill($this->userRecord->toArray());
    }

    #[Computed]
    public function userRecord()
    {
        return User::with([
            'studentProfile',
            'studentProfile.media',
            'roles',
            'media',
        ])->findOrFail($this->userId);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->userRecord)
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->getStateUsing(fn ($record) => $record->getAvatarUrlAttribute()),

                        TextEntry::make('name'),

                        TextEntry::make('email'),

                        TextEntry::make('student_profile.student_number'),
                    ]),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->userRecord)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Tabs::make('tabs')
                            ->tabs([

                                Tabs\Tab::make('Personal Information')
                                    ->icon('heroicon-o-user')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Grid::make(1)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label(__('Name'))
                                                            ->disabled()
                                                            ->required(),

                                                        TextInput::make('email')
                                                            ->label(__('Email'))
                                                            ->email()
                                                            ->disabled()
                                                            ->unique(ignoreRecord: true, ignorable: $this->userRecord) // استخدم الدالة المحسوبة هنا
                                                            ->required(),

                                                        TextInput::make('password')
                                                            ->label(__('Password'))
                                                            ->password()
                                                            ->dehydrated(fn ($state) => filled($state))
                                                            ->required(fn (string $context): bool => $context === 'create'),

                                                        Grid::make(2)
                                                            ->schema([
                                                                TextInput::make('student_profile.student_number')
                                                                    ->label(__('Student Number'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->required(),

                                                                Select::make('student_profile.major_id')
                                                                    ->label(__('Major'))
                                                                    ->options(\Modules\PPUDS\Entities\Major::get()->pluck('name', 'id'))
                                                                    ->disabled()
                                                                    ->searchable(),

                                                                TextInput::make('student_profile.enrollment_year')
                                                                    ->label(__('Enrollment Year'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->minLength(4)
                                                                    ->maxLength(4),

                                                                TextInput::make('student_profile.semester_level')
                                                                    ->label(__('Semester Level'))
                                                                    ->disabled()
                                                                    ->numeric(),

                                                                TextInput::make('student_profile.tawjihi_gpa')
                                                                    ->label(__('Tawjihi GPA'))
                                                                    ->numeric()
                                                                    ->disabled()
                                                                    ->step(0.1),

                                                                DatePicker::make('student_profile.dob')
                                                                    ->label(__('Date of Birth'))
                                                                    ->disabled()
                                                                    ->displayFormat('d/m/Y'),

                                                                Select::make('student_profile.gender')
                                                                    ->label(__('Gender'))
                                                                    ->disabled()
                                                                    ->options([
                                                                        'male' => __('Male'),
                                                                        'female' => __('Female'),
                                                                    ]),

                                                                Select::make('student_profile.cv_status')
                                                                    ->label(__('CV Status'))
                                                                    ->disabled()
                                                                    ->options([
                                                                        'pending' => __('Pending'),
                                                                        'approved' => __('Approved'),
                                                                        'rejected' => __('Rejected'),
                                                                    ]),
                                                            ]),
                                                    ])
                                                    ->columnSpan(2),

                                                Grid::make(1)
                                                    ->schema([
                                                        SpatieMediaLibraryFileUpload::make('cover_photo')
                                                            ->disk('media')
                                                            ->collection('cover_photo')
                                                            ->imageEditor()
                                                            ->alignCenter(),

                                                        SpatieMediaLibraryFileUpload::make('avatar')
                                                            ->disk('media')
                                                            ->collection('avatar')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->avatar()
                                                            ->alignCenter(),

                                                        SpatieMediaLibraryFileUpload::make('cv')
                                                            ->label(__('CV'))
                                                            ->disk('student_profiles')
                                                            ->collection('cv')
                                                            ->image()
                                                            ->imageEditor()
                                                            ->alignCenter(),
                                                    ])
                                                    ->columnSpan(1),
                                            ]),
                                    ]),

                                // Tabs\Tab::make('Student Profile')
                                //     ->icon('heroicon-o-academic-cap')
                                //     ->schema([
                                //         Grid::make(3)
                                //             ->schema([
                                //                 Section::make()
                                //                     ->columnSpan(2)
                                //                     ->schema([
                                //                         Grid::make(2)
                                //                             ->schema([
                                //                                 TextInput::make('student_profile.student_number')
                                //                                     ->label(__('Student Number'))
                                //                                     ->numeric()
                                //                                     ->disabled()
                                //                                     ->required(),

                                //                                 Select::make('student_profile.major_id')
                                //                                     ->label(__('Major'))
                                //                                     ->options(\Modules\PPUDS\Entities\Major::get()->pluck('name', 'id'))
                                //                                     ->disabled()
                                //                                     ->searchable(),

                                //                                 TextInput::make('student_profile.enrollment_year')
                                //                                     ->label(__('Enrollment Year'))
                                //                                     ->numeric()
                                //                                     ->disabled()
                                //                                     ->minLength(4)
                                //                                     ->maxLength(4),

                                //                                 TextInput::make('student_profile.semester_level')
                                //                                     ->label(__('Semester Level'))
                                //                                     ->disabled()
                                //                                     ->numeric(),

                                //                                 TextInput::make('student_profile.tawjihi_gpa')
                                //                                     ->label(__('Tawjihi GPA'))
                                //                                     ->numeric()
                                //                                     ->disabled()
                                //                                     ->step(0.1),

                                //                                 DatePicker::make('student_profile.dob')
                                //                                     ->label(__('Date of Birth'))
                                //                                     ->disabled()
                                //                                     ->displayFormat('d/m/Y'),

                                //                                 Select::make('student_profile.gender')
                                //                                     ->label(__('Gender'))
                                //                                     ->disabled()
                                //                                     ->options([
                                //                                         'male' => __('Male'),
                                //                                         'female' => __('Female'),
                                //                                     ]),

                                //                                 Select::make('student_profile.cv_status')
                                //                                     ->label(__('CV Status'))
                                //                                     ->disabled()
                                //                                     ->options([
                                //                                         'pending' => __('Pending'),
                                //                                         'approved' => __('Approved'),
                                //                                         'rejected' => __('Rejected'),
                                //                                     ]),
                                //                             ]),
                                //                     ]),

                                //                 Section::make()
                                //                     ->columnSpan(1)
                                //                     ->schema([
                                //                         SpatieMediaLibraryFileUpload::make('cv')
                                //                             ->label(__('CV'))
                                //                             ->disk('student_profiles')
                                //                             ->collection('cv')
                                //                             ->image()
                                //                             ->imageEditor()
                                //                             ->alignCenter(),
                                //                     ]),
                                //             ]),
                                //     ]),

                                Tabs\Tab::make('Work Experience')
                                    ->icon('heroicon-o-briefcase')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\WorkExperience\Index::class,
                                                    [
                                                        'studentId' => $this->userId,
                                                    ]
                                                )
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('Training History')
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\StudentCompany\Index::class,
                                                    [
                                                        'studentId' => $this->userId, // تم التحديث هنا
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('Registration')
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\Registration\Index::class,
                                                    [
                                                        'studentId' => $this->userId, // تم التحديث هنا
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('Attendance')
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\StudentAttendance\Index::class,
                                                    [
                                                        'studentId' => $this->userId,
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                                Tabs\Tab::make('Payment')
                                    ->icon('heroicon-o-academic-cap')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Livewire::make(
                                                    \Modules\PPUDS\Livewire\Pages\Student\Details\Payment\Index::class,
                                                    [
                                                        'studentId' => $this->userId, // تم التحديث هنا
                                                    ]
                                                )
                                                    ->columnSpanFull()
                                                    ->lazy(),
                                            ]),
                                    ]),

                            ])
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        // $this->authorize('');

        $this->validate();

        $data = $this->form->getState();

        DB::transaction(function () use ($data) {

            $user = $this->userRecord;

            // تحديث الباسورد فقط إذا تم إدخال باسورد جديد
            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // $user->update($updateData);

            $this->form->model($user)->saveRelationships();

            // Notification::make()->title('Saved successfully')->success()->send();
        });

        // إعادة التوجيه بعد الحفظ
        // return redirect()->route('students.index');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Student Details'), 'url' => route('students.details', $this->userId)], // استخدمنا userId هنا بدلاً من الموديل
            ],
        ]);
    }
}
