<?php

namespace Tobiebenezer\Ai\Tools;

use Tobiebenezer\Ai\DTO\AiToolCall;
use Tobiebenezer\Ai\DTO\AiToolResult;
use Tobiebenezer\Ai\Exceptions\ToolException;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;

class ToolExecutor
{
    public function execute(AiToolCall $call, array $tools, GuardrailContext $context)
    {
        if (! isset($tools[$call->name])) {
            throw new ToolException("AI tool [{$call->name}] is not available for this request.");
        }

        $result = $tools[$call->name]->execute($call->arguments, $context);

        if (! is_array($result)) {
            throw new ToolException("AI tool [{$call->name}] must return an array result.");
        }

        return new AiToolResult($call->id, $call->name, $result);
    }
}
