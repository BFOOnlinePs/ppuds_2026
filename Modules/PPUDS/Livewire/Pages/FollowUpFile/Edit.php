<?php

namespace Modules\PPUDS\Livewire\Pages\FollowUpFile;

use App\View\Components\AppLayout;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyDepartment;
use Modules\PPUDS\Entities\FollowUp;
use Modules\PPUDS\Entities\Registration;
use Modules\PPUDS\Enums\TrainingStatus;

class Edit extends Component implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    public ?array $data = [];
    public $record; // المتغير الذي يحمل السجل الحالي

    // استقبال المعامل من الراوت (تأكد أن الاسم يطابق ما في ملف routes/web.php)
    public function mount($followUp)
    {
        // 1. جلب السجل
        $this->record = FollowUp::findOrFail($followUp);

        // 2. تعبئة النموذج بالبيانات الحالية
        $this->form->fill($this->record->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record) // ربط النموذج بالسجل
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        // --- العمود الرئيسي (يسار) ---
                        Group::make()
                            ->columnSpan(['lg' => 2])
                            ->schema([

                                // 1. قسم بيانات الطالب والتسجيل
                                Section::make(__('Student & Registration Info'))
                                    ->icon('solar-user-id-bold-duotone')
                                    // 🔥 حل مشكلة التداخل والقائمة المختفية 🔥
                                    ->extraAttributes(['class' => '!overflow-visible relative z-20'])
                                    ->schema([
                                        Select::make('registration_id')
                                            ->label(__('Select Student Registration'))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->live()
                                            ->prefixIcon('solar-document-text-linear')
                                            ->options(function () {
                                                return Registration::with(['student', 'course'])
                                                    ->get()
                                                    ->mapWithKeys(function ($reg) {
                                                        return [$reg->id => "{$reg->student->name} - {$reg->course->name}"];
                                                    });
                                            }),
                                    ]),

                                // 2. قسم تفاصيل المكان (الشركات)
                                Section::make(__('Placement Details'))
                                    ->icon('solar-buildings-2-bold-duotone')
                                    // تقليل الـ z-index لهذا القسم لكي يظهر القسم العلوي فوقه
                                    ->extraAttributes(['class' => 'relative z-10'])
                                    ->schema([
                                        Grid::make(2)->schema([

                                            Select::make('company_id')
                                                ->label(__('Company'))
                                                ->options(Company::get()->pluck('name', 'id'))
                                                ->searchable()
                                                ->preload()
                                                ->live()
                                                // عند تغيير الشركة، نقوم بتصفير الفرع والقسم
                                                ->afterStateUpdated(function (Select $component) {
                                                    $component->getContainer()->getComponent('branchSelect')->state(null);
                                                    $component->getContainer()->getComponent('deptSelect')->state(null);
                                                })
                                                ->prefixIcon('solar-city-linear'),

                                            Select::make('branch_id')
                                                ->label(__('Branch'))
                                                ->key('branchSelect')
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon('solar-map-point-linear')
                                                ->placeholder(fn (Get $get) => $get('company_id') ? __('Select Branch') : __('Select Company First'))
                                                ->disabled(fn (Get $get) => ! $get('company_id'))
                                                ->options(fn (Get $get) =>
                                                Branch::whereHas('companies', function ($query) use ($get) {
                                                    $query->where('company_id', $get('company_id'));
                                                })->get()->pluck('name', 'id')
                                                ),

                                            Select::make('department_id')
                                                ->label(__('Department'))
                                                ->key('deptSelect')
                                                ->searchable()
                                                ->preload()
                                                ->prefixIcon('solar-users-group-two-rounded-linear')
                                                ->disabled(fn (Get $get) => ! $get('company_id'))
                                                ->options(fn (Get $get) =>
                                                    // يمكنك هنا إضافة فلترة للأقسام حسب الشركة إذا كانت العلاقة موجودة
                                                CompanyDepartment::get()->pluck('name', 'id')
                                                )
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                            ]),

                        // --- العمود الجانبي (يمين) ---
                        Group::make()
                            ->columnSpan(['lg' => 1])
                            ->schema([
                                Section::make(__('Status & Settings'))
                                    ->icon('solar-settings-bold-duotone')
                                    ->schema([
                                        Select::make('status')
                                            ->label(__('Training Status'))
                                            ->required()
                                            ->options(TrainingStatus::class)
                                            ->native(false)
                                            ->prefixIcon('solar-flag-bold-duotone'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function messages(): array
    {
        return [
            'data.registration_id.required' => __('Please select a student registration record.'),
            'data.company_id.required' => __('Please select a company.'),
            'data.status.required' => __('The status field is required.'),
        ];
    }

    public function save()
    {
        // $this->authorize("FollowUp Update");

        $this->validate();

        $data = $this->data;

        // منطق هام: إذا قام المستخدم بتغيير "سجل التسجيل"، يجب تحديث "الطالب" أيضاً
        if ($data['registration_id'] != $this->record->registration_id) {
            $registration = Registration::find($data['registration_id']);
            if ($registration) {
                $data['student_id'] = $registration->student_id;
            }
        }

        // تحديث السجل
        $this->record->update($data);

        Toaster::success(__('Follow-up record updated successfully'));

        $this->redirect(route('follow-ups.index'));
    }

    public function render()
    {
        // تأكد من وجود ملف العرض (يمكنك نسخ ملف add.blade.php وتسميته edit.blade.php)
        return view('ppuds::livewire.pages.follow-up.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Follow Ups'), 'url' => route('follow-ups.index')],
                ['title' => __('Edit Follow Up'), 'url' => '#'],
            ]
        ]);
    }
}
