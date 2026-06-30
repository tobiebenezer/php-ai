<?php

namespace Tobiebenezer\Ai\Guardrails;

class GuardrailPipeline
{
    protected $instructionGuardrails;
    protected $runtimeGuardrails;

    public function __construct(array $instructionGuardrails = [], array $runtimeGuardrails = [])
    {
        $this->instructionGuardrails = $instructionGuardrails;
        $this->runtimeGuardrails = $runtimeGuardrails;
    }

    public function instructions(GuardrailContext $context)
    {
        $instructions = [];

        foreach ($this->instructionGuardrails as $guardrail) {
            $instructions = array_merge($instructions, $guardrail->instructions($context));
        }

        return array_values(array_filter($instructions));
    }

    public function check(GuardrailEvent $event, GuardrailContext $context)
    {
        foreach ($this->runtimeGuardrails as $guardrail) {
            $decision = $guardrail->check($event, $context);

            if (! $decision->allowed) {
                return $decision;
            }
        }

        return GuardrailDecision::allow();
    }
}
