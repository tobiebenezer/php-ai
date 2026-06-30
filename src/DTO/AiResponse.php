<?php

namespace Tobiebenezer\Ai\DTO;

class AiResponse
{
    public $content;
    public $toolResults;
    public $metadata;
    public $raw;

    public function __construct(array $attributes = [])
    {
        $this->content = isset($attributes['content']) ? $attributes['content'] : null;
        $this->toolResults = isset($attributes['tool_results']) ? $attributes['tool_results'] : [];
        $this->metadata = isset($attributes['metadata']) ? $attributes['metadata'] : [];
        $this->raw = isset($attributes['raw']) ? $attributes['raw'] : [];
    }

    public static function fromProvider(AiProviderResponse $response, array $toolResults = [])
    {
        return new static([
            'content' => $response->content,
            'tool_results' => $toolResults,
            'metadata' => ['usage' => $response->usage],
            'raw' => $response->raw,
        ]);
    }
}
