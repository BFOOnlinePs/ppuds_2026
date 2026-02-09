<?php

namespace Modules\PPUDS\Livewire\Pages\Student;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
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
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\StudnetProfile; // انتبه: الاسم كما في ملفك المرفق
use Modules\PPUDS\Entities\Major; // تأكد من وجود موديل للتخصصات
use Spatie\Permission\Models\Role;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];
    public User $user;

    public function mount(User $user)
    {
        $this->user = $user->load('studentProfile');
        $data = $this->user->toArray();
        $data['studentProfile'] = $this->user->studentProfile
            ? $this->user->studentProfile->toArray()
            : [];
        $this->form->fill($data);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->user)
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->getStateUsing(fn($record) => $record->getAvatarUrlAttribute()),

                        TextEntry::make('name'),

                        TextEntry::make('email'),

                        TextEntry::make('studentProfile.student_number'),
                    ])
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->user)
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
                                                                ->required(),

                                                            TextInput::make('email')
                                                                ->label(__('Email'))
                                                                ->email()
                                                                ->required(),

                                                            TextInput::make('password')
                                                                ->label(__('Password'))
                                                                ->password()
                                                                ->dehydrated(fn ($state) => filled($state)) // حفظ الباسورد فقط اذا تم تغييره
                                                                ->required(fn (string $context): bool => $context === 'create'), // مطلوب فقط عند الانشاء
                                                        ])
                                                        ->columnSpan(2),

                                                    Grid::make(1)
                                                        ->schema([
                                                            SpatieMediaLibraryFileUpload::make('avatar')
                                                                ->disk('media')
                                                                ->collection('avatar')
                                                                ->image()
                                                                ->imageEditor()
                                                                ->avatar() // جعلها دائرية للمعاينة
                                                                ->alignCenter()
                                                        ])
                                                        ->columnSpan(1)
                                                ])
                                        ]),

                                    Tabs\Tab::make('Student Profile')
                                        ->icon('heroicon-o-academic-cap')
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    TextInput::make('studentProfile.student_number')
                                                        ->label(__('Student Number'))
                                                        ->numeric()
                                                        ->required(),

                                                    Select::make('studentProfile.major_id')
                                                        ->label(__('Major'))
                                                        ->options(\Modules\PPUDS\Entities\Major::get()->pluck('name', 'id')) // تأكد من استيراد كلاس Major
                                                        ->searchable(),

                                                    TextInput::make('studentProfile.enrollment_year')
                                                        ->label(__('Enrollment Year'))
                                                        ->numeric()
                                                        ->minLength(4)
                                                        ->maxLength(4),

                                                    TextInput::make('studentProfile.semester_level')
                                                        ->label(__('Semester Level'))
                                                        ->numeric(),

                                                    TextInput::make('studentProfile.tawjihi_gpa')
                                                        ->label(__('Tawjihi GPA'))
                                                        ->numeric()
                                                        ->step(0.1),

                                                    // --- البيانات الشخصية للطالب ---
                                                    DatePicker::make('studentProfile.dob')
                                                        ->label(__('Date of Birth'))
                                                        ->displayFormat('d/m/Y'),

                                                    Select::make('studentProfile.gender')
                                                        ->label(__('Gender'))
                                                        ->options([
                                                            'male' => __('Male'),
                                                            'female' => __('Female'),
                                                        ]),

                                                    // --- الحالة والسيرة الذاتية ---
                                                    Select::make('studentProfile.cv_status')
                                                        ->label(__('CV Status'))
                                                        ->options([
                                                            'pending' => __('Pending'),
                                                            'approved' => __('Approved'),
                                                            'rejected' => __('Rejected'),
                                                        ]),

                                                    // ملاحظة: رفع ملف الـ CV لعلاقة HasOne يتطلب إعدادات خاصة في Filament
                                                    // يفضل عرضه هنا للقراءة فقط أو استخدام Resource منفصل للطالب لرفع الملفات
                                                ]),
                                        ]),
                                ])
                            ->columnSpanFull()
                ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {

            $userData = [
                'name'          => $this->data['name'],
                'email'         => $this->data['email'],
                'phone'         => $this->data['phone'],
                'password'      => Hash::make($this->data['password']),
            ];

            $user = User::create($userData);
            $user->generateAvatar();

            $user->assignRole('student');

            $profileData = collect($this->data)
                ->except(['name', 'email', 'password', 'password_confirmation', 'roles'])
                ->toArray();

            StudnetProfile::create(array_merge($profileData, [
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
        return view('ppuds::livewire.pages.student.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Student Details'), 'url' => route('students.details', $this->user)],
            ]
        ]);
    }
}
