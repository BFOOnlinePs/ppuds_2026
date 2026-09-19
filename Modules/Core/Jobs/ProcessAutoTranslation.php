<?php

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Core\Services\AutoTranslationService;
use Throwable;

class ProcessAutoTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public int $timeout;

    // The record was deleted before the job ran: nothing left to translate.
    public bool $deleteWhenMissingModels = true;

    /**
     * @param  array<string, string>  $sources  attribute => locale it was written in
     */
    public function __construct(
        public Model $model,
        public array $sources,
    ) {
        $this->timeout = (int) config('ai.auto_translation.timeout', 60) + 30;
    }

    public function handle(AutoTranslationService $service): void
    {
        $service->translate($this->model, $this->sources);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Auto translation failed.', [
            'model' => $this->model::class,
            'id' => $this->model->getKey(),
            'exception' => $exception->getMessage(),
        ]);
    }
}
