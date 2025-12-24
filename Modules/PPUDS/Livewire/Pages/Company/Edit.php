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
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyCategory;
use Modules\PPUDS\Enums\CompanyStatus;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public Company $company;

    public function mount($company)
    {
        $this->form->fill($company->toArray());
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
                                    ->collection('logo')
                                    ->image()
                                    ->imageEditor(), // اختياري

                                Section::make()
                                    ->schema([
                                        Select::make('company_category_id')
                                            ->label(__('Category'))
                                            ->required()
                                            ->options(CompanyCategory::get()->pluck('name', 'id'))
                                            ->searchable(),
                                    ]),

                                Section::make()
                                    ->schema([
                                        Select::make('status')
                                            ->label(__('Status'))
                                            ->required()
                                            ->options(CompanyStatus::options())
                                            ->searchable()
                                    ])
                            ])
                    ]),
            ])
            ->statePath('data')
            ->model($this->company); // مهم جداً: ربط الفورم بالكائن الحالي ليظهر الصورة القديمة
    }

    public function save()
    {
        $this->validate();

        $data = $this->form->getState();

        // استبعاد اللوجو من مصفوفة البيانات لأنها لا تخزن في جدول الشركات مباشرة
        $attributes = collect($data)->except(['logo'])->toArray();

        // تحديث بيانات الشركة
        $this->company->update($attributes);

        // حفظ العلاقات (مثل الصور) عبر Filament
        $this->form->model($this->company)->saveRelationships();

        Toaster::success(__('Company updated successfully'));

        $this->redirect(route('companies.index'));
    }

    public function render()
    {
        return view('ppuds::livewire.pages.company.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Companies List'), 'url' => route('companies.index')],
                ['title' => __('Edit Company'), 'url' => route('companies.edit', $this->company->id)],
            ]
        ]);
    }
}
