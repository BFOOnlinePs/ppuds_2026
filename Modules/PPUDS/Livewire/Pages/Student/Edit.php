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
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\Major;
use Modules\PPUDS\Entities\StudentProfile;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public User $user;

    public ?array $data = [];

    public function mount(User $user)
    {
        $this->user = $user;

        $profile = $this->user->studentProfile;

        $this->form->fill(array_merge(
            $this->user->attributesToArray(),
            $profile ? $profile->attributesToArray() : []
        ));
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
                                            ->label(__('Name (Arabic)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_en')
                                            ->label(__('Name (English)'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label(__('Email Address'))
                                            ->email()
                                            ->required()
                                            ->unique('users', 'email', ignoreRecord: true),

                                        TextInput::make('phone')
                                            ->label(__('Phone'))
                                            ->numeric()
                                            ->required(),

                                        TextInput::make('password')
                                            ->label(__('Password'))
                                            ->password()
                                            ->revealable()
                                            // التعديلات الخاصة بصفحة التعديل:
                                            ->nullable() // غير إجباري
                                            ->dehydrated(fn ($state) => filled($state)) // لا يرسل للسيرفر إلا إذا تم تعبئته
                                            ->confirmed(),

                                        TextInput::make('password_confirmation')
                                            ->label(__('Confirm Password'))
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => filled($get('password'))) // يظهر فقط إذا كتب باسورد
                                            ->required(fn ($get) => filled($get('password'))),
                                    ]),

                                Section::make(__('Academic Information'))
                                    ->description(__('Student university details'))
                                    ->icon('heroicon-o-academic-cap')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('student_number')
                                            ->label(__('Student Number'))
                                            ->required()
                                            ->numeric()
                                            // استثناء الرقم الجامعي الحالي (نستثني الـ ID الخاص بالبروفايل)
                                            ->rule(function () {
                                                $profileId = $this->user->studentProfile?->id;

                                                return Rule::unique(config('ppuds.table_prefix').'student_profiles', 'student_number')
                                                    ->ignore($profileId);
                                            }),

                                        Select::make('major_id')
                                            ->label(__('Major'))
                                            ->options(function () {
                                                return Major::get()->pluck('name', 'id');
                                            })
                                            // خيار إضافة تخصص جديد سريعاً (اختياري في التعديل، لكن مفيد)
                                            ->createOptionForm([
                                                TextInput::make('reference_code')->label(__('Reference code'))->required(),
                                                TextInput::make('name')->label(__('Name'))->required(),
                                                Textarea::make('description')->label(__('Description')),
                                            ])
                                            ->createOptionUsing(function (array $data) {
                                                $data['created_by'] = auth()->id();

                                                return Major::create($data);
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        TextInput::make('enrollment_year')
                                            ->label(__('Enrollment Year'))
                                            ->numeric()
                                            ->minValue(2000)
                                            ->maxValue(date('Y') + 1),

                                        Select::make('semester_level')
                                            ->label(__('Semester Level'))
                                            ->options(array_combine(range(1, 10), range(1, 10)))
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
            ->statePath('data')
            ->model($this->user); // ربط الموديل بالفورم للمساعدة في الـ Validation
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {

            // 1. تحديث بيانات المستخدم الأساسية
            $userData = [
                'name' => $this->data['name'],
                'name_en' => $this->data['name_en'],
                'email' => $this->data['email'],
                'phone' => $this->data['phone'],
            ];

            // تحديث كلمة المرور فقط إذا تم إدخالها
            if (! empty($this->data['password'])) {
                $userData['password'] = Hash::make($this->data['password']);
            }

            $this->user->update($userData);

            // 2. تحديث أو إنشاء بيانات البروفايل
            $profileData = collect($this->data)
                ->except(['name', 'name_en', 'email', 'password', 'password_confirmation', 'roles'])
                ->toArray();

            // استخدام updateOrCreate لضمان عدم حدوث أخطاء
            StudentProfile::updateOrCreate(
                ['user_id' => $this->user->id], // شرط البحث
                $profileData // البيانات للتحديث
            );

            // تحديث الأدوار إذا لزم الأمر (في Add كان يتم إسناد student فقط)
            // إذا كنت تريد السماح بتعديل الأدوار هنا، يجب إضافة حقل Select للأدوار في الفورم
            // حالياً هو طالب، لذا لا داعي لتغيير دوره
        });

        // إعادة التوجيه
        return redirect()->route('students.index');
    }

    public function render()
    {
        return view('ppuds::livewire.pages.student.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Students List'), 'url' => route('students.index')],
                ['title' => __('Edit Student'), 'url' => '#'], // الرابط الحالي
            ],
        ]);
    }
}
