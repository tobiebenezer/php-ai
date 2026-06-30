<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\DTO\AiProviderRequest;

interface StreamingProviderAdapter extends ProviderAdapter
{
    public function stream(AiProviderRequest $request, StreamHandler $handler);
}
