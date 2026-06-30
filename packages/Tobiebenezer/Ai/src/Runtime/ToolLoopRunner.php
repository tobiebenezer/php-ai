<?php

namespace Tobiebenezer\Ai\Runtime;

use Tobiebenezer\Ai\Contracts\ProviderAdapter;
use Tobiebenezer\Ai\Contracts\StreamHandler;
use Tobiebenezer\Ai\Contracts\StreamingProviderAdapter;
use Tobiebenezer\Ai\DTO\AiMessage;
use Tobiebenezer\Ai\DTO\AiProviderRequest;
use Tobiebenezer\Ai\DTO\AiResponse;
use Tobiebenezer\Ai\DTO\AiStreamChunk;
use Tobiebenezer\Ai\Exceptions\GuardrailException;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;
use Tobiebenezer\Ai\Guardrails\GuardrailEvent;
use Tobiebenezer\Ai\Guardrails\GuardrailPipeline;
use Tobiebenezer\Ai\Tools\ToolExecutor;

class ToolLoopRunner
{
    protected $tools;

    public function __construct(ToolExecutor $tools)
    {
        $this->tools = $tools;
    }

    public function run(
        ProviderAdapter $provider,
        AiProviderRequest $request,
        array $tools,
        GuardrailPipeline $guardrails,
        GuardrailContext $context
    ) {
        $toolResults = [];

        while (true) {
            $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_PROVIDER_REQUEST, $request), $context);

            $providerResponse = $provider->send($request);

            if (! $providerResponse->hasToolCalls()) {
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_FINAL_RESPONSE, $providerResponse), $context);

                return AiResponse::fromProvider($providerResponse, $toolResults);
            }

            $request->messages[] = AiMessage::assistant(null, ['tool_calls' => $providerResponse->toolCalls]);

            foreach ($providerResponse->toolCalls as $toolCall) {
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_TOOL_CALL, $toolCall), $context);

                $result = $this->tools->execute($toolCall, $tools, $context);
                $toolResults[] = $result;
                $context->toolCallCount++;

                $request->toolResults[] = $result;
                $request->messages[] = AiMessage::tool($result->id, $result->name, $result->content());

                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::AFTER_TOOL_RESULT, $result), $context);
            }
        }
    }

    public function stream(
        ProviderAdapter $provider,
        AiProviderRequest $request,
        array $tools,
        GuardrailPipeline $guardrails,
        GuardrailContext $context,
        StreamHandler $handler
    ) {
        $toolResults = [];

        while (true) {
            $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_PROVIDER_REQUEST, $request), $context);

            if ($provider instanceof StreamingProviderAdapter) {
                $providerResponse = $provider->stream($request, $handler);
            } else {
                $providerResponse = $provider->send($request);

                if ($providerResponse->content) {
                    $handler->handle(AiStreamChunk::text($providerResponse->content));
                }
            }

            if (! $providerResponse->hasToolCalls()) {
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_FINAL_RESPONSE, $providerResponse), $context);
                $handler->handle(AiStreamChunk::done(['response' => $providerResponse]));

                return AiResponse::fromProvider($providerResponse, $toolResults);
            }

            $request->messages[] = AiMessage::assistant(null, ['tool_calls' => $providerResponse->toolCalls]);

            foreach ($providerResponse->toolCalls as $toolCall) {
                $handler->handle(AiStreamChunk::toolCall(null, ['tool_call' => $toolCall]));
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_TOOL_CALL, $toolCall), $context);

                $result = $this->tools->execute($toolCall, $tools, $context);
                $toolResults[] = $result;
                $context->toolCallCount++;

                $request->toolResults[] = $result;
                $request->messages[] = AiMessage::tool($result->id, $result->name, $result->content());

                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::AFTER_TOOL_RESULT, $result), $context);
            }
        }
    }

    protected function enforce(GuardrailPipeline $guardrails, GuardrailEvent $event, GuardrailContext $context)
    {
        $decision = $guardrails->check($event, $context);

        if (! $decision->allowed) {
            throw new GuardrailException($decision->message ?: 'AI guardrail denied the operation.');
        }
    }
}
