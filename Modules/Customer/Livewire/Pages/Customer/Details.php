<?php

namespace Modules\Customer\Livewire\Pages\Customer;

use App\Models\User;
use App\View\Components\AppLayout;
use Carbon\Carbon;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\Branch;
use Modules\Clinic\Entities\FoodItem;
use Modules\Clinic\Entities\MealItem;
use Modules\Clinic\Entities\Program;
use Modules\Clinic\Entities\ProgramCustomer;
use Modules\Clinic\Entities\ProgramDay;
use Modules\Clinic\Entities\ProgramDayMeal;
use Modules\Clinic\Entities\ProgramMealItem;
use Modules\Clinic\Entities\ProgramTypeOfMeal;
use Modules\Clinic\Entities\ServingSize;
use Modules\Clinic\Entities\Survey;
use Modules\Clinic\Enums\CustomerProgramStatus;
use Modules\Core\Enums\TransactionFlow;
use Modules\Core\Enums\TransactionType;
use Modules\Core\Filament\Forms\Components\Textarea;
use Modules\Core\Interfaces\TransactionLoggerInterface;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Livewire\Pages\Customer\Details\Tables\Subscription;
use Modules\Customer\Livewire\Pages\Customer\Details\Tables\Transaction;
use Modules\Customer\Livewire\Pages\Customer\Details\Views\Surveys;
use Modules\GeoLocation\Entities\City;
use Modules\GeoLocation\Entities\Country;
use Modules\GeoLocation\Entities\District;
use Modules\GeoLocation\Entities\Governorate;
use Modules\Subscription\Entities\Plan;
use Illuminate\Support\Arr;
use Modules\Items\Enums\PaymentMethod;
use Modules\Subscription\Enums\Status;
use Modules\Subscription\Enums\SubscriptionTransactionType;

class Details extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?array $data;
    public $customer;
    public array $statistics = [];
    protected $subscriptions;
    public int $selectedTab = 1;
    public array $programData = [];
    public array $newReadingData = [];
    public array $newSubscriptionData = [];

    public function mount($customer)
    {
        $this->customer = Customer::with('user')->findOrFail($customer);
        $this->data = $this->customer->toArray();
        $this->calculateStatistics();
        $this->selectedTab = $this->parseTab(request()->query('tab', 1));
        $this->form->fill($this->data);
    }

    public function hydrate()
    {
        $this->selectedTab = $this->parseTab(request()->query('tab', $this->selectedTab));
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->customer)
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(6)
                            ->schema([
                                TextEntry::make('name')
                                    ->label(__('Name'))
                                    ->columnSpan(1),
                                TextEntry::make('user.phone')
                                    ->label(__('Phone'))
                                    ->columnSpan(1),
                                TextEntry::make('date_of_birth')
                                    ->label(__('Date of Birth'))
                                    ->columnSpan(1),
                                TextEntry::make('age')
                                    ->label(__('Age'))
                                    ->getStateUsing(fn($record) => $record->date_of_birth ? Carbon::parse($record->date_of_birth)->age : null
                                    )
                                    ->columnSpan(1),
                                TextEntry::make('status')
                                    ->label('الحالة')
                                    ->badge()
                                    ->color(\Modules\Customer\Enums\Status::class)
                                    ->columnSpan(1),
                                TextEntry::make('overall_subscription_status')
                                    ->label('حالة الاشتراك')
                                    ->badge()
                                    ->color(function ($state): string {
                                        if ($state instanceof Status) {
                                            return $state->getColor();
                                        }
                                        return 'gray';
                                    })
                                    ->formatStateUsing(function ($state): string {
                                        if ($state instanceof Status) {
                                            return $state->getLabel();
                                        }
                                        return $state;
                                    })
                                    ->columnSpan(1),
                            ])
                    ])
            ]);
    }

    private function parseTab(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        if (is_string($value) && preg_match('/(\d+)/', $value, $m)) {
            return (int)$m[1];
        }

        return 1;
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->customer)
            ->schema([
                Tabs::make('Tabs')
                    ->persistTabInQueryString('tab')
                    ->activeTab(fn() => $this->selectedTab)
                    ->tabs([
                        Tabs\Tab::make(__('Customer Details'))
                            ->id(1)
                            ->live()
                            ->schema($this->getCustomerDetailsSchema()),
                        Tabs\Tab::make(__('Customer Surveys'))
                            ->id(2)
                            ->live()
                            ->schema($this->getCustomerSurveysSchema()),
                        Tabs\Tab::make(__('Customer Readings'))
                            ->id(3)
                            ->live()
                            ->schema($this->getCustomerReadingsSchema())
                            ->statePath('newReadingData'),
                        Tabs\Tab::make(__('Customer Subscriptions'))
                            ->id(4)
                            ->schema($this->getCustomerSubscriptionsSchema())
                            ->statePath('newSubscriptionData'),
                        Tabs\Tab::make(__('Customer Balance'))
                            ->id(5)
                            ->schema($this->getCustomerBalanceSchema())
                            ->statePath('newBalanceData'),
                        Tabs\Tab::make(__('Customer Programs'))
                            ->id(6)
                            ->schema($this->getCustomerProgramsSchema())
                            ->statePath('programData'),
                    ])
            ])
            ->statePath('data');
    }

    protected function getCustomerDetailsSchema(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(4)
                ->schema([
                    TextInput::make('user.name')
                        ->label(__('Name'))
                        ->columnSpan(4),
                    DatePicker::make('date_of_birth')
                        ->columnSpan(2)
                        ->label(__('Date of Birth')),
                    TextInput::make('user.phone')
                        ->columnSpan(2)
                        ->label(__('Phone')),
                    Select::make('country_id')
                        ->label(__('Country'))
                        ->required()
                        ->searchable()
                        ->options(Country::get()->pluck('name', 'id'))
                        ->default(Country::whereTranslation('name', 'Palestine')->first()->id),
                    Select::make('governorate_id')
                        ->label(__('Governorate'))
                        ->required()
                        ->searchable()
                        ->options(Governorate::get()->pluck('name', 'id')),
                    Select::make('city_id')
                        ->label(__('City'))
                        ->required()
                        ->searchable()
                        ->options(City::get()->pluck('name', 'id')),
                    Select::make('district_id')
                        ->label(__('District'))
                        ->required()
                        ->searchable()
                        ->options(District::get()->pluck('name', 'id')),
                    Textarea::make('address')
                        ->columnSpanFull()
                        ->rows(4)
                        ->label(__('Address')),

                    Actions::make([
                        Action::make('customer_save')
                            ->label(__('Save'))
                            ->action('saveCustomerDetails')
                    ])
                ])
        ];
    }

    protected function getCustomerSurveysSchema(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(4)
                ->schema([
                    View::make('customer::livewire.pages.customer.details.views.surveys-container')
                        ->columnSpanFull()
                        ->viewData([
                            'customer' => $this->customer,
                            'surveys' => Survey::first()
                        ])
                ])
        ];
    }

    protected function getCustomerReadingsSchema(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(6)
                ->schema([
                    TextInput::make('weight')
                        ->label(__('Weight'))
                        ->columns(1)
                        ->numeric(),
                    TextInput::make('fats')
                        ->label(__('Fats'))
                        ->columns(1)
                        ->numeric(),
                    TextInput::make('muscles')
                        ->label(__('Muscles'))
                        ->columns(1)
                        ->numeric(),
                    TextInput::make('salts')
                        ->label(__('Salts'))
                        ->columns(1)
                        ->numeric(),
                    TextInput::make('water')
                        ->label(__('Water'))
                        ->columns(1)
                        ->numeric(),
                    TextInput::make('bmi')
                        ->label(__('BMI'))
                        ->columns(1)
                        ->numeric(),
                    Actions::make([
                        Action::make('save_reading')
                            ->label(__('Save Reading'))
                            ->action('saveReading')
                    ])
                    ->columnSpanFull(),
                    View::make('customer::tables.clinic.customer-readings-content')
                        ->columnSpanFull()
                        ->viewData([
                            'stats' => $this->statistics,
                            'readings' => $this->customer->readings()->latest()->get()
                        ])
                ])
        ];
    }

    protected function getCustomerSubscriptionsSchema(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(2)
                ->schema([
                    Select::make('plan_id')
                        ->columnSpanFull()
                        ->label(__('Plan'))
                        ->required()
                        ->options(Plan::get()->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $price = Plan::find($state)->price ?? null;
                            $duration = Plan::find($state)->duration ?? null;
                            $today = Carbon::today();
                            $set('start_date', $today->toDateString());
                            if ($duration) {
                                $end = $today->copy()->addDays(max(0, $duration - 1));
                                $set('end_date', $end->toDateString());
                            } else {
                                $set('end_date', null);
                            }
                            $set('paid_amount', $price);
                        }),
                    Placeholder::make('duration_info')
                        ->columnSpanFull()
                        ->label(__('Subscription Duration'))
                        ->content(function (Get $get) {
                            $planId = $get('plan_id');
                            if (!$planId) {
                                return __('Select a plan to see duration');
                            }
                            $plan = Plan::find($planId);
                            return $plan ? __(':days days', ['days' => $plan->duration]) : null;
                        }),
                    DatePicker::make('start_date')
                        ->columnSpan(1)
                        ->required()
                        ->label(__('Start Date')),
                    DatePicker::make('end_date')
                        ->columnSpan(1)
                        ->required()
                        ->label(__('End Date')),
                    TextInput::make('paid_amount')
                        ->columnSpanFull()
                        ->label(__('Paid Amount'))
                        ->required()
                        ->numeric()
                        ->prefix('₪')
                        ->extraAttributes(['class' => 'money-xl'])
                        ->extraInputAttributes([
                            'class' => 'money-xl',
                            'inputmode' => 'decimal',
                        ]),
                    Actions::make([
                        Action::make('save_subscription')
                            ->label(__('Add subscription'))
                            ->action('saveSubscription')
                    ]),
                    Livewire::make(Subscription::class, ['customer' => $this->customer])
                        ->columnSpanFull()
                        ->live()
                ])
        ];
    }

    protected function getCustomerProgramsSchema(): array
    {
        return [
            Select::make('program_id')
                ->label(__('Program'))
                ->searchable()
                ->live()
                ->options(Program::get()->pluck('name', 'id'))
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $program = Program::with(['days.dayMeals.mealItems'])->find($state);
                        if ($program) {
                            $daysData = $program->days->map(function ($day) {
                                return [
                                    'day_number' => $day->day_number,
                                    'dayMeals' => $day->dayMeals->map(function ($meal) {
                                        return [
                                            'type_of_meal_id' => $meal->type_of_meal_id,
                                            'mealItems' => $meal->mealItems->map(function ($item) {
                                                return [
                                                    'food_item_id' => $item->food_item_id,
                                                    'quantity' => $item->quantity,
                                                    'serving_size_id' => $item->serving_size_id,
                                                    'description' => $item->description,
                                                ];
                                            })->toArray(),
                                        ];
                                    })->toArray(),
                                ];
                            })->toArray();
                            $set('days', $daysData);
                        } else {
                            $set('days', []);
                        }
                    }
                }),
            Repeater::make('days')
                ->label('أيام البرنامج')
                ->collapsed()
                ->afterStateUpdated(function (callable $set, callable $get, $state) {
                    $days = collect($get('days'))
                        ->values()
                        ->map(function ($row, $i) {
                            $row['day_number'] = $row['day_number'] ?? ($i + 1);
                            return $row;
                        })->all();
                    $set('days', $days);
                })
                ->itemLabel(function (array $state, $component): ?string {
                    $dayNumber = $state['day_number'] ?? null;
                    if (!$dayNumber) {
                        $items = $component->getState();
                        $dayNumber = is_array($items) ? (count($items)) : 1;
                    }
                    return 'اليوم رقم: ' . $dayNumber;
                })
                ->schema([
                    TextInput::make('id')->hidden(),
                    TextInput::make('day_number')
                        ->hidden(),
                    Repeater::make('dayMeals')
                        ->label('الوجبات')
                        ->schema([
                            \Filament\Forms\Components\Grid::make(6)
                                ->schema([
                                    Select::make('type_of_meal_id')
                                        ->label('نوع الوجبة')
                                        ->options(ProgramTypeOfMeal::with('translations')->get()->mapWithKeys(function ($meal) {
                                            return [$meal->id => $meal->name ?? 'غير محدد'];
                                        }))
                                        ->required()
                                        ->columnSpan(1),
                                    Repeater::make('mealItems')
                                        ->label('الأصناف')
                                        ->schema([
                                            Select::make('food_item_id')
                                                ->label('الصنف')
                                                ->options(function () {
                                                    return FoodItem::with('translations')->get()->mapWithKeys(function ($item) {
                                                        return [$item->id => $item->name ?? 'غير محدد'];
                                                    });
                                                })
                                                ->searchable()
                                                ->reactive()
                                                ->afterStateUpdated(function ($state, callable $set) {
                                                    $set('serving_size_id', null);
                                                })
                                                ->required(),
                                            TextInput::make('quantity')
                                                ->label('الكمية')
                                                ->numeric()
                                                ->required(),
                                            Select::make('serving_size_id')
                                                ->label('وحدة القياس')
                                                ->options(function (callable $get) {
                                                    $foodItemId = $get('food_item_id');
                                                    if (!$foodItemId) {
                                                        return [];
                                                    }
                                                    return ServingSize::where('food_item_id', $foodItemId)
                                                        ->with('translations')
                                                        ->get()
                                                        ->mapWithKeys(function ($servingSize) {
                                                            return [$servingSize->id => $servingSize->name ?? 'غير محدد'];
                                                        });
                                                })
                                                ->searchable()
                                                ->disabled(fn(callable $get): bool => !$get('food_item_id'))
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
            Actions::make([
                Action::make('save_program')
                    ->label('حفظ البرنامج')
                    ->action('saveProgram')
                    ->color('success'),
                Action::make('open_save_as_template')
                    ->label('حفظ كقالب جديد')
                    ->color('primary')
                    ->form([
                        TextInput::make('program_id')
                            ->label('asd')
                    ])
            ]),
            View::make('customer::livewire.pages.customer.details.tables.programs-container')
                ->columnSpanFull()
                ->viewData([
                    'customer' => $this->customer
                ])
        ];
    }



    public function assignSimple(int $programId, int $customerId)
    {
        return DB::transaction(function () use ($programId, $customerId) {
            $program = Program::with(['days.dayMeals.mealItems'])->findOrFail($programId);

            $pc = ProgramCustomer::create([
                'program_id'  => $program->id,   // مرجع اختياري للقالب
                'customer_id' => $customerId,
                'start_date'  => now(),
                'status'      => CustomerProgramStatus::ACTIVE->value,
                'created_by'  => auth()->id(),
            ]);

            // 2) انسخ الأيام -> الوجبات -> العناصر
            $program->days->each(function ($day) use ($pc) {
                $newDay = $day->replicate();                 // ينسخ كل الأعمدة ما عدا الـ id
                $newDay->program_id = null;                  // افصل عن القالب (اختياري)
                $newDay->program_customer_id = $pc->id;      // اربط بنسخة العميل
                $newDay->save();

                $day->dayMeals->each(function ($meal) use ($newDay) {
                    $newMeal = $meal->replicate();
                    $newMeal->program_day_id = $newDay->id;
                    $newMeal->save();

                    $meal->mealItems->each(function ($item) use ($newMeal) {
                        $newItem = $item->replicate();
                        $newItem->day_meal_id = $newMeal->id;
                        $newItem->save();
                    });
                });
            });

            return $pc;
        });
    }

    public function saveAsTemplate(array $data): void
    {

        $name = $data['template_name'] ?? null;
        if (! $name) {
            Toaster::error('حدد اسم القالب.');
            return;
        }

        $pc = ProgramCustomer::with(['days.dayMeals.mealItems'])
            ->where('customer_id', $this->customer->id)
            ->latest('id')
            ->first();

        if (! $pc) {
            Toaster::error('لا يوجد برنامج محفوظ لهذا العميل.');
            return;
        }

        DB::transaction(function () use ($name, $pc) {
            $program = Program::create([
                'name'        => $name,
                'description' => 'Generated from customer #' . $pc->customer_id,
                'is_active'   => 1,
                'created_by'  => auth()->id(),
            ]);

            foreach ($pc->days as $day) {
                $newDay = $day->replicate();
                $newDay->program_id = $program->id;      // اربطه بالقالب الجديد
                $newDay->program_customer_id = null;     // افصل عن نسخة العميل
                $newDay->save();

                foreach ($day->dayMeals as $meal) {
                    $newMeal = $meal->replicate();
                    $newMeal->program_day_id = $newDay->id;
                    $newMeal->save();

                    $children = $meal->mealItems ?? $meal->items ?? collect();
                    foreach ($children as $item) {
                        $newItem = $item->replicate();
                        $newItem->program_day_meal_id = $newMeal->id;
                        $newItem->save();
                    }
                }
            }
        });

        Toaster::success('تم إنشاء القالب الجديد بنجاح.');
    }

    public function saveProgram(): void
    {
        $programId  = $this->data['programData']['program_id'] ?? null;
        $daysInput  = $this->data['programData']['days'] ?? [];
        $customerId = $this->customer->id;

        if (! $programId) {
            Toaster::error('الرجاء اختيار برنامج أولاً');
            return;
        }

        DB::beginTransaction();
        try {
            $pc = ProgramCustomer::Create(
                [
                    'customer_id' => $customerId,
                    'program_id'  => $programId,
                    'start_date'  => now(),
                    'status'      => \Modules\Clinic\Enums\CustomerProgramStatus::ACTIVE->value,
                    'created_by'  => auth()->id(),
                ]
            );

            // إذا أول مرة ولسه ما فيه أيام منسوخة — انسخ القالب
            if ($pc->wasRecentlyCreated && empty($daysInput)) {
                $this->assignSimple($programId, $customerId);
                DB::commit();
                Toaster::success('تم نسخ البرنامج للعميل بنجاح');
                return;
            }

            // 2) تحديث (Upsert) الأيام/الوجبات/الأصناف بناءً على البيانات في الفورم
            // لنفترض أن كل عنصر في الـ repeater يحمل 'id' عند التعديل. إن لم يوجد => إنشاء جديد.
            $existingDayIds = $pc->days()->pluck('id')->all();
            $seenDayIds = [];

            foreach ($daysInput as $d) {
                $dayId = Arr::get($d, 'id');
                $payloadDay = [
                    'day_number' => Arr::get($d, 'day_number'),
                    'created_by' => auth()->id(),
                ];

                if ($dayId) {
                    $day = $pc->days()->whereKey($dayId)->first();
                    if ($day) {
                        $day->update($payloadDay);
                    } else {
                        // id غير صالح، أنشئ جديد
                        $day = $pc->days()->create($payloadDay);
                    }
                } else {
                    $day = $pc->days()->create($payloadDay);
                }
                $seenDayIds[] = $day->id;

                // meals
                $existingMealIds = $day->dayMeals()->pluck('id')->all();
                $seenMealIds = [];

                foreach (Arr::get($d, 'dayMeals', []) as $m) {
                    $mealId = Arr::get($m, 'id');
                    $payloadMeal = [
                        'type_of_meal_id' => Arr::get($m, 'type_of_meal_id'),
                        'notes'           => Arr::get($m, 'notes'),
                        'created_by'      => auth()->id(),
                    ];

                    if ($mealId) {
                        $meal = $day->dayMeals()->whereKey($mealId)->first();
                        if ($meal) {
                            $meal->update($payloadMeal);
                        } else {
                            $meal = $day->dayMeals()->create($payloadMeal);
                        }
                    } else {
                        $meal = $day->dayMeals()->create($payloadMeal);
                    }
                    $seenMealIds[] = $meal->id;

                    // items
                    $existingItemIds = $meal->mealItems()->pluck('id')->all();
                    $seenItemIds = [];

                    foreach (Arr::get($m, 'mealItems', []) as $it) {
                        $itemId = Arr::get($it, 'id');
                        $payloadItem = [
                            'food_item_id'    => Arr::get($it, 'food_item_id'),
                            'serving_size_id' => Arr::get($it, 'serving_size_id'),
                            'quantity'        => Arr::get($it, 'quantity'),
                            'description'     => Arr::get($it, 'description'),
                            'created_by'      => auth()->id(),
                        ];

                        if ($itemId) {
                            $item = $meal->mealItems()->whereKey($itemId)->first();
                            if ($item) {
                                $item->update($payloadItem);
                            } else {
                                $item = $meal->mealItems()->create($payloadItem);
                            }
                        } else {
                            $item = $meal->mealItems()->create($payloadItem);
                        }
                        $seenItemIds[] = $item->id;
                    }

//                    $toDeleteItems = array_diff($existingItemIds, $seenItemIds);
//                    if ($toDeleteItems) {
//                        $meal->mealItems()->whereIn('id', $toDeleteItems)->delete();
//                    }
                }

//                // احذف الوجبات المحذوفة
//                $toDeleteMeals = array_diff($existingMealIds, $seenMealIds);
//                if ($toDeleteMeals) {
//                    $day->dayMeals()->whereIn('id', $toDeleteMeals)->delete();
//                }
            }

//            // احذف الأيام المحذوفة
//            $toDeleteDays = array_diff($existingDayIds, $seenDayIds);
//            if ($toDeleteDays) {
//                $pc->days()->whereIn('id', $toDeleteDays)->delete();
//            }

            DB::commit();
            $this->dispatch('programSaved');
            Toaster::success('تم حفظ/تعديل برنامج العميل بنجاح');
        } catch (\Throwable $e) {
            DB::rollBack();
            Toaster::error('حدث خطأ أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function calculateStatistics(): void
    {
        $readings = $this->customer->readings();
        if ($readings->count() < 1) {
            $this->statistics = [];
            return;
        }

        $firstReading = $this->customer->readings()->orderBy('id', 'asc')->first();
        $lastReading = $this->customer->readings()->orderBy('id', 'desc')->first();
        $previousReading = $this->customer->readings()->orderBy('id', 'desc')->skip(1)->first();

        $this->statistics = [
            'weight' => [
                'current' => $lastReading->weight,
                'previous' => $previousReading?->weight,
                'first' => $firstReading->weight,
                'total_diff' => $lastReading->weight - $firstReading->weight,
            ],
            'fats' => [
                'current' => $lastReading->fats,
                'previous' => $previousReading?->fats,
                'first' => $firstReading->fats,
                'total_diff' => $lastReading->fats - $firstReading->fats,
            ],
            'muscles' => [
                'current' => $lastReading->muscles,
                'previous' => $previousReading?->muscles,
                'first' => $firstReading->muscles,
                'total_diff' => $lastReading->muscles - $firstReading->muscles,
            ],
            'salts' => [
                'current' => $lastReading->salts,
                'previous' => $previousReading?->salts,
                'first' => $firstReading->salts,
                'total_diff' => $lastReading->salts - $firstReading->salts,
            ],
            'water' => [
                'current' => $lastReading->water,
                'previous' => $previousReading?->water,
                'first' => $firstReading->water,
                'total_diff' => $lastReading->water - $firstReading->water,
            ],
            'bmi' => [
                'current' => $lastReading->bmi,
                'previous' => $previousReading?->bmi,
                'first' => $firstReading->bmi,
                'total_diff' => $lastReading->bmi - $firstReading->bmi,
            ],
        ];
    }

    public function getSubscriptions()
    {
        if ($this->subscriptions === null) {
            $this->subscriptions = $this->customer->subscriptions()->latest()->get();
        }
        return $this->subscriptions;
    }

    public function removeReading($id): void
    {
        $this->customer->readings()->where('id', $id)->delete();
        $this->calculateStatistics();
        Toaster::success('Reading deleted successfully');
    }

    public function saveCustomerDetails()
    {
        $userData = $this->data['user'] ?? [];
        unset($userData['created_at'], $userData['updated_at']);

        $this->customer->user()->update($userData);

        $customerData = $this->data;
        unset($customerData['user'], $customerData['created_at'], $customerData['updated_at']);

        $this->customer->update($customerData);
        Toaster::success('Customer details saved successfully');
    }

    public function saveSubscription()
    {
        // --- START: Logic for stacking subscriptions ---

        // 1. Fetch the plan to determine its duration.
        $plan = Plan::find($this->data['newSubscriptionData']['plan_id'] ?? null);

        if (!$plan) {
            Toaster::danger('The selected plan could not be found.');
            return;
        }

        // 2. Find the end date of the last active subscription for this customer.
        $lastEndDateString = $this->customer->subscriptions()
            ->where('status', \Modules\Subscription\Enums\Status::ACTIVE)
            ->max('end_date');

        // 3. Determine the correct start date for the new subscription.
        $proposedStartDate = Carbon::parse($this->data['newSubscriptionData']['start_date'] ?? 'now')->startOfDay();
        $actualStartDate = $proposedStartDate;

        if ($lastEndDateString) {
            $lastEndDate = Carbon::parse($lastEndDateString)->startOfDay();

            if ($lastEndDate->gte($proposedStartDate)) {
                $actualStartDate = $lastEndDate->addDay();
            }
        }

        // 4. Calculate the new end date based on plan duration in DAYS.
        // This logic now assumes the 'duration' column in your 'plans' table always holds the number of days.
        $daysToAdd = (int) ($plan->duration ?? 0);

        if ($daysToAdd <= 0) {
            // Safety check to prevent subscriptions with invalid duration.
            Toaster::danger('The plan duration is invalid.');
            return;
        }

        // We subtract 1 because the start date is the first day of the subscription.
        // e.g., a 1-day subscription starting today, also ends today.
        $actualEndDate = $actualStartDate->copy()->addDays($daysToAdd - 1)->endOfDay();

        // 5. Update your data array with the correctly calculated dates.
        $this->data['newSubscriptionData']['start_date'] = $actualStartDate;
        $this->data['newSubscriptionData']['end_date'] = $actualEndDate;

        // --- END: Logic for stacking subscriptions ---

        $this->data['newSubscriptionData']['created_by'] = auth()->id();
        $subscription = $this->customer->subscriptions()->create($this->data['newSubscriptionData']);
        $paidAmount = (float) $subscription['paid_amount'];
        app(TransactionLoggerInterface::class)->log(
            sourceDocument: $subscription,
            flow: TransactionFlow::INCOME->value,
            amount: $paidAmount,
            sourceTypeEnum: SubscriptionTransactionType::NewSubscription,
            description: __('New subscription payment for plan :plan', ['plan' => $subscription->plan->name ?? 'N/A']),
            relatedEntity: $this->customer,
        );
        $this->dispatch('subscriptionSaved');
        Toaster::success('Subscription saved successfully');
    }

    protected function getCustomerBalanceSchema(): array
    {
        return [
            \Filament\Forms\Components\Grid::make(3)
                ->schema([
                    TextInput::make('amount')
                        ->label(__('Deposit Amount'))
                        ->required()
                        ->numeric()
                        ->prefix('₪'),
                    Select::make('payment_method')
                        ->label(__('Payment Method'))
                        ->required()
                        ->options(PaymentMethod::options())
                        ->live()
                        ->searchable(),
                    DatePicker::make('date')
                        ->label(__('Payment Date'))
                        ->default(now()->format('Y-m-d'))
                        ->required(),
                    TextInput::make('reference_no')
                        ->label(__('Reference Number'))
                        ->columnSpanFull()
                        ->nullable()
                        ->visible(fn (Get $get) => $get('payment_method') == PaymentMethod::BANK_TRANSFER->value || $get('payment_method') == PaymentMethod::CREDIT_CARD->value),
                    Textarea::make('notes')
                        ->columnSpanFull()
                        ->label(__('Notes')),

                    Actions::make([
                        Action::make('save_deposit')
                            ->label(__('Add Deposit'))
                            ->action('saveDeposit') // <== الدالة الجديدة التي سننشئها
                            ->color('success')
                    ]),

                    Livewire::make(Transaction::class, ['customer' => $this->customer])
                        ->columnSpanFull()
                        ->live()
                ])
        ];
    }

    public function saveReading()
    {
        $this->data['newReadingData']['created_by'] = auth()->id();
        $this->customer->readings()->create($this->data['newReadingData']);
        $this->calculateStatistics();
        Toaster::success('Reading saved successfully');
    }

    public function saveDeposit()
    {
        $data = $this->data['newBalanceData'];
        $amount = (float) ($data['amount'] ?? 0);
        $notes = $data['notes'] ?? null;
        $paymentMethod = $data['payment_method'] ?? null;
        $referenceNo = $data['reference_no'] ?? null;
        $depositDate = $data['date'] ?? now();

        if ($amount <= 0) {
            Toaster::error(__('Deposit amount must be greater than zero.'));
            return;
        }

        app(TransactionLoggerInterface::class)->log(
          sourceDocument: $this->customer,
          flow: TransactionFlow::INCOME->value,
          amount:$amount,
          paymentMethod: $paymentMethod,
          referenceNo: $referenceNo,
          sourceTypeEnum: TransactionType::PrepaymentDeposit,
          description: __('Customer added a prepayment deposit.') . " " . $notes,relatedEntity: $this->customer,
        );

        $this->dispatch('saveDeposit');

        Toaster::success('Deposit added successfully');
    }

    public function render()
    {
        return view('customer::livewire.pages.customer.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Customers List'), 'url' => route('customers.index')],
                ['title' => __('Customers Details'), 'url' => route('customers.details', ['customer' => $this->data['id']])],
            ]
        ]);
    }
}
