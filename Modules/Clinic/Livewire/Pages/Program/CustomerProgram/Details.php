<?php

namespace Modules\Clinic\Livewire\Pages\Program\CustomerProgram;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\FoodItem;
use Modules\Clinic\Entities\ProgramCustomer;
use Modules\Clinic\Entities\ProgramTypeOfMeal;
use Modules\Clinic\Entities\ServingSize;
use Modules\Core\Filament\Forms\Components\Textarea;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ProgramCustomer $customerProgram;
    public ?array $data = [];

    public function mount(ProgramCustomer $customerProgram): void
    {
        $this->customerProgram = $customerProgram;
        // CORRECTED: Load the days related to the customerProgram itself
        $this->customerProgram->load('days.dayMeals.mealItems');

        // If the customer has no days, you might want to copy them from the template program here.
        // This is a common pattern, but for now we just load what exists.
        if ($this->customerProgram->days->isEmpty() && $this->customerProgram->program->days->isNotEmpty()) {
            // Optional: Logic to copy days from program template to customer program on first view.
            // For example:
            // foreach ($this->customerProgram->program->days as $templateDay) {
            //     $newDay = $templateDay->replicate()->fill(['program_customer_id' => $this->customerProgram->id, 'program_id' => null]);
            //     $newDay->save();
            //     // You would also need to replicate dayMeals and mealItems
            // }
            // $this->customerProgram->load('days.dayMeals.mealItems'); // Reload after copying
        }

        // CORRECTED: Fill the form with the customerProgram's data
        $this->form->fill($this->customerProgram->toArray());
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->customerProgram)
            ->schema([
                \Filament\Infolists\Components\Grid::make(1)
                    ->schema([
                        \Filament\Infolists\Components\Section::make()
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->label(__('Name'))
                                    ->url(function ($record) {
                                        return route('customers.details', $record->customer);
                                    })
                                ])
                        ])
                ])
            ->columns(2);
    }

    // getSafeName function remains the same...

    public function form(Form $form): Form
    {
        return $form
            // CORRECTED: Bind the form to the customerProgram instance
            ->model($this->customerProgram)
            ->schema([
                Section::make()
                    ->schema([
                        Repeater::make('days')
                            ->label('أيام برنامج العميل') // Changed label for clarity
                            ->relationship()
                            ->collapsed()
                            ->cloneable()
                            ->itemLabel(function (array $state): ?string {
                                return 'اليوم رقم: ' . ($state['day_number'] ?? 'جديد');
                            })
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data, $component): array {
                                $data['day_number'] = count($component->getState());
                                $data['created_by'] = auth()->id();
                                return $data;
                            })
                            ->schema([
                                TextInput::make('day_number')
                                    ->hidden()
                                    ->label('رقم اليوم')
                                    ->numeric()
                                    ->default(fn($component) => count($component->getContainer()->getParentComponent()->getState()))
                                    ->required(),

                                Repeater::make('dayMeals')
                                    ->label('الوجبات')
                                    ->relationship()
                                    ->cloneable()
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                        $data['created_by'] = auth()->id();
                                        return $data;
                                    })
                                    ->schema([
                                        Grid::make(6)
                                            ->schema([
                                                Select::make('type_of_meal_id')
                                                    ->label('نوع الوجبة')
                                                    ->options(ProgramTypeOfMeal::get()->pluck('name', 'id'))
                                                    ->required()
                                                    ->columnSpan(1),

                                                Repeater::make('mealItems')
                                                    ->label('الأصناف')
                                                    ->relationship()
                                                    ->cloneable()
                                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                                        $data['created_by'] = auth()->id();
                                                        return $data;
                                                    })
                                                    ->schema([
                                                        Select::make('food_item_id')
                                                            ->label('الصنف')
                                                            ->options(FoodItem::get()->pluck('name', 'id'))
                                                            ->searchable()
                                                            ->reactive()
                                                            ->afterStateUpdated(fn (callable $set) => $set('serving_size_id', null))
                                                            ->required(),
                                                        TextInput::make('quantity')
                                                            ->label('الكمية')
                                                            ->numeric()
                                                            ->required(),
                                                        Select::make('serving_size_id')
                                                            ->label('وحدة القياس')
                                                            ->options(function (callable $get) {
                                                                $foodItemId = $get('food_item_id');
                                                                if (!$foodItemId) return [];
                                                                return ServingSize::where('food_item_id', $foodItemId)->get()->pluck('name', 'id');
                                                            })
                                                            ->searchable()
                                                            ->disabled(fn (callable $get): bool => !$get('food_item_id'))
                                                            ->required(),
                                                        Textarea::make('description')
                                                            ->label(__('Description'))
                                                            ->rows(2),
                                                    ])
                                                    ->columns(4)
                                                    ->addActionLabel('إضافة صنف')
                                                    ->columnSpan(5),
                                            ]),
                                    ])
                                    ->addActionLabel('إضافة وجبة'),
                            ])
                            ->addActionLabel('إضافة يوم جديد'),
                    ])
            ])
            ->statePath('data');
    }

    public function save()
    {
        // CORRECTED: Update the customerProgram model itself.
        // The relationship manager will handle saving the 'days' repeater data.
        $this->customerProgram->update($this->form->getState());

        Toaster::success('تم تحديث برنامج العميل بنجاح!');
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.customer-program.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('الرئيسية'), 'url' => route('home')],
                ['title' => __('قائمة برامج العملاء'), 'url' => route('program.customer-programs.index')],
                ['title' => __('إدارة برنامج العميل: ') . $this->customerProgram->program->name, 'url' => '#'],
            ]
        ]);
    }
}
