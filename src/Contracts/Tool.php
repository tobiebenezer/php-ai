<?php

namespace Tobiebenezer\Ai\Contracts;

use Tobiebenezer\Ai\Guardrails\GuardrailContext;

interface Tool
{
    public function name();

    public function description();

    public function schema();

    public function profiles();

    public function isReadOnly();

    public function execute(array $arguments, GuardrailContext $context);
}
