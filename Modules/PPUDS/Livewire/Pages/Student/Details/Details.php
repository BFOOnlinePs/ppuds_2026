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
use Livewire\Attributes\Computed; // استيراد ضروري
use Modules\Core\Entities\User;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];
    public int $userId;

    public function mount($user)
    {
        // استخراج الـ ID
        $this->userId = is_object($user) ? $user->id : (int) $user;
        
        // تعبئة الفورم باستخدام الدالة المحسوبة
        $this->form->fill($this->userRecord->toArray());
    }

    // هذه الدالة المحسوبة ستجلب الموديل مرة واحدة فقط عند الحاجة إليه
    #[Computed]
    public function userRecord()
    {
        return User::with([
            'studentProfile', 
            'studentProfile.media', 
            'roles', 
            'media'
        ])->findOrFail($this->userId);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->userRecord) // استخدم الدالة المحسوبة هنا
            ->schema([
                \Filament\Infolists\Components\Grid::make(5)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->getStateUsing(fn($record) => $record->getAvatarUrlAttribute()),

                        TextEntry::make('name'),

                        TextEntry::make('email'),

                        TextEntry::make('student_profile.student_number'),
                    ])
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->userRecord) // استخدم الدالة المحسوبة هنا
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
                                                                ->alignCenter()
                                                        ])
                                                        ->columnSpan(1)
                                                ])
                                        ]),

                                    Tabs\Tab::make('Work Experience')
                                        ->icon('heroicon-o-academic-cap')
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([

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
        $this->authorize('update');
        
        $this->validate();

        $data = $this->form->getState();

        DB::transaction(function () use ($data) { // أضف use ($data) هنا
            
            // بما أننا نقوم بالتحديث، الأفضل استخدام update بدلاً من firstOrCreate
            $user = $this->userRecord;
            
            $updateData = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            // تحديث الباسورد فقط إذا تم إدخال باسورد جديد
            if (!empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            $user->update($updateData);

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
            ]
        ]);
    }
}