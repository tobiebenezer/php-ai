<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;

class ReadOnlyToolsGuardrail implements RuntimeGuardrail
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

        $toolName = isset($event->payload->name) ? $event->payload->name : null;
        $tool = isset($context->tools[$toolName]) ? $context->tools[$toolName] : null;

        if ($toolName && $tool && ! $tool->isReadOnly()) {
            return GuardrailDecision::deny("The AI tool [{$toolName}] is not allowed by the read-only tools policy.");
        }

        return GuardrailDecision::allow();
    }
}
