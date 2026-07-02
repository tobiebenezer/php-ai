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
        $executedTools = [];
        $startTime = microtime(true);

        while (true) {
            $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_PROVIDER_REQUEST, $request), $context);

            $providerResponse = $provider->send($request);

            if (! $providerResponse->hasToolCalls()) {
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_FINAL_RESPONSE, $providerResponse), $context);

                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $promptData = array_map(function ($message) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                }, $request->messages);

                $log = ExecutionLogger::logRequest(
                    $context->user,
                    $context->profile,
                    $context->model,
                    $promptData,
                    $providerResponse->content,
                    $providerResponse->usage,
                    $latencyMs
                );

                if ($log) {
                    foreach ($executedTools as $toolLog) {
                        ExecutionLogger::logToolCall(
                            $log->id,
                            $toolLog['tool_name'],
                            $toolLog['arguments'],
                            $toolLog['result'],
                            $toolLog['status'],
                            $toolLog['exception_message'],
                            $toolLog['latency_ms']
                        );
                    }
                }

                return AiResponse::fromProvider($providerResponse, $toolResults);
            }

            $request->messages[] = AiMessage::assistant(null, ['tool_calls' => $providerResponse->toolCalls]);

            foreach ($providerResponse->toolCalls as $toolCall) {
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_TOOL_CALL, $toolCall), $context);

                $toolStartTime = microtime(true);
                $status = 'success';
                $exceptionMessage = null;
                $resultData = null;
                $result = null;

                try {
                    $result = $this->tools->execute($toolCall, $tools, $context);
                    $resultData = $result->result;
                } catch (\Throwable $exception) {
                    $status = 'failed';
                    $exceptionMessage = $exception->getMessage();
                    $result = new \Tobiebenezer\Ai\DTO\AiToolResult(
                        $toolCall->id,
                        $toolCall->name,
                        [
                            'status' => 'error',
                            'message' => 'Tool execution failed: ' . $exception->getMessage(),
                            'suggestion' => 'Check arguments and schema constraints. If a column was not found, verify the selected fields.',
                        ]
                    );
                    $resultData = $result->result;
                } finally {
                    $toolLatency = (int) ((microtime(true) - $toolStartTime) * 1000);
                    $executedTools[] = [
                        'tool_name' => $toolCall->name,
                        'arguments' => $toolCall->arguments,
                        'result' => $resultData,
                        'status' => $status,
                        'exception_message' => $exceptionMessage,
                        'latency_ms' => $toolLatency,
                    ];
                }

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
        $executedTools = [];
        $startTime = microtime(true);

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

                $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

                $promptData = array_map(function ($message) {
                    return [
                        'role' => $message->role,
                        'content' => $message->content,
                    ];
                }, $request->messages);

                $log = ExecutionLogger::logRequest(
                    $context->user,
                    $context->profile,
                    $context->model,
                    $promptData,
                    $providerResponse->content,
                    $providerResponse->usage,
                    $latencyMs
                );

                if ($log) {
                    foreach ($executedTools as $toolLog) {
                        ExecutionLogger::logToolCall(
                            $log->id,
                            $toolLog['tool_name'],
                            $toolLog['arguments'],
                            $toolLog['result'],
                            $toolLog['status'],
                            $toolLog['exception_message'],
                            $toolLog['latency_ms']
                        );
                    }
                }

                return AiResponse::fromProvider($providerResponse, $toolResults);
            }

            $request->messages[] = AiMessage::assistant(null, ['tool_calls' => $providerResponse->toolCalls]);

            foreach ($providerResponse->toolCalls as $toolCall) {
                $handler->handle(AiStreamChunk::toolCall(null, ['raw' => $toolCall]));
                $this->enforce($guardrails, new GuardrailEvent(GuardrailEvent::BEFORE_TOOL_CALL, $toolCall), $context);

                $toolStartTime = microtime(true);
                $status = 'success';
                $exceptionMessage = null;
                $resultData = null;
                $result = null;

                try {
                    $result = $this->tools->execute($toolCall, $tools, $context);
                    $resultData = $result->result;
                } catch (\Throwable $exception) {
                    $status = 'failed';
                    $exceptionMessage = $exception->getMessage();
                    $result = new \Tobiebenezer\Ai\DTO\AiToolResult(
                        $toolCall->id,
                        $toolCall->name,
                        [
                            'status' => 'error',
                            'message' => 'Tool execution failed: ' . $exception->getMessage(),
                            'suggestion' => 'Check arguments and schema constraints. If a column was not found, verify the selected fields.',
                        ]
                    );
                    $resultData = $result->result;
                } finally {
                    $toolLatency = (int) ((microtime(true) - $toolStartTime) * 1000);
                    $executedTools[] = [
                        'tool_name' => $toolCall->name,
                        'arguments' => $toolCall->arguments,
                        'result' => $resultData,
                        'status' => $status,
                        'exception_message' => $exceptionMessage,
                        'latency_ms' => $toolLatency,
                    ];
                }

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
