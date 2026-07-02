<?php

namespace Tobiebenezer\Ai\Runtime;

use Tobiebenezer\Ai\Models\AiRequestLog;
use Tobiebenezer\Ai\Models\AiToolCallLog;
use Illuminate\Support\Facades\Schema;

class ExecutionLogger
{
    public static function logRequest($user, $profile, $model, $prompt, $responseContent, array $usage, $latencyMs)
    {
        try {
            if (! Schema::hasTable('ai_request_logs')) {
                return null;
            }

            $promptTokens = isset($usage['prompt_tokens']) ? $usage['prompt_tokens'] : (isset($usage['promptTokenCount']) ? $usage['promptTokenCount'] : null);
            $completionTokens = isset($usage['completion_tokens']) ? $usage['completion_tokens'] : (isset($usage['candidatesTokenCount']) ? $usage['candidatesTokenCount'] : null);
            $totalTokens = isset($usage['total_tokens']) ? $usage['total_tokens'] : (isset($usage['totalTokenCount']) ? $usage['totalTokenCount'] : null);

            return AiRequestLog::create([
                'user_id' => $user ? $user->id : null,
                'profile' => $profile,
                'model' => $model,
                'prompt' => is_string($prompt) ? $prompt : json_encode($prompt),
                'response_content' => $responseContent,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => $totalTokens,
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if (app()->bound(\Illuminate\Contracts\Debug\ExceptionHandler::class)) {
                report($exception);
            }
            return null;
        }
    }

    public static function logToolCall($requestLogId, $toolName, array $arguments, $result, $status, $exceptionMessage, $latencyMs)
    {
        try {
            if (! $requestLogId || ! Schema::hasTable('ai_tool_call_logs')) {
                return null;
            }

            return AiToolCallLog::create([
                'request_log_id' => $requestLogId,
                'tool_name' => $toolName,
                'arguments' => $arguments,
                'result' => $result,
                'status' => $status,
                'exception_message' => $exceptionMessage,
                'latency_ms' => $latencyMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            if (app()->bound(\Illuminate\Contracts\Debug\ExceptionHandler::class)) {
                report($exception);
            }
            return null;
        }
    }
}
