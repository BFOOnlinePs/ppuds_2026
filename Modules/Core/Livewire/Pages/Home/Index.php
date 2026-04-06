<?php

namespace Modules\Core\Livewire\Pages\Home;

use App\View\Components\AppLayout;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Modules\Clinic\Livewire\Pages\Appointment\Index as AppointmentIndex;
use Modules\PPUDS\Entities\Announcement;
use Nwidart\Modules\Facades\Module;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Modules\Core\Livewire\Pages\Home\Widget\CalendarWidget;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithInfolists;
    use InteractsWithForms;

    public function mount()
    {
        // $userRoles = auth()->user()->getRoleNames();

        // $announcements = Announcement::whereJsonContains('target_roles', $userRoles)->get();

        // dd($announcements);
    }

    public function getAnnouncements(): array
    {
        $roles = auth()->user()->getRoleNames(); // ترجع Collection من Spatie

        return Announcement::active()
            ->where(fn($query) => $roles->each(fn($role) => $query->orWhereJsonContains('target_roles', $role)))
            ->get()
            ->map(fn($ad) => [
                'name'         => $ad->name,
                'content'      => $ad->content,
                'published_at' => $ad->published_at,
            ])
            ->toArray();
    }

    public function infolist($infolist): Infolist
    {
        return $infolist
            ->state([
                'announcements' => $this->getAnnouncements(),
            ])
            ->schema([
                Section::make(__('Announcements'))
                    ->icon('heroicon-m-megaphone')
                    ->description(__('أحدث الإعلانات والتنبيهات الخاصة بك'))
                    ->schema([
                        RepeatableEntry::make('announcements')
                            ->hiddenLabel()
                            ->visible(fn() => !empty($this->getAnnouncements()))
                            ->schema([
                                TextEntry::make('name')
                                    ->label('العنوان')
                                    ->weight('bold')
                                    ->size('lg')
                                    ->color('primary'),

                                TextEntry::make('published_at')
                                    ->label('تاريخ النشر')
                                    ->since()
                                    ->badge(),

                                TextEntry::make('content')
                                    ->label('التفاصيل')
                                    ->html()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->grid(2),

                        TextEntry::make('empty_message')
                            ->hiddenLabel()
                            ->default('لا توجد إعلانات حالياً.')
                            ->color('gray')
                            ->alignCenter()
                            ->visible(fn() => empty($this->getAnnouncements()))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function render()
    {
        return view('core::livewire.pages.home.index')
            ->layout(AppLayout::class);
    }
}
