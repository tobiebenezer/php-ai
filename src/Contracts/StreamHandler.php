<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\DTO\AiStreamChunk;

interface StreamHandler
{
    public function handle(AiStreamChunk $chunk);
}
