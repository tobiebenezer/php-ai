<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\InstructionGuardrail;

class CapabilitiesGuardrail implements InstructionGuardrail
{
    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function instructions(GuardrailContext $context)
    {
        return [
            $this->getCapabilitiesDirective(),
        ];
    }

    protected function getCapabilitiesDirective()
    {
        return <<<TEXT
When asked "what other information can you provide" or similar inquiries about your capabilities, you MUST NOT output generic AI assistant capabilities (such as writing general code in Python/Rust/Go, regex, copywriting, translation, DevOps, CI/CD, shell commands, or general web search).

Instead, you MUST only provide information about the specific systems, business data, and analytical tools available within the Calsoft application. Explain that you can retrieve, filter, and analyze:
1. Sales: Transactions, totals, discounts, and payment statuses.
2. Expenses: Spending records, items, and categories.
3. Procurement: Vendor orders, inventory stocking, and costs.
4. Inventory: HQ and branch stock levels, threshold alerts, and low stock items.
5. Customers: Contact details, loyalty points, and staff assignments.
6. Staff: Employees, designations, departments, salaries, and leave data.

Instruct the user to ask questions about these specific data domains (e.g., "Summarize expenses by category this month" or "Show low stock inventory items").
TEXT;
    }
}
