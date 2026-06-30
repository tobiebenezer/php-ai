<?php

namespace Tobiebenezer\Ai\Guardrails;

class GuardrailDecision
{
    public $allowed;
    public $message;
    public $metadata;

    public function __construct($allowed, $message = null, array $metadata = [])
    {
        $this->allowed = (bool) $allowed;
        $this->message = $message;
        $this->metadata = $metadata;
    }

    public static function allow(array $metadata = [])
    {
        return new static(true, null, $metadata);
    }

    public static function deny($message, array $metadata = [])
    {
        return new static(false, $message, $metadata);
    }
}
