<?php

namespace Modules\PPUDS\Livewire\Pages\ChatMessage;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Entities\User;
use Modules\Core\Filament\Forms\Components\CreateAction;
use Modules\Core\Filament\Forms\Components\DeleteAction;
use Modules\Core\Filament\Forms\Components\EditAction;
use Modules\Core\Filament\Forms\Components\InfoAction;
use Modules\Core\Filament\Forms\Components\ViewAction;
use Modules\PPUDS\Entities\Survey;
use Wirechat\Wirechat\Livewire\Chat\Chat;

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

                        return redirect()->route('admin.chats.show', $conversation->id);
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
