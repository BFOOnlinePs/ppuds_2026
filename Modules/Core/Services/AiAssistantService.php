<?php

namespace Modules\Core\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Enums\Lab;
use Modules\Core\Ai\AiAssistantAgent;
use Modules\Core\Entities\User;

/**
 * تنسيق مساعد الذكاء الاصطناعي: إرسال الرسائل، وقراءة المحادثات المحفوظة للمستخدم الحالي فقط.
 */
class AiAssistantService
{
    /**
     * @return array{conversation_id: ?string, text: string, tools: array<int, string>}
     */
    public function send(User $user, string $message, ?string $conversationId = null): array
    {
        $agent = AiAssistantAgent::make();

        $conversationId && $this->ownsConversation($user, $conversationId)
            ? $agent->continue($conversationId, $user)
            : $agent->forUser($user);

        $response = $agent->prompt(
            $message,
            provider: $this->configuredProvider(),
            model: $this->configuredModel(),
            timeout: $this->configuredTimeout(),
        );

        if ($response->conversationId) {
            $this->conversations()
                ->where('id', $response->conversationId)
                ->update(['updated_at' => now()]);
        }

        return [
            'conversation_id' => $response->conversationId,
            'text' => trim($response->text),
            'tools' => $response->toolCalls
                ->map(fn ($toolCall) => (string) data_get($toolCall, 'name'))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    public function conversationsFor(User $user, int $limit = 40): Collection
    {
        return $this->conversations()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'title', 'updated_at']);
    }

    /**
     * رسائل المحادثة المعروضة في الواجهة (رسائل المستخدم والمساعد النصية فقط).
     *
     * @return array<int, array{role: string, text: string, tools: array<int, string>}>
     */
    public function messagesFor(User $user, string $conversationId): array
    {
        if (! $this->ownsConversation($user, $conversationId)) {
            return [];
        }

        return $this->messages()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('id')
            ->get(['role', 'content', 'tool_calls'])
            ->filter(fn ($record) => filled($record->content))
            ->map(fn ($record) => [
                'role' => $record->role,
                'text' => $record->content,
                'tools' => collect(json_decode((string) $record->tool_calls, true) ?: [])
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    public function deleteConversation(User $user, string $conversationId): bool
    {
        if (! $this->ownsConversation($user, $conversationId)) {
            return false;
        }

        DB::transaction(function () use ($conversationId) {
            $this->messages()->where('conversation_id', $conversationId)->delete();
            $this->conversations()->where('id', $conversationId)->delete();
        });

        return true;
    }

    public function ownsConversation(User $user, string $conversationId): bool
    {
        return $this->conversations()
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function isConfigured(): bool
    {
        $provider = $this->configuredProvider() ?? config('ai.default');

        if ($provider instanceof Lab) {
            $provider = $provider->value;
        }

        if (is_array($provider)) {
            return collect($provider)
                ->map(fn ($model, $providerName) => is_int($providerName) ? $model : $providerName)
                ->contains(fn ($providerName) => $this->providerHasCredentials((string) $providerName));
        }

        return $this->providerHasCredentials((string) $provider);
    }

    private function providerHasCredentials(string $provider): bool
    {
        if ($provider === 'ollama') {
            return filled(config('ai.providers.ollama.url'));
        }

        return filled(config("ai.providers.{$provider}.key"));
    }

    private function conversations(): Builder
    {
        return DB::table(config('ai.conversations.tables.conversations', 'agent_conversations'));
    }

    private function messages(): Builder
    {
        return DB::table(config('ai.conversations.tables.messages', 'agent_conversation_messages'));
    }

    private function configuredProvider(): Lab|array|string|null
    {
        $provider = config('ai.ai_assistant.provider');

        return blank($provider) ? null : $provider;
    }

    private function configuredModel(): ?string
    {
        $model = config('ai.ai_assistant.model');

        return blank($model) ? null : $model;
    }

    private function configuredTimeout(): int
    {
        return (int) config('ai.ai_assistant.timeout', 120);
    }
}
