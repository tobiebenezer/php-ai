<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\DTO\AiRequest;
use Tobiebenezer\Ai\DTO\AiResponse;
use Throwable;

interface QueuedResponseHandler
{
    public function handle(AiResponse $response, AiRequest $request);

    public function failed(Throwable $exception, AiRequest $request);
}
