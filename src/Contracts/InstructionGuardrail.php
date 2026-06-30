<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\Guardrails\GuardrailContext;

interface InstructionGuardrail
{
    public function appliesTo(GuardrailContext $context);

    public function instructions(GuardrailContext $context);
}
