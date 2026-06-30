<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;
use Tobiebenezer\Ai\DTO\AiProviderRequest;

class PromptSanitizerGuardrail implements RuntimeGuardrail
{
    /**
     * Patterns commonly used for prompt injection.
     */
    protected $injectionPatterns = [
        '/ignore\s+(?:all\s+)?previous\s+instructions/i',
        '/system\s+override/i',
        '/disregard\s+(?:all\s+)?prior\s+directives/i',
        '/you\s+are\s+now\s+in\s+developer\s+mode/i',
        '/jailbreak/i',
    ];

    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function check(GuardrailEvent $event, GuardrailContext $context)
    {
        if ($event->phase !== GuardrailEvent::BEFORE_PROVIDER_REQUEST) {
            return GuardrailDecision::allow();
        }

        /** @var AiProviderRequest $request */
        $request = $event->payload;

        foreach ($request->messages as $message) {
            if ($message->role === 'user') {
                $content = $message->content;

                // 1. Detect prompt injection attempts
                foreach ($this->injectionPatterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        return GuardrailDecision::deny("Your input was flagged by the safety system for containing unauthorized instruction overrides.");
                    }
                }

                // 2. Sanitize HTML tags to prevent XSS/injection of raw HTML block tags
                $message->content = $this->sanitize($content);
            }
        }

        return GuardrailDecision::allow();
    }

    protected function sanitize(string $content): string
    {
        return strip_tags($content);
    }
}
