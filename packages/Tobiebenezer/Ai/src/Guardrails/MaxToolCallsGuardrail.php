<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;

class MaxToolCallsGuardrail implements RuntimeGuardrail
{
    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function check(GuardrailEvent $event, GuardrailContext $context)
    {
        if ($event->phase !== GuardrailEvent::BEFORE_TOOL_CALL) {
            return GuardrailDecision::allow();
        }

        if ($context->toolCallCount >= $context->maxToolCalls) {
            return GuardrailDecision::deny('The maximum number of AI tool calls has been reached.');
        }

        return GuardrailDecision::allow();
    }
}
