<?php

namespace Tobiebenezer\Ai\DTO;

class AiMessage
{
    public $role;
    public $content;
    public $name;
    public $toolCallId;
    public $metadata;

    public function __construct(array $attributes = [])
    {
        $this->role = isset($attributes['role']) ? $attributes['role'] : 'user';
        $this->content = array_key_exists('content', $attributes) ? $attributes['content'] : null;
        $this->name = isset($attributes['name']) ? $attributes['name'] : null;
        $this->toolCallId = isset($attributes['tool_call_id']) ? $attributes['tool_call_id'] : null;
        $this->metadata = isset($attributes['metadata']) ? $attributes['metadata'] : [];
    }

    public static function system($content)
    {
        return new static(['role' => 'system', 'content' => $content]);
    }

    public static function user($content)
    {
        return new static(['role' => 'user', 'content' => $content]);
    }

    public static function assistant($content = null, array $metadata = [])
    {
        return new static(['role' => 'assistant', 'content' => $content, 'metadata' => $metadata]);
    }

    public static function tool($toolCallId, $name, $content)
    {
        return new static([
            'role' => 'tool',
            'tool_call_id' => $toolCallId,
            'name' => $name,
            'content' => $content,
        ]);
    }
}
