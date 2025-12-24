<?php

namespace Modules\Coupon\Livewire\Pages\Coupon;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Modules\Coupon\Entities\Coupon;
use Modules\Coupon\Enums\CouponType;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Coupon $record;
    public ?array $data = [];

    public function mount($coupon)
    {
        // جلب الكوبون وتعبئة الفورم بالبيانات
        $this->record = Coupon::findOrFail($coupon);
        $this->form->fill($this->record->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->record)
            ->schema([
                Grid::make(3)
                    ->schema([
                        Section::make()
                            ->columnSpan(2)
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextInput::make('code')
                                            ->required()
                                            ->label(__('Code'))
                                            ->columnSpanFull()
                                            // هام جداً: تجاهل السجل الحالي عند التحقق من التكرار
                                            ->unique(Coupon::class, 'code', ignoreRecord: true),

                                        Select::make('type')
                                            ->label(__('Type'))
                                            ->options(CouponType::options())
                                            ->searchable()
                                            ->columnSpan(2)
                                            ->required(),

                                        TextInput::make('value')
                                            ->required()
                                            ->label(__('Value'))
                                            ->numeric()
                                            ->columnSpan(2),

                                        TextInput::make('max_uses')
                                            ->required()
                                            ->label(__('Max Uses'))
                                            ->numeric()
                                            ->columnSpan(2),

                                        TextInput::make('min_order_amount')
                                            ->required()
                                            ->label(__('Min Order Amount'))
                                            ->numeric()
                                            ->columnSpan(2),
                                    ])
                            ]),

                        Section::make()
                            ->columnSpan(1)
                            ->schema([
                                TextInput::make('starts_at')
                                    ->label(__('Starts At'))
                                    ->type('datetime-local')
                                    ->columnSpan(1)
                                    ->required(),

                                TextInput::make('expires_at')
                                    ->label(__('Expires At'))
                                    ->type('datetime-local')
                                    ->columnSpan(1)
                                    ->required(),
                            ])
                    ]),
            ])
            ->statePath('data');
    }

    public function save()
    {
        // التحقق من صحة البيانات بناءً على الـ schema
        $this->validate();

        // تحديث السجل
        $this->record->update($this->data);

        // إعادة التوجيه لقائمة الكوبونات
        $this->redirectRoute('coupons.index');
    }

    public function render()
    {
        // تأكد من مسار ملف الـ blade، قمت بتعديله ليتناسب مع الكوبونات
        // غالباً ستستخدم نفس ملف blade الخاص بالإضافة إذا كان موحداً، أو ملف خاص بالتعديل
        return view('coupon::livewire.pages.coupon.edit')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Coupons List'), 'url' => route('coupons.index')],
                ['title' => __('Edit Coupon'), 'url' => route('coupons.edit', $this->record->id)],
            ]
        ]);
    }
}