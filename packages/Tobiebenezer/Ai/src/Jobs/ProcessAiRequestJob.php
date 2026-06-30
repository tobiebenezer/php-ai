<?php

namespace Tobiebenezer\Ai\Jobs;

use Tobiebenezer\Ai\AiAssistant;
use Tobiebenezer\Ai\Contracts\QueuedResponseHandler;
use Tobiebenezer\Ai\DTO\AiRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAiRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $request;
    public $handler;

    public function __construct(AiRequest $request, $handler = null)
    {
        $this->request = $request;
        $this->handler = $handler;
    }

    public function handle(AiAssistant $assistant)
    {
        $response = $assistant->respond($this->request);
        $this->handler()->handle($response, $this->request);
    }

    public function failed(Throwable $exception)
    {
        $this->handler()->failed($exception, $this->request);
    }

    protected function handler()
    {
        $handlerClass = $this->handler ?: config('ai.queue.default_handler');
        $handler = app($handlerClass);

        if (! $handler instanceof QueuedResponseHandler) {
            throw new \InvalidArgumentException('AI queue handler must implement '.QueuedResponseHandler::class.'.');
        }

        return $handler;
    }
}
