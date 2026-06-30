<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\Guardrails\GuardrailContext;
use Tobiebenezer\Ai\Guardrails\GuardrailDecision;
use Tobiebenezer\Ai\Guardrails\GuardrailEvent;

interface RuntimeGuardrail
{
    public function appliesTo(GuardrailContext $context);

    public function check(GuardrailEvent $event, GuardrailContext $context);
}
