<?php

namespace Tobiebenezer\Ai\Guardrails;

class GuardrailContext
{
    public $user;
    public $profile;
    public $provider;
    public $model;
    public $tools;
    public $metadata;
    public $toolCallCount;
    public $maxToolCalls;

    public function __construct(array $attributes = [])
    {
        $this->user = isset($attributes['user']) ? $attributes['user'] : null;
        $this->profile = isset($attributes['profile']) ? $attributes['profile'] : null;
        $this->provider = isset($attributes['provider']) ? $attributes['provider'] : null;
        $this->model = isset($attributes['model']) ? $attributes['model'] : null;
        $this->tools = isset($attributes['tools']) ? $attributes['tools'] : [];
        $this->metadata = isset($attributes['metadata']) ? $attributes['metadata'] : [];
        $this->toolCallCount = isset($attributes['tool_call_count']) ? (int) $attributes['tool_call_count'] : 0;
        $this->maxToolCalls = isset($attributes['max_tool_calls']) ? (int) $attributes['max_tool_calls'] : 3;
    }
}
