<?php

namespace Modules\GeoLocation\Livewire\Pages\Governorate;

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
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\Governorate;

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
            ->schema([
                Grid::make(2)
                    ->schema([
                        Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->columnSpanFull()
                                            ->label(__('Name'))
                                            ->required(),
                                        Select::make('code')
                                            ->label(__('Code'))
                                            ->options(Governorate::get()->pluck('code', 'code')->toArray())
                                            ->searchable()
                                            ->createOptionForm([
                                                TextInput::make('code')
                                                    ->label(__('Code'))
                                                    ->required(),
                                            ])
                                            ->createOptionUsing(fn (array $data) => $data['code'])
                                            ->getOptionLabelUsing(fn ($value) => $value)
                                            ->unique(table: config('geolocation.table_prefix') . 'governorates', column: 'code')
                                            ->required(),
                                        Select::make('country_id')
                                            ->label(__('Country'))
                                            ->searchable()
                                            ->options(Country::get()->pluck('name', 'id'))
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
            'data.code' => ['required', 'string', 'max:255'],
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
        ];
    }


    public function save()
    {
        $this->authorize('Governorate Create');

        $this->validate();

        $locale = $this->data['locale'] = app()->getLocale();
        $this->data['created_by'] = auth()->user()->id;

        $governorate = Governorate::create($this->data);

        $this->redirectRoute('governorates.index');
    }

    public function render()
    {
        return view('geolocation::livewire.pages.governorate.add')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Governorates List'), 'url' => route('governorates.index')],
                ['title' => __('Add Governorate'), 'url' => route('governorates.add')],
            ]
        ]);
    }
}
