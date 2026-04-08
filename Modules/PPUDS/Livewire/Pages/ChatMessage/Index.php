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

    /**
     * دالة مساعدة لجلب قائمة المستخدمين المتاحين للمحادثة بناءً على الصلاحية
     */
    private function getAvailableContacts(): array
    {
        $user = auth()->user();
        $userIds = [];

        if ($user->hasRole('Student')) {
            // 1. جلب أحدث تسجيل للطالب
            $registration = Registration::where('student_id', $user->id)->latest()->first();

            if ($registration) {
                // إضافة مشرف الجامعة
                if ($registration->supervisor_id) {
                    $userIds[] = $registration->supervisor_id;
                }

                // 2. جلب بيانات تدريب الطالب في الشركة
                $studentCompany = \Modules\PPUDS\Entities\StudentCompany::where('registration_id', $registration->id)->latest()->first();

                if ($studentCompany && $studentCompany->branch_id && $studentCompany->department_id) {

                    $branch = \Modules\Branch\Entities\Branch::find($studentCompany->branch_id);

                    if ($branch) {

                        $department = $branch->departments()
                            ->first();

                        $userIds[] = $department->pivot->user_id;
                    }
                }
            }

            // تنظيف المصفوفة من القيم الفارغة أو المكررة وإعادة ترتيب الـ Keys
            $userIds = array_values(array_unique(array_filter($userIds ?? [])));
        } elseif ($user->hasRole('Practical Training Supervisor')) {
            // صلاحية مشرف الجامعة (التدريب العملي)
            $registrations = Registration::where('supervisor_id', $user->id)->get();
            $userIds = array_merge(
                $userIds,
                $registrations->pluck('student_id')->toArray(),           // طلابه
                $registrations->pluck('company_supervisor_id')->toArray() // مشرفي الشركات لطلابه
            );
        } elseif ($user->hasRole('Company Supervisor')) {
            // صلاحية مشرف الشركة
            $registrations = Registration::where('company_supervisor_id', $user->id)->get();
            $userIds = array_merge(
                $userIds,
                $registrations->pluck('student_id')->toArray(), // طلابه
                $registrations->pluck('supervisor_id')->toArray() // مشرفي الجامعة لطلابه
            );
        }

        // إزالة القيم الفارغة (null) وإزالة المعرفات المكررة
        $userIds = array_filter(array_unique($userIds));

        return User::whereIn('id', $userIds)->pluck('name', 'id')->toArray();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record(null)
            ->schema([
                Actions::make([
                    Action::make('test')
                        ->label(__('New Message'))
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->color('primary')
                        ->form([
                            FormSection::make(__('New Conversation'))
                                ->description(__('Please select the chat type and choose the recipients.'))
                                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                ->schema([

                                    Radio::make('type')
                                        ->label(__('Chat Type'))
                                        ->options(function () {
                                            $options = [
                                                'single' => __('Single Chat'),
                                            ];

                                            if (!auth()->user()->hasRole('Student')) {
                                                $options['group'] = __('Group Chat');
                                            }

                                            return $options;
                                        })
                                        ->default('single')
                                        ->inline()
                                        ->inlineLabel(false)
                                        ->live()
                                        ->columnSpanFull(),

                                    FormGrid::make(2)
                                        ->schema([

                                            Select::make('student_id') // يمكنك تغيير اسم الحقل لاحقاً ليصبح user_id ليكون أدق
                                                ->label(__('Select Contact'))
                                                ->prefixIcon('heroicon-m-user')
                                                ->options(fn() => $this->getAvailableContacts()) // استدعاء الدالة هنا
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn(Get $get) => $get('type') === 'single')
                                                ->required(fn(Get $get) => $get('type') === 'single')
                                                ->columnSpanFull(),

                                            Select::make('student_ids')
                                                ->label(__('Select Contacts for Group'))
                                                ->prefixIcon('heroicon-m-users')
                                                ->options(fn() => $this->getAvailableContacts()) // استدعاء الدالة هنا
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->visible(fn(Get $get) => $get('type') === 'group')
                                                ->required(fn(Get $get) => $get('type') === 'group')
                                                ->columnSpanFull(),

                                            TextInput::make('name')
                                                ->label(__('Group Name'))
                                                ->prefixIcon('heroicon-m-user-group')
                                                ->placeholder(__('e.g., Team Alpha'))
                                                ->visible(fn(Get $get) => $get('type') === 'group')
                                                ->required(fn(Get $get) => $get('type') === 'group')
                                                ->columnSpan(1),

                                            TextInput::make('description')
                                                ->label(__('Group Description'))
                                                ->prefixIcon('heroicon-m-document-text')
                                                ->placeholder(__('Brief description of the group...'))
                                                ->visible(fn(Get $get) => $get('type') === 'group')
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
                                        name: $data['name'] ?? __('Group Chat'),
                                        description: $data['description'] ?? __('Group Chat'),
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
