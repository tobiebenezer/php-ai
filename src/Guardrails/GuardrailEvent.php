<?php

namespace Tobiebenezer\Ai\Guardrails;

class GuardrailEvent
{
    const BEFORE_PROVIDER_REQUEST = 'before_provider_request';
    const BEFORE_TOOL_CALL = 'before_tool_call';
    const AFTER_TOOL_RESULT = 'after_tool_result';
    const BEFORE_FINAL_RESPONSE = 'before_final_response';

    public $phase;
    public $payload;

    public function __construct($phase, $payload = null)
    {
        $this->phase = $phase;
        $this->payload = $payload;
    }
}
