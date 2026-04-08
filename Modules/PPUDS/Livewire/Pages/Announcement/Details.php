<?php

namespace Modules\PPUDS\Livewire\Pages\Announcement;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Split;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Modules\PPUDS\Entities\Announcement;

class Details extends Component implements HasInfolists, HasForms
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    // تم تغيير الاسم ليكون أنظف وأكثر توافقاً مع معايير لارافل
    public Announcement $announcement;

    // Route Model Binding: لارافل سيقوم بجلب الإعلان تلقائياً
    public function mount(Announcement $announcement)
    {
        $this->announcement = $announcement;
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->announcement)
            ->schema([
                Section::make()
                    ->schema([
                        // 1. قسم الصورة (استخدام الدالة التي أصلحناها سابقاً)
                        ImageEntry::make('image_url')
                            ->hiddenLabel()
                            ->getStateUsing(fn(Announcement $record) => $record->getImageAttribute())
                            ->width('100%')
                            ->height(400)
                            ->extraImgAttributes([
                                'class' => 'object-contain w-full rounded-xl bg-gray-50 border border-gray-100 p-4',
                            ]),

                        // 2. تقسيم الشاشة (محتوى الإعلان يميناً، ومعلومات الإعلان يساراً)
                        Split::make([
                            // العمود الأول: العنوان والمحتوى
                            Grid::make(1)->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight('bold')
                                    ->size(TextEntry\TextEntrySize::Large),

                                TextEntry::make('content')
                                    ->hiddenLabel()
                                    ->html() // ضروري إذا كان المحتوى محفوظ كـ HTML من محرر نصوص
                                    ->prose(), // يعطي تنسيق مقال احترافي للمسافات والخطوط
                            ]),

                            // العمود الثاني: بطاقة جانبية للمعلومات
                            Section::make(__('Announcement Info'))
                                ->grow(false) // لمنع هذا القسم من التمدد (يبقى كشريط جانبي)
                                ->schema([
                                    TextEntry::make('createdBy.name')
                                        ->label(__('Publisher'))
                                        ->icon('heroicon-m-user')
                                        ->default('Admin'),

                                    TextEntry::make('created_at')
                                        ->label(__('Publish Date'))
                                        ->date('F j, Y')
                                        ->icon('heroicon-m-calendar-days'),

                                    TextEntry::make('target_roles') // الفئات المستهدفة
                                        ->label(__('Target Audience'))
                                        ->badge()
                                        ->color('info')
                                        ->icon('heroicon-m-users')
                                        ->visible(fn($state) => filled($state)), // يظهر فقط إذا كان هناك داتا
                                ])
                        ])->from('md') // التقسيم يبدأ من الشاشات المتوسطة وما فوق
                            ->extraAttributes(['class' => 'mt-6']),
                    ])
            ]);
    }

    public function render()
    {
        return view('ppuds::livewire.pages.announcement.details')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Announcement Details'), 'url' => route('announcements.details', $this->announcement->id)],
            ]
        ]);
    }
}
