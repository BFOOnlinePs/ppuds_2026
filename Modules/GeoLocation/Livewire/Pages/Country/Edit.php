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
use Modules\GeoLocation\Entities\Country;
use Modules\Items\Entities\Category;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount($country)
    {
        $this->data = Country::find($country)->toArray();
        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make()
                            ->columnSpan(3)
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpanFull()
                                            ->label(__('Name'))
                                            ->required(),
                                        Select::make('code')
                                            ->label(__('Code'))
                                            ->options(Country::get()->pluck('code' , 'code')->toArray())
                                            ->searchable()
                                            ->unique(table: config('geolocation.table_prefix') . 'countries', column: 'code')
                                            ->reactive()
                                            ->afterStateUpdated(function (Set $set , $state){
                                                if ($state)
                                                {
                                                    $country = Country::where('code', $state)->first();
                                                    if ($country)
                                                    {
                                                        $set('phone_code', $country->phone_code);
                                                        $set('currency', $country->currency);
                                                    }
                                                }
                                            })
                                            ->required(),
                                        Select::make('phone_code')
                                            ->label(__('Phone Code'))
                                            ->options(Country::get()->pluck('phone_code' , 'phone_code')->toArray())
                                            ->searchable()
                                            ->required(),
                                        Select::make('currency_id')
                                            ->label(__('Currency'))
                                            ->searchable()
                                            ->options(Currency::get()->pluck('name' , 'id')->toArray())
                                            ->required()
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
                ['title' => __('Countries List'), 'url' => route('countries.index')],
                ['title' => __('Add Currency'), 'url' => route('countries.add')],
            ]
        ]);
    }
}
