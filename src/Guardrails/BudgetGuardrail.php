<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;
use Tobiebenezer\Ai\Models\AiRequestLog;

class BudgetGuardrail implements RuntimeGuardrail
{
    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function check(GuardrailEvent $event, GuardrailContext $context)
    {
        if ($event->phase !== GuardrailEvent::BEFORE_PROVIDER_REQUEST) {
            return GuardrailDecision::allow();
        }

        $limit = config('ai.budget.monthly_token_limit');

        if (! $limit) {
            return GuardrailDecision::allow();
        }

        try {
            $sum = AiRequestLog::where('created_at', '>=', now()->startOfMonth())
                ->sum('total_tokens');

            if ($sum >= $limit) {
                return GuardrailDecision::deny("AI monthly token budget exceeded. (Current: {$sum}, Limit: {$limit})");
            }
        } catch (\Throwable $exception) {
            // Ignore DB errors (e.g. table not migrated yet) so we don't break execution
        }

        return GuardrailDecision::allow();
    }
}
