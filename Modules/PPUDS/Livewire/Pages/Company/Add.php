<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Hash;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Enums\CompanyStatus;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Role;

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
            ->model(Company::class)
            ->schema([
                Wizard::make([
                    Wizard\Step::make(__('General Info'))
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    Grid::make(3)
                                        ->columnSpan(3)
                                        ->schema([
                                            Section::make(__('Company Information'))
                                                ->schema([
                                                    TextInput::make('name')
                                                        ->required()
                                                        ->label(__('Name')),
                                                    TextInput::make('website')
                                                        ->url()
                                                        ->label(__('Website')),
                                                    Textarea::make('description')
                                                        ->label(__('Description')),
                                                ]),
                                        ]),

                                    Grid::make(1)
                                        ->columnSpan(1)
                                        ->schema([
                                            SpatieMediaLibraryFileUpload::make('logo')
                                                ->label(__('Logo'))
                                                ->model(Company::class)
                                                ->collection('logo'),

                                            Section::make()
                                                ->schema([
                                                    Select::make('company_category_id')
                                                        ->label(__('Company'))
                                                        ->required()
                                                        ->options(CompanyCategory::get()->pluck('name', 'id'))
                                                        ->searchable()
                                                ]),

                                            Section::make()
                                                ->schema([
                                                    Select::make('status')
                                                        ->label(__('Status'))
                                                        ->required()
                                                        ->default(CompanyStatus::ACTIVE->value)
                                                        ->options(CompanyStatus::options())
                                                        ->searchable()
                                                ])
                                        ])
                                ]),
                        ]),

                    Wizard\Step::make(__('Branches Management'))
                        ->icon('heroicon-m-building-storefront')
                        ->schema([
                            Repeater::make('branches') // هذا المفتاح سنستخدمه في الحفظ
                            ->label(__('Branches'))
                                ->relationship('branches') // إذا كانت العلاقة معرفة في الموديل، وإلا سنحفظ يدوياً
                                ->minItems(1) // إجباري فرع واحد على الأقل
                                ->defaultItems(1) // يفتح وبداخله فرع واحد جاهز للتعبئة
                                ->collapsible() // قابل للطي لترتيب الشاشة
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? __('New Branch')) // يظهر اسم الفرع كعنوان للصندوق
                                ->cloneable() // زر لنسخ الفرع (مفيد إذا كانت الفروع في نفس المدينة وبنفس الدوام)
                                ->schema([
                                    // === بيانات الفرع الأساسية ===
                                    Section::make(__('Branch Details'))
                                        ->schema([
                                            TextInput::make('name')
                                                ->label(__('Branch Name'))
                                                ->required()
                                                ->default(__('Main Branch')) // افتراضياً اسمه الفرع الرئيسي
                                                ->placeholder(__('e.g. Main Branch, Downtown Branch')),

                                            Grid::make(2)->schema([
                                                TextInput::make('email')->label(__('Email'))->email(),
                                                TextInput::make('phone')->label(__('Phone'))->tel(),
                                            ]),
                                        ])->compact(),

                                    // === بيانات الموقع (نفس الكود السابق مع إصلاح Cities) ===
                                    Section::make(__('Location'))
                                        ->schema([
                                            Grid::make(2)->schema([
                                                Select::make('country_id')
                                                    ->label(__('Country'))
                                                    ->options(Country::all()->pluck('name', 'id'))
                                                    ->searchable()
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),

                                                Select::make('city_id')
                                                    ->label(__('City'))
                                                    ->options(function (Get $get) {
                                                        $countryId = $get('country_id');
                                                        if (! $countryId) return [];

                                                        return City::whereHas('governorate', function (Builder $query) use ($countryId) {
                                                            $query->where('country_id', $countryId);
                                                        })->pluck('name', 'id');
                                                    })
                                                    ->searchable()
                                                    ->required(),
                                            ]),

                                            Grid::make(2)->schema([
                                                TextInput::make('latitude')->numeric()->required(),
                                                TextInput::make('longitude')->numeric()->required(),
                                            ]),
                                        ])->compact(),

                                    // === أوقات العمل ===
                                    Section::make(__('Working Hours'))
                                        ->schema([
                                            Grid::make(2)->schema([
                                                TimePicker::make('opening_time')->label(__('Opening'))->seconds(false)->required(),
                                                TimePicker::make('closing_time')->label(__('Closing'))->seconds(false)->required(),
                                            ]),
                                        ])->compact(),
                                ])
                                ->grid(1) // عرض الفروع تحت بعضها (أو 2 لعرضها كشبكة)
                        ]),

                    Wizard\Step::make('Delivery')
                        ->schema([

                        ]),

                    Wizard\Step::make('Billing')
                        ->schema([

                        ]),
                ])
                    ->columnSpan('full')
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        $this->data['created_by'] = auth()->id();

        $company = Company::create($this->data);

        if (isset($this->data['logo'])) {
            $company->addImage($this->data['logo']);
        }

        Toaster::success(__('Company created successfully'));

        $this->redirect(route('companies.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Add Company'), 'url' => route('companies.add')],
            ]
        ]);
    }
}
