<?php

namespace Modules\PPUDS\Livewire\Pages\ChatMessage;

use App\View\Components\AppLayout;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section as FormSection;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Registration;

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
                        ->label(__('New Message')) // محادثة / رسالة جديدة
                        ->icon('heroicon-o-chat-bubble-left-ellipsis') // غيرت الأيقونة لتناسب الرسائل
                        ->color('primary')
                        ->form([
                            FormSection::make(__('New Conversation')) // محادثة جديدة بدلاً من تفاصيل التسجيل
                                ->description(__('Please select the chat type and choose the recipients.')) // وصف مناسب للرسائل
                                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                ->schema([

                                    Radio::make('type')
                                        ->label(__('Chat Type')) // نوع المحادثة
                                        ->options([
                                            'single' => __('Single Student'),
                                            'group' => __('Students Group'),
                                        ])
                                        ->default('single')
                                        ->inline()
                                        ->inlineLabel(false)
                                        ->live()
                                        ->columnSpanFull(),

                                    FormGrid::make(2)
                                        ->schema([

                                            Select::make('student_id')
                                                ->label(__('Select Student'))
                                                ->prefixIcon('heroicon-m-user')
                                                ->options(function () {
                                                    $user = auth()->user();
                                                    if ($user->hasRole('Student')) {
                                                        $registration = Registration::where('student_id', $user->id)->latest()->first();
                                                        if ($registration) {
                                                            return User::whereIn('id', [$registration->supervisor_id])->pluck('name', 'id');
                                                        }
                                                    }

                                                    return [];
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn (Get $get) => $get('type') === 'single')
                                                ->required(fn (Get $get) => $get('type') === 'single')
                                                ->columnSpanFull(),

                                            Select::make('student_ids')
                                                ->label(__('Select Students Group'))
                                                ->prefixIcon('heroicon-m-users')
                                                ->options(function () {
                                                    $user = auth()->user();
                                                    if ($user->hasRole('Student')) {
                                                        $registration = Registration::where('student_id', $user->id)->latest()->first();
                                                        if ($registration) {
                                                            return User::whereIn('id', [$registration->supervisor_id])->pluck('name', 'id');
                                                        }
                                                    }

                                                    return [];
                                                })
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn (Get $get) => $get('type') === 'group')
                                                ->required(fn (Get $get) => $get('type') === 'group')
                                                ->columnSpanFull(),

                                            TextInput::make('name')
                                                ->label(__('Group Name'))
                                                ->prefixIcon('heroicon-m-user-group')
                                                ->placeholder(__('e.g., Team Alpha'))
                                                ->visible(fn (Get $get) => $get('type') === 'group')
                                                ->required(fn (Get $get) => $get('type') === 'group')
                                                ->columnSpan(1),

                                            TextInput::make('description')
                                                ->label(__('Group Description'))
                                                ->prefixIcon('heroicon-m-document-text')
                                                ->placeholder(__('Brief description of the group...'))
                                                ->visible(fn (Get $get) => $get('type') === 'group')
                                                ->columnSpan(1),

                                        ]),
                                ])
                                ->collapsible(),
                        ])
                        ->action(function (array $data) {

                            $currentUser = auth()->user();

                            if ($data['type'] == 'single') {
                                $receiver = User::find($data['student_id']);

                                if ($receiver) {
                                    $conversation = $currentUser->createConversationWith($receiver);

                                    return redirect()->route('chat-messages.show', $conversation->id);
                                }
                            } else {
                                $receiver = User::whereIn('id', array_filter($data['student_ids']))->get();

                                if ($receiver) {
                                    $conversation = $currentUser->createGroup(
                                        name: $data['name'] ?? __('Group Chat'), // استخدمت اسم المجموعة المدخل من الفورم
                                        description: $data['description'] ?? __('Group Chat'), // استخدمت الوصف المدخل
                                    );

                                    foreach ($receiver as $participant) {
                                        $conversation->addParticipant($participant);
                                    }
                                }
                            }

                            if (isset($conversation)) {
                                return redirect()->route('chat-messages.show', $conversation->id);
                            }
                        }),
                ]),

                InfolistSection::make(__('Chat Messages'))
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