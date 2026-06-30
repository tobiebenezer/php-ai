<?php

namespace Tobiebenezer\Ai\Queue;

use Tobiebenezer\Ai\Contracts\QueuedResponseHandler;
use Tobiebenezer\Ai\DTO\AiRequest;
use Tobiebenezer\Ai\DTO\AiResponse;
use Throwable;

class NullQueuedResponseHandler implements QueuedResponseHandler
{
    public function handle(AiResponse $response, AiRequest $request)
    {
        return null;
    }

    public function failed(Throwable $exception, AiRequest $request)
    {
        return null;
    }
}
