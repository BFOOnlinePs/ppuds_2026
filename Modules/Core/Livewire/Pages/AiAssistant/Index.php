<?php

namespace Modules\Core\Livewire\Pages\AiAssistant;

use App\View\Components\AppLayout;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Modules\Core\Services\AiAssistantService;
use Throwable;

/**
 * صفحة مساعد الذكاء الاصطناعي المستقلة (شبيهة بـ ChatGPT).
 * الإرسال على خطوتين: send() يعرض رسالة المستخدم فورًا، ثم generate() ينتظر رد الذكاء الاصطناعي.
 */
class Index extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'c', history: true)]
    public ?string $conversationId = null;

    public array $messages = [];

    public string $prompt = '';

    public ?string $pendingPrompt = null;

    public function mount(AiAssistantService $service): void
    {
        $this->authorize('AI Assistant View');

        if ($this->conversationId && ! $service->ownsConversation(auth()->user(), $this->conversationId)) {
            $this->conversationId = null;
        }

        $this->loadMessages();
    }

    #[Computed]
    public function conversations(): Collection
    {
        return app(AiAssistantService::class)->conversationsFor(auth()->user());
    }

    #[Computed]
    public function configured(): bool
    {
        return app(AiAssistantService::class)->isConfigured();
    }

    public function newChat(): void
    {
        $this->authorize('AI Assistant View');

        $this->reset(['conversationId', 'messages', 'prompt', 'pendingPrompt']);
    }

    public function openConversation(string $conversationId): void
    {
        $this->authorize('AI Assistant View');

        if (! app(AiAssistantService::class)->ownsConversation(auth()->user(), $conversationId)) {
            return;
        }

        $this->conversationId = $conversationId;
        $this->pendingPrompt = null;
        $this->loadMessages();
    }

    public function deleteConversation(string $conversationId): void
    {
        $this->authorize('AI Assistant View');

        if (! app(AiAssistantService::class)->deleteConversation(auth()->user(), $conversationId)) {
            return;
        }

        if ($this->conversationId === $conversationId) {
            $this->newChat();
        }

        unset($this->conversations);
        Toaster::success(__('Conversation deleted'));
    }

    public function send(): void
    {
        $this->authorize('AI Assistant View');

        if ($this->pendingPrompt !== null) {
            return;
        }

        $this->validate([
            'prompt' => ['required', 'string', 'max:4000'],
        ], [], [
            'prompt' => __('Message'),
        ]);

        $this->pendingPrompt = trim($this->prompt);
        $this->messages[] = ['role' => 'user', 'text' => $this->pendingPrompt, 'tools' => []];
        $this->prompt = '';

        // نعرض رسالة المستخدم أولًا ثم نطلب الرد في طلب منفصل.
        $this->js('$wire.generate()');
    }

    public function generate(AiAssistantService $service): void
    {
        $this->authorize('AI Assistant View');

        if ($this->pendingPrompt === null) {
            return;
        }

        $prompt = $this->pendingPrompt;

        try {
            if (! $service->isConfigured()) {
                $this->messages[] = ['role' => 'error', 'text' => __('AI provider is not configured. Please add the API key in the environment settings.'), 'tools' => []];

                return;
            }

            $result = $service->send(auth()->user(), $prompt, $this->conversationId);

            $this->conversationId = $result['conversation_id'] ?? $this->conversationId;
            $this->messages[] = [
                'role' => 'assistant',
                'text' => $result['text'] !== '' ? $result['text'] : __('I could not generate a reply, please try again.'),
                'tools' => $result['tools'],
            ];

            unset($this->conversations);
        } catch (Throwable $exception) {
            Log::warning('AI assistant reply failed.', [
                'user_id' => auth()->id(),
                'conversation_id' => $this->conversationId,
                'exception' => $exception->getMessage(),
            ]);

            $this->messages[] = ['role' => 'error', 'text' => __('Something went wrong while contacting the AI service. Please try again.'), 'tools' => []];
        } finally {
            $this->pendingPrompt = null;
        }
    }

    private function loadMessages(): void
    {
        $this->messages = $this->conversationId
            ? app(AiAssistantService::class)->messagesFor(auth()->user(), $this->conversationId)
            : [];
    }

    public function render()
    {
        return view('core::livewire.pages.ai-assistant.index')->layout(AppLayout::class, [
            'breadcrumbs' => [
                ['title' => __('Home'), 'url' => route('home')],
                ['title' => __('AI Assistant'), 'url' => route('ai-assistant.index')],
            ],
        ]);
    }
}
