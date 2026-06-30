<?php

namespace Tobiebenezer\Ai\Providers;

use Tobiebenezer\Ai\Contracts\ProviderAdapter;
use Tobiebenezer\Ai\Contracts\StreamHandler;
use Tobiebenezer\Ai\Contracts\StreamingProviderAdapter;
use Tobiebenezer\Ai\DTO\AiProviderRequest;
use Tobiebenezer\Ai\DTO\AiProviderResponse;
use Tobiebenezer\Ai\DTO\AiStreamChunk;

class NullProviderAdapter implements ProviderAdapter, StreamingProviderAdapter
{
    public function name()
    {
        return 'null';
    }

    public function send(AiProviderRequest $request)
    {
        if (isset($request->options['response']) && $request->options['response'] instanceof AiProviderResponse) {
            return $request->options['response'];
        }

        return AiProviderResponse::message('Null AI provider response.', [
            'provider' => 'null',
            'model' => $request->model,
        ]);
    }

    public function supportsTools()
    {
        return true;
    }

    public function stream(AiProviderRequest $request, StreamHandler $handler)
    {
        $response = $this->send($request);

        if ($response->content) {
            $handler->handle(AiStreamChunk::text($response->content));
        }

        return $response;
    }
}
