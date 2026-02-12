<?php

namespace Modules\PPUDS\Livewire\Pages\Company\Details;

use App\View\Components\AppLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data = [];
    public Company $company;

    public function mount(Company $company)
    {
//        $this->$company = $company->load('studentProfile');

        $data = $this->company->toArray();

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->company)
            ->schema([
                Grid::make(3)
                ->schema([
                            Tabs::make('tabs')
                                ->tabs([

                                    Tabs\Tab::make('Personal Information')
                                        ->icon('heroicon-o-user')
                                        ->schema([
                                            Grid::make(3)
                                                ->schema([
                                                    Grid::make(2)
                                                        ->schema([
                                                            TextInput::make('name')
                                                                ->label(__('Name'))
                                                                ->columnSpanFull()
                                                                ->required(),

                                                            TextInput::make('website')
                                                                ->label(__('Website'))
                                                                ->columnSpan(1)
                                                                ->url(),

                                                            Select::make('company_category_id')
                                                                ->label(__('Company Category'))
                                                                ->options(CompanyCategory::all()->pluck('name', 'id'))
                                                                ->required(),

                                                            Textarea::make('description')
                                                                ->label(__('Description'))
                                                                ->columnSpanFull()
                                                                ->rows(3)
                                                                ->required(),
                                                        ])
                                                        ->columnSpan(2),

                                                    Grid::make(1)
                                                        ->schema([
                                                            SpatieMediaLibraryFileUpload::make('cover_photo')
                                                                ->disk('media')
                                                                ->collection('cover_photo')
                                                                ->imageEditor()
                                                                ->alignCenter(),

                                                            SpatieMediaLibraryFileUpload::make('logo')
                                                                ->disk('media')
                                                                ->collection('logo')
                                                                ->image()
                                                                ->imageEditor()
                                                                ->avatar()
                                                                ->alignCenter()
                                                        ])
                                                        ->columnSpan(1)
                                                ])
                                        ]),

                                    Tabs\Tab::make('Branches')
                                        ->icon('heroicon-o-user')
                                        ->schema([
                                            Grid::make(3)
                                                ->schema([
                                                    Grid::make(2)
                                                        ->schema([

                                                        ])
                                                        ->columnSpanFull(2),
                                                ])
                                        ]),

                                    Tabs\Tab::make('Training History')
                                        ->icon('heroicon-o-academic-cap')
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    Livewire::make(\Modules\PPUDS\Livewire\Pages\Student\Details\StudentCompany\Index::class ,
                                                        [
                                                            'companyId' => $this->company->id,
                                                        ]
                                                    )
                                                        ->columnSpanFull()
                                                    ->lazy()
                                                ]),
                                        ]),
                                ])
                            ->columnSpanFull()
                ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        $this->validate();

        $data = $this->form->getState();

        DB::transaction(function () use ($data) {

            $company = Company::updateOrCreate(
                ['id' => $this->company->id ?? null],
                $data
            );

            $this->form->model($company)->saveRelationships();

            $this->company = $company;
        });

        return redirect()->route('companies.details', $this->company);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.details.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Company List'), 'url' => route('companies.index')],
                ['title' => __('Company Details'), 'url' => route('companies.details', $this->company)],
            ]
        ]);
    }
}
