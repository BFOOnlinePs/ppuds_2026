<?php

namespace Modules\GeoLocation\Livewire\Pages\City;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Core\Entities\Currency;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;
use Modules\GeoLocation\Enums\CapitalType;
use Modules\GeoLocation\Enums\CityType;
use Modules\Items\Entities\Category;
use Svg\Tag\Text;

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
            ->model(City::class)
            ->schema([
                Grid::make(2)
                    ->schema([
                        Section::make(__('General information'))
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpanFull()
                                            ->label(__('Name'))
                                            ->required(),
                                        Select::make('governorate_id')
                                            ->label(__('Governorate'))
                                            ->options(Governorate::get()->pluck('name', 'id'))
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('population')
                                            ->label(__('Population'))
                                            ->numeric(),
                                        TextInput::make('latitude')
                                            ->label(__('Latitude'))
                                            ->numeric(),
                                        TextInput::make('longitude')
                                            ->label(__('Longitude'))
                                            ->numeric(),
                                        Select::make('type')
                                            ->label(__('Type'))
                                            ->options(CityType::options())
                                            ->default(CityType::CITY->value),
                                        Select::make('capital_type')
                                            ->label(__('Capital Type'))
                                            ->options(CapitalType::options())
                                            ->default(CapitalType::COUNTRY->value),
                                        Toggle::make('is_capital')
                                            ->label(__('Is Capital'))
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'data.population' => ['required', 'numeric', 'min:0'],
            'data.latitude' => ['required', 'numeric', 'min:0'],
            'data.longitude' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.name.string' => __('Name must be a string'),
            'data.governorate_id.required' => __('Governorate is required'),
            'data.governorate_id.integer' => __('Governorate must be an integer'),
            'data.population.required' => __('Population is required'),
            'data.population.numeric' => __('Population must be a numeric'),
            'data.latitude.required' => __('Latitude is required'),
            'data.latitude.numeric' => __('Latitude must be a numeric'),
            'data.latitude.min' => __('Latitude must be at least 0'),
            'data.longitude.required' => __('Longitude is required'),
            'data.longitude.numeric' => __('Longitude must be a numeric'),
            'data.longitude.min' => __('Longitude must be at least 0'),
            'data.longitude.max' => __('Longitude must be at most 0'),
            'data.type.required' => __('Type is required'),
            'data.capital_type.required' => __('Capital Type is required'),
            'data.capital_type.integer' => __('Capital Type must be an integer'),
            'data.capital_type.min' => __('Capital Type must be at least 0'),
            'data.capital_type.max' => __('Capital Type must be at most 0'),
            'data.population.min' => __('Population must be at least 0'),
            'data.population.max' => __('Population must be at most 0'),
        ];
    }


    public function save()
    {
        $this->authorize('City Create');

        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $city = City::create($this->data);

        $this->redirectRoute('cities.index');
    }

    public function render()
    {
        return view('geolocation::livewire.pages.city.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Cities List'), 'url' => route('cities.index')],
                ['title' => __('Add City'), 'url' => route('cities.add')],
            ]
        ]);
    }
}
