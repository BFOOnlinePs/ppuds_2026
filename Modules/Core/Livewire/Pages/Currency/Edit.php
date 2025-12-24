<?php

namespace Modules\Core\Livewire\Pages\Currency;

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
use Modules\Core\Enums\SymbolPosition;
use Modules\Items\Entities\Category;
use Svg\Tag\Text;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function mount($currency)
    {
        $this->data = Currency::findOrFail($currency)->toArray();
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
                                        TextInput::make('code')
                                            ->label(__('Code'))
                                            ->unique('currencies', column: 'code')
                                            ->required(),
                                        TextInput::make('symbol')
                                            ->label(__('Symbol'))
                                            ->required(),
                                        Select::make('symbol_position')
                                            ->label(__('Position'))
                                            ->options(
                                                collect(SymbolPosition::cases())
                                                    ->mapWithKeys(fn ($case) => [$case->value => $case->name])
                                                    ->toArray()
                                            ),
                                        TextInput::make('decimal_places')
                                            ->label(__('Decimal places'))
                                            ->default(2)
                                            ->numeric()
                                            ->required(),
                                        TextInput::make('exchange_rate')
                                            ->label(__('Exchange rate'))
                                            ->required()
                                            ->numeric()
                                            ->default(1.0000),
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
            'data.symbol' => ['required' , 'string', 'max:255'],
            'data.symbol_position' => ['required' , 'string', 'max:255'],
            'data.decimal_places' => ['required' , 'string', 'max:255'],
            'data.exchange_rate' => ['required' , 'string', 'max:255'],
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
            'data.symbol.required' => __('Symbol is required'),
            'data.symbol.string' => __('Symbol must be a string'),
            'data.exchange_rate.required' => __('Exchange rate is required'),
            'data.exchange_rate.numeric' => __('Exchange rate must be a number'),
        ];
    }


    public function save()
    {
        $this->authorize('Currency Create');

        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $currency = Currency::create($this->data);

        $this->redirectRoute('currencies.index');
    }

    public function render()
    {
        return view('core::livewire.pages.currency.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Currencies List'), 'url' => route('currencies.index')],
                ['title' => __('Add Currency'), 'url' => route('currencies.add')],
            ]
        ]);
    }
}
