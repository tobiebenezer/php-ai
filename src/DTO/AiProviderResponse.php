<?php

namespace Tobiebenezer\Ai\DTO;

class AiProviderResponse
{
    const TYPE_MESSAGE = 'message';
    const TYPE_TOOL_CALLS = 'tool_calls';

    public $type;
    public $content;
    public $toolCalls;
    public $raw;
    public $usage;

    public function __construct(array $attributes = [])
    {
        $this->type = isset($attributes['type']) ? $attributes['type'] : self::TYPE_MESSAGE;
        $this->content = isset($attributes['content']) ? $attributes['content'] : null;
        $this->toolCalls = isset($attributes['tool_calls']) ? $attributes['tool_calls'] : [];
        $this->raw = isset($attributes['raw']) ? $attributes['raw'] : [];
        $this->usage = isset($attributes['usage']) ? $attributes['usage'] : [];
    }

    public static function message($content, array $raw = [], array $usage = [])
    {
        return new static([
            'type' => self::TYPE_MESSAGE,
            'content' => $content,
            'raw' => $raw,
            'usage' => $usage,
        ]);
    }

    public static function toolCalls(array $toolCalls, array $raw = [], array $usage = [])
    {
        return new static([
            'type' => self::TYPE_TOOL_CALLS,
            'tool_calls' => $toolCalls,
            'raw' => $raw,
            'usage' => $usage,
        ]);
    }

    public function hasToolCalls()
    {
        return $this->type === self::TYPE_TOOL_CALLS && count($this->toolCalls) > 0;
    }
}
