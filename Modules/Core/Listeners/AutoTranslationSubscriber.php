<?php

namespace Modules\Core\Listeners;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Modules\Core\Jobs\ProcessAutoTranslation;
use Modules\Core\Services\AutoTranslationService;
use Throwable;

/**
 * Queues AI translation of every translatable record whose name, title or
 * description was just written in one language, so the other languages are
 * filled in without touching any form or model.
 *
 * Listens after Astrotomic's own saved listener (wildcards run after
 * class-specific listeners), so the translations are already stored.
 */
class AutoTranslationSubscriber
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            'eloquent.saved: *' => 'handleModelSaved',
        ];
    }

    public function handleModelSaved(string $event, array $payload): void
    {
        $model = $payload[0] ?? null;

        if (! $model instanceof TranslatableContract) {
            return;
        }

        // Translation is a convenience: nothing here may fail the save itself.
        try {
            $service = app(AutoTranslationService::class);
            $sources = $service->changedSources($model);

            if ($sources === [] || ! $service->acceptsSaves()) {
                return;
            }

            $connection = config('ai.auto_translation.queue_connection');

            // Without a queue worker: run once the response has been sent, so
            // the person saving never waits on the AI or sees its errors.
            if ($connection === 'sync') {
                ProcessAutoTranslation::dispatchAfterResponse($model, $sources);

                return;
            }

            ProcessAutoTranslation::dispatch($model, $sources)
                ->onConnection($connection)
                ->onQueue(config('ai.auto_translation.queue'))
                ->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('Auto translation could not be queued.', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
