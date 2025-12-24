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
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Jantinnerezo\LivewireAlert\Facades\LivewireAlert;
use Livewire\Component;
use Modules\Core\Entities\User;
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

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public User $user;
    public Customer $customer;

    public function mount($customer)
    {
        $this->user = $customer->user;
        $this->form->fill(array_merge(
            $this->customer->toArray(),
            ['user' => $this->user->toArray()]
        ));
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->customer)
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
                                                        TextInput::make('user.name')
                                                            ->columnSpanFull()
                                                            ->label(__('Name'))
                                                            ->required(),
                                                        TextInput::make('user.email')
                                                            ->label(__('Email'))
                                                            ->unique(table: 'users', column: 'email', ignorable: $this->user)
                                                            ->email(),
                                                        TextInput::make('user.phone')
                                                            ->label(__('Phone'))
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
                                                    ->default(Country::whereTranslation('name', 'Palestine')->first()->id),
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
                                            ->disk('customer')
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
        $this->user->update(Arr::except($this->data['user'], ['locale', 'created_by']));
        $this->customer->update($this->data);

        if (isset($this->data['image'])) {
            $this->customer->addImage($this->data['image']);
        }
        $this->redirectRoute('customers.index');
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Customers List'), 'url' => route('customers.index')],
                ['title' => __('Add Customer'), 'url' => route('customers.add')],
            ]
        ]);
    }
}
