<?php

namespace Modules\Core\Livewire\Pages\Home;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Modules\PPUDS\Entities\Announcement;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Split;
use Livewire\Attributes\Computed;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithInfolists;
    use InteractsWithForms;

    public function mount() {}

    #[Computed]
    public function getAnnouncements()
    {
        return Announcement::active()->get();
    }

    // public function getAnnouncements(): array
    // {
    //     $roles = auth()->user()->getRoleNames();

    //     return Announcement::active()
    //         ->where(fn($query) => $roles->each(fn($role) => $query->orWhereJsonContains('target_roles', $role)))
    //         ->get()
    //         ->map(fn($ad) => [
    //             'name'         => $ad->name,
    //             'content'      => strip_tags($ad->content),
    //             'image'        => $ad->image ?? 'https://ui-avatars.com/api/?name=' . urlencode($ad->name) . '&color=7F9CF5&background=EBF4FF&size=600',
    //             'roles'        => $ad->target_roles ?? [],
    //             // تنسيق التاريخ ليطابق الصورة (مثال: Oct 30, 2023)
    //             'published_at' => $ad->published_at ? $ad->published_at->format('M d, Y') : '',
    //         ])
    //         ->toArray();
    // }

    // public function infolist($infolist): Infolist
    // {
    //     return $infolist
    //         ->state([
    //             'announcements' => $this->getAnnouncements(),
    //         ])
    //         ->schema([
    //             Section::make(__('Announcements'))
    //                 ->icon('heroicon-m-megaphone')
    //                 ->description(__('أحدث الإعلانات والتنبيهات الخاصة بك'))
    //                 ->schema([
    //                     RepeatableEntry::make('announcements')
    //                         ->hiddenLabel()
    //                         ->visible(fn() => !empty($this->getAnnouncements()))
    //                         ->grid(3)
    //                         ->schema([
    //                             ImageEntry::make('image')
    //                                 ->hiddenLabel()
    //                                 ->width('100%')
    //                                 ->columnSpanFull()
    //                                 ->height(180)
    //                                 ->extraImgAttributes([
    //                                     'class' => 'object-cover w-full rounded-t-xl',
    //                                 ])
    //                                 ->columnSpanFull(),

    //                             // 2. عنوان الإعلان
    //                             TextEntry::make('name')
    //                                 ->hiddenLabel()
    //                                 ->weight('bold')
    //                                 ->size('lg')
    //                                 ->columnSpanFull(),

    //                             // 3. تفاصيل الإعلان (مقتطف)
    //                             TextEntry::make('content')
    //                                 ->hiddenLabel()
    //                                 ->color('gray')
    //                                 ->lineClamp(3) // الاكتفاء بـ 3 أسطر فقط
    //                                 ->columnSpanFull(),

    //                             // 4. الفئات المستهدفة (تظهر كوسوم Tags)
    //                             TextEntry::make('roles')
    //                                 ->hiddenLabel()
    //                                 ->badge()
    //                                 ->color('gray')
    //                                 ->columnSpanFull(),

    //                             // 5. الفوتر (التاريخ وأيقونة الإعلان)
    //                             Split::make([
    //                                 TextEntry::make('published_at')
    //                                     ->hiddenLabel()
    //                                     ->weight('bold')
    //                                     ->size('sm'),

    //                                 TextEntry::make('type_label')
    //                                     ->default('إعلان') // نص ثابت كبديل لتصنيف المقال
    //                                     ->hiddenLabel()
    //                                     ->icon('heroicon-m-megaphone') // أيقونة للإعلان
    //                                     ->weight('bold')
    //                                     ->size('sm')
    //                                     ->color('gray')
    //                                     ->alignEnd(),
    //                             ])
    //                                 ->columnSpanFull()
    //                                 ->extraAttributes(['class' => 'mt-4 pt-4 border-t border-gray-100']),
    //                         ]),

    //                     // رسالة في حال عدم وجود إعلانات
    //                     TextEntry::make('empty_message')
    //                         ->hiddenLabel()
    //                         ->default('لا توجد إعلانات حالياً.')
    //                         ->color('gray')
    //                         ->alignCenter()
    //                         ->visible(fn() => empty($this->getAnnouncements()))
    //                         ->columnSpanFull(),
    //                 ]),
    //         ]);
    // }

    public function render()
    {
        return view('core::livewire.pages.home.index')
            ->layout(AppLayout::class);
    }
}
