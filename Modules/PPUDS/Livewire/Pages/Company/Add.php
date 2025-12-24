<?php

namespace Modules\PPUDS\Livewire\Pages\Company;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Hash;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Branch\Entities\Branch;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\Textarea;
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
            ->schema([
                Grid::make(4)
                            ->schema([
                                Grid::make(3)
                                    ->columnSpan(3)
                                    ->schema([
                                        Section::make(__('Company Information'))
                                            ->columnSpan(3)
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
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        $this->data['created_by'] = auth()->id();

        $company = Company::create($this->data);

        if (isset($this->data['logo'])){
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
