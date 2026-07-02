<?php

namespace Tobiebenezer\Ai\DTO;

class AiProviderRequest
{
    public $model;
    public $messages;
    public $tools;
    public $toolResults;
    public $options;
    public $responseSchema;

    public function __construct(array $attributes = [])
    {
        $this->model = isset($attributes['model']) ? $attributes['model'] : null;
        $this->messages = isset($attributes['messages']) ? $attributes['messages'] : [];
        $this->tools = isset($attributes['tools']) ? $attributes['tools'] : [];
        $this->toolResults = isset($attributes['tool_results']) ? $attributes['tool_results'] : [];
        $this->options = isset($attributes['options']) ? $attributes['options'] : [];
        $this->responseSchema = isset($attributes['response_schema']) ? $attributes['response_schema'] : null;
    }
}
