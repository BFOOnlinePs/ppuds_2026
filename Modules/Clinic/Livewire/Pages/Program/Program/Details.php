<?php

namespace Modules\Clinic\Livewire\Pages\Program\Program;

use App\View\Components\AppLayout;
use Exception;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Clinic\Entities\FoodItem;
use Modules\Clinic\Entities\Program;
use Modules\Clinic\Entities\ProgramTypeOfMeal;
use Modules\Clinic\Entities\ServingSize;
use Modules\Core\Filament\Forms\Components\Textarea;

class Details extends Component implements HasForms
{
    use InteractsWithForms;

    public Program $program;
    public ?array $data = [];

    public function mount(Program $program): void
    {
        $this->program = $program;
        // تحميل العلاقات لعرض البيانات الموجودة مسبقاً
        $this->program->load('days.dayMeals.mealItems');
        $this->form->fill($this->program->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->program)
            ->schema([
                Repeater::make('days')
                    ->label('أيام البرنامج')
                    ->relationship()
                    // ✅ التعديل الجوهري 1: تحديد عمود الترتيب
                    // سيقوم Filament تلقائياً بإعادة ترقيم day_number (1, 2, 3...) عند الحفظ
                    ->orderColumn('day_number')
                    ->reorderable(true) // السماح بالسحب والإفلات للترتيب
                    ->reorderableWithButtons() // إظهار أزرار الصعود والهبوط
                    ->cloneable() // النسخ الآن آمن وسيعيد الترقيم تلقائياً
                    ->collapsed()
                    // ... داخل تعريف الـ Repeater

                    ->itemLabel(function (array $state, $uuid, $component) {

                        // 1. نجلب كل العناصر الموجودة حالياً في الـ Repeater
                        // $component هنا هو الـ Repeater نفسه
                        $items = $component->getState() ?? [];

                        // 2. نحصل على مفاتيح المصفوفة (وهي الـ UUIDs)
                        $keys = array_keys($items);

                        // 3. نبحث عن ترتيب الـ uuid الحالي داخل المصفوفة
                        $index = array_search($uuid, $keys);

                        // 4. نزيد 1 لأن المصفوفة تبدأ من 0
                        $number = ($index !== false) ? ($index + 1) : count($items);

                        return 'اليوم رقم: ' . $number;
                    })

                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    })
                    ->schema([
                        // ✅ التعديل الجوهري 2: إخفاء الحقل وتركه للنظام
                        TextInput::make('day_number')
                            ->hidden()
                            ->dehydrated() // ضروري ليتم إرسال القيمة وتحديثها من قبل orderColumn
                            ->numeric(),

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
                                            ->options(ProgramTypeOfMeal::with('translations')->get()->pluck('name', 'id'))
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
                                                    ->options(function () {
                                                        return FoodItem::with('translations')->get()->pluck('name', 'id');
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
                                                            ->pluck('name', 'id');
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
            ])
            ->statePath('data');
    }

    public function save()
    {
        // التحقق من صحة البيانات
        $this->form->validate();

        // 1. تحديث بيانات البرنامج الأساسية
        $this->program->update($this->form->getStateOnly(['name', 'description'])); // عدل الحقول حسب الحاجة

        // 2. حفظ العلاقات (الأيام والوجبات) وتطبيق الترتيب الجديد
        // هذه الدالة ضرورية جداً عند استخدام orderColumn
        $this->form->model($this->program)->saveRelationships();

        Toaster::success('تم تحديث البرنامج وترتيب الأيام بنجاح!');

        // إعادة تحميل البيانات لتحديث أرقام الأيام في الواجهة
        $this->mount($this->program);
    }

    public function render()
    {
        return view('clinic::livewire.pages.program.program.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('الرئيسية'), 'url' => route('home')],
                ['title' => __('قائمة البرامج'), 'url' => route('program.programs.index')],
                ['title' => __('إدارة البرنامج: ') . $this->program->name, 'url' => '#'],
            ]
        ]);
    }
}
