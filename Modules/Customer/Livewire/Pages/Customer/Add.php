<?php

namespace Modules\Customer\Livewire\Pages\Customer;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Hash;
use Illuminate\Support\Str;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\Core\Enums\UserRole;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Enums\GenderType;
use Modules\Customer\Enums\Language;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Items\Entities\Category;
use Modules\Items\Enums\CategoryStatus;
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
            ->model(Customer::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Grid::make()
                            ->columnSpan(2)
                            ->schema([
                                Section::make(__('General Information'))
                                    ->columnSpan(2)
                                    ->schema([
                                        Grid::make(1)
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->columnSpanFull()
                                                            ->label(__('Name'))
                                                            ->required(),
                                                        TextInput::make('email')
                                                            ->label(__('Email'))
                                                            ->required()
                                                            ->email(),
                                                        TextInput::make('phone')
                                                            ->label(__('Phone'))
                                                            ->required()
                                                            ->numeric(),
                                                        DatePicker::make('date_of_birth')
                                                            ->label(__('Date of Birth')),
                                                        Select::make('gender')
                                                            ->label(__('Gender'))
                                                            ->options(GenderType::options()),
                                                        Textarea::make('notes')
                                                            ->columnSpanFull()
                                                            ->label(__('Notes')),
                                                    ]),
                                            ]),
                                    ]),
                                Section::make(__('GeoLocation'))
                                    ->columnSpan(2)
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                Select::make('country_id')
                                                    ->label(__('Country'))
                                                    ->required()
                                                    ->searchable()
                                                    ->options(Country::get()->pluck('name', 'id'))
                                                    ->default(Country::whereTranslation('name' , 'Palestine')->first()->id),
                                                Select::make('governorate_id')
                                                    ->label(__('Governorate'))
                                                    ->required()
                                                    ->searchable()
                                                    ->options(Governorate::get()->pluck('name', 'id')),
                                                Select::make('city_id')
                                                    ->label(__('City'))
                                                    ->required()
                                                    ->searchable()
                                                    ->options(City::get()->pluck('name', 'id')),
                                                Select::make('district_id')
                                                    ->label(__('District'))
                                                    ->required()
                                                    ->searchable()
                                                    ->options(District::get()->pluck('name', 'id')),
                                                Textarea::make('address')
                                                    ->columnSpanFull()
                                                    ->rows(4)
                                                    ->label(__('Address'))
                                            ])
                                    ]),
                            ]),
                        Grid::make()
                            ->columnSpan(1)
                            ->schema([
                                Section::make(__('Image Information'))
                                    ->columnSpanFull()
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('image')
                                            ->image()
                                            ->disk('customers')
                                            ->collection('customer_image')
                                            ->label(__('Image'))
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('400')
                                            ->imageResizeTargetHeight('400')
                                            ->maxSize(10000),
                                    ]),


                                Section::make(__('Language'))
                                    ->columnSpanFull()
                                    ->schema([
                                        Select::make('language')
                                            ->label(__('Language'))
                                            ->options(Language::options())
                                            ->default(Language::AR)
                                    ])
                            ])
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.email' => ['nullable', 'email', 'max:255'],
            'data.phone' => ['nullable', 'string', 'max:20'],
            'data.country_id' => ['required', 'exists:countries,id'],
            'data.governorate_id' => ['required', 'exists:governorates,id'],
            'data.city_id' => ['required', 'exists:cities,id'],
            'data.district_id' => ['required', 'exists:districts,id'],
            'data.image' => ['nullable', 'image', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.string' => __('Name must be a string'),
            'data.name.max' => __('Name must not exceed 255 characters'),
            'data.email.email' => __('Email must be a valid email address'),
            'data.email.max' => __('Email must not exceed 255 characters'),
            'data.phone.string' => __('Phone must be a string'),
            'data.phone.max' => __('Phone must not exceed 20 characters'),
            'data.country_id.required' => __('Country is required'),
            'data.governorate_id.required' => __('Governorate is required'),
            'data.city_id.required' => __('City is required'),
            'data.district_id.required' => __('District is required'),
            'data.image.image' => __('The file must be a valid image.'),
            'data.image.max' => __('Image size must not exceed 10MB.'),
        ];
    }


    public function save()
    {
        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $user = User::create([
            'name' => $this->data['name'],
            'email' => $this->data['email'],
            'phone' => $this->data['phone'],
            'password' => Hash::make($this->data['phone']),
        ]);

        $user->assignRole(UserRole::CUSTOMER->value);

        $customer = $user->clinicProfile()->create($this->data);

        if (isset($this->data['image']) && !empty($this->data['image'])) {
            $customer->addImage($this->data['image']);
        }
        else{
            $user->generateAvatar();
        }
        $this->redirectRoute('customers.index');
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Customers List'), 'url' => route('customers.index')],
                ['title' => __('Add Customer'), 'url' => route('customers.add')],
            ]
        ]);
    }
}
