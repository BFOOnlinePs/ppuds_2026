<?php

namespace Modules\PPUDS\Livewire\Pages\ChatMessage;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Modules\Core\Entities\User;

class Index extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public function infolist(Infolist $infolist): Infolist
{
    return $infolist
        ->record(null)
        ->schema([
            Actions::make([
                Action::make('test')
                    ->label('Test Action')
                    ->icon('heroicon-o-bolt')
                    ->color('primary')
                    ->form([
                        Select::make('student_id')
                            ->label('Student')
                            ->options(
                                User::pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $receiver       = User::find($data['student_id']);
                        $currentUser    = auth()->user();

                        $conversation = $currentUser->createConversationWith($receiver);

                        return redirect()->route('chat-messages.show', $conversation->id);
                    }),
            ]),

            Section::make('Chat Messages')
                ->schema([
                    \Filament\Infolists\Components\Livewire::make('chat')
                        ->view('ppuds::livewire.pages.chat-message.chat')
                        ->columnSpanFull(),
                ]),
        ]);
}

    public function render()
    {
        return view('ppuds::livewire.pages.chat-message.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('Chat Messages'), 'url' => route('chat-messages.index')],
            ],
        ]);
    }
}
