<?php

namespace Modules\Core\Livewire\Pages\Home;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid; // لا تنس استدعاء Grid
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Modules\Clinic\Livewire\Pages\Appointment\Index as AppointmentIndex;
use Nwidart\Modules\Facades\Module;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithInfolists;
    use InteractsWithForms;

    public function mount()
    {
        // ...
    }

    public function infolist($infolist): Infolist
    {
        return $infolist
            ->state([])
            ->schema([
                Section::make('الاستقبال')
                    ->description('الوصول السريع لأقسام النظام')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Section::make('العملاء')
                                    ->icon('heroicon-m-users')
                                    ->description('إدارة سجلات العملاء')
                                    ->compact()
                                    ->schema([
                                        Actions::make([
                                            Action::make('list_customers')
                                                ->label('قائمة العملاء')
                                                ->icon('heroicon-m-list-bullet')
                                                // نتحقق أولاً هل الراوت موجود أم لا لتجنب الخطأ
                                                ->url(fn () => Route::has('customers.index') ? route('customers.index') : '#')
                                                ->color('primary'),

                                            Action::make('add_customer')
                                                ->label('إضافة عميل جديد')
                                                ->icon('heroicon-m-plus-circle')
                                                ->url(fn () => Route::has('customers.add') ? route('customers.add') : '#')
                                                ->color('success'),
                                        ])->fullWidth(),
                                    ])
                                    ->columnSpan(1),

                                Section::make('المواعيد')
                                    ->icon('heroicon-m-calendar')
                                    ->description('إدارة المواعيد')
                                    ->compact()
                                    ->schema([
                                        Actions::make([
                                            Action::make('list_appointments')
                                                ->label('قائمة المواعيد')
                                                ->icon('heroicon-m-list-bullet')
                                                // هنا كان سبب الخطأ الرئيسي
                                                ->url(fn () => Route::has('appointments.index') ? route('appointments.index') : '#')
                                                ->color('primary'),
                                        ])->fullWidth(),
                                    ])
                                    ->columnSpan(1),

                                Section::make('المالية')
                                    ->icon('heroicon-m-calendar')
                                    ->description('إدارة الحسابات المالية')
                                    ->compact()
                                    ->schema([
                                        Actions::make([
                                            Action::make('add_payment')
                                                ->label('اضافة دفعة')
                                                ->icon('heroicon-m-list-bullet')
                                                ->url(fn () => Route::has('appointments.index') ? route('appointments.index') : '#')
                                                ->color('primary'),

                                            Action::make('statement')
                                                ->label('كشف حساب')
                                                ->icon('heroicon-m-plus-circle')
                                                ->url('#')
                                                ->color('success'),
                                        ])->fullWidth(),
                                    ])
                                    ->columnSpan(1),

                                // تنبيه: تأكد أن كلاس AppointmentIndex موجود أيضاً وإلا سيظهر خطأ آخر
                                // يفضل إخفاؤه أو التحقق من وجوده إذا كان تابعاً للموديول
                                Livewire::make(AppointmentIndex::class)
                                    ->columnSpanFull()
                                    ->visible(fn () => Module::has('Clinic') && Module::isEnabled('Clinic')),
                            ]),
                    ])
                    // الشرط الرئيسي لظهور السكشن بالكامل
                    ->visible(fn () => Module::has('Clinic') && Module::isEnabled('Clinic')),            ]);
    }

    public function render()
    {
        return view('core::livewire.pages.home.index')->layout(AppLayout::class);
    }
}
