<?php

namespace Modules\GeoLocation\Livewire\Pages\District;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Modules\Core\Entities\Currency;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Enums\DistrictType;
use Modules\Items\Entities\Category;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount($district)
    {
        $this->data = District::find($district)->toArray();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General Information')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->columnSpanFull()
                                    ->label(__('Name'))
                                    ->required(),
                                Select::make('city_id')
                                    ->label(__('City'))
                                    ->searchable()
                                    ->options(City::all()->pluck('name', 'id'))
                                    ->required(),
                                Select::make('type')
                                    ->label(__('Type'))
                                    ->default(DistrictType::DISTRICT)
                                    ->options(DistrictType::options())
                                    ->required(),
                                TextInput::make('latitude')
                                    ->label(__('Latitude'))
                                    ->nullable(),
                                TextInput::make('longitude')
                                    ->label(__('Longitude'))
                                    ->numeric()
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function rules(): array
    {
        return [
            'data.name' => ['required', 'string', 'max:255'],
            'data.code' => ['required' , 'string', 'max:255'],
            'data.phone_code' => ['required', 'string', 'max:255'],
            'data.currency' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'data.name.required' => __('Name is required'),
            'data.name.string' => __('Name must be a string'),
            'data.code.required' => __('Code is required'),
            'data.code.string' => __('Code must be a string'),
            'data.code.unique' => __('Code already exist'),
            'data.phone_code.required' => __('Phone Code is required'),
            'data.currency.required' => __('Currency is required'),
            'data.currency.string' => __('Currency must be a string'),
            'data.phone_code.string' => __('Phone Code must be a string'),
        ];
    }


    public function save()
    {
        $this->authorize('Currency Create');

        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $country = Currency::create($this->data);

        $this->redirectRoute('countries.index');
    }

    public function render()
    {
        return view('geolocation::livewire.pages.country.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Districts List'), 'url' => route('districts.index')],
                ['title' => __('Edit District'), 'url' => route('districts.add')],
            ]
        ]);
    }
}
