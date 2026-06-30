<?php

namespace Tobiebenezer\Ai\DTO;

class AiProviderRequest
{
    public $model;
    public $messages;
    public $tools;
    public $toolResults;
    public $options;

    public function __construct(array $attributes = [])
    {
        $this->model = isset($attributes['model']) ? $attributes['model'] : null;
        $this->messages = isset($attributes['messages']) ? $attributes['messages'] : [];
        $this->tools = isset($attributes['tools']) ? $attributes['tools'] : [];
        $this->toolResults = isset($attributes['tool_results']) ? $attributes['tool_results'] : [];
        $this->options = isset($attributes['options']) ? $attributes['options'] : [];
    }
}
