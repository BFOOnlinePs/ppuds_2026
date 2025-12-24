<?php

namespace Modules\GeoLocation\Livewire\Pages\Country;

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
            ->model(Country::class)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make(__('General information'))
                            ->columnSpan(3)
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpanFull()
                                            ->label(__('Name'))
                                            ->required(),
                                        TextInput::make('code')
                                            ->label(__('Code'))
                                            ->unique(table: config('geolocation.table_prefix') . 'countries', column: 'code')
                                            ->required(),
                                        TextInput::make('phone_code')
                                            ->label(__('Phone Code'))
                                            ->required(),
                                        Select::make('currency')
                                            ->label(__('Currency'))
                                            ->options(Currency::get()->pluck('name', 'id')->toArray())
                                            ->searchable()
                                            ->required(),
                                    ]),
                            ]),
                        Section::make('Cities and Districts')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('city_id')
                                            ->label(__('City'))
                                            ->relationship('cities', 'name')
                                            ->searchable()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('Name')),
                                            ])
            ,
                                        Select::make('district_id')
                                            ->label(__('District')),
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
            'data.code' => ['required', 'string', 'max:255'],
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
        return view('geolocation::livewire.pages.country.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Countries List'), 'url' => route('countries.index')],
                ['title' => __('Add Currency'), 'url' => route('countries.add')],
            ]
        ]);
    }
}
