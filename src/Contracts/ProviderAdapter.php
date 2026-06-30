<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\DTO\AiProviderRequest;
use Tobiebenezer\Ai\DTO\AiProviderResponse;

interface ProviderAdapter
{
    public function name();

    public function send(AiProviderRequest $request);

    public function supportsTools();
}
