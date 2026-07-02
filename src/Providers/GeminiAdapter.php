<?php

namespace Tobiebenezer\Ai\Providers;

use Tobiebenezer\Ai\Contracts\StreamHandler;
use Tobiebenezer\Ai\Contracts\StreamingProviderAdapter;
use Tobiebenezer\Ai\DTO\AiMessage;
use Tobiebenezer\Ai\DTO\AiProviderRequest;
use Tobiebenezer\Ai\DTO\AiProviderResponse;
use Tobiebenezer\Ai\DTO\AiStreamChunk;
use Tobiebenezer\Ai\DTO\AiToolCall;
use Tobiebenezer\Ai\Exceptions\ProviderException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class GeminiAdapter implements StreamingProviderAdapter
{
    protected $client;

    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client ?: new Client([
            'http_errors' => false,
            'timeout' => (int) config('ai.providers.gemini.timeout', 60),
        ]);
    }

    public function name()
    {
        return 'gemini';
    }

    public function send(AiProviderRequest $request)
    {
        $data = $this->post($request, false);

        return $this->toProviderResponse($data);
    }

    public function stream(AiProviderRequest $request, StreamHandler $handler)
    {
        $body = $this->post($request, true);
        $content = '';
        $toolCalls = [];
        $rawEvents = [];

        foreach ($this->streamEvents($body) as $event) {
            $rawEvents[] = $event;
            $parts = isset($event['candidates'][0]['content']['parts']) ? $event['candidates'][0]['content']['parts'] : [];

            foreach ($parts as $part) {
                if (isset($part['text'])) {
                    $content .= $part['text'];
                    $handler->handle(AiStreamChunk::text($part['text'], ['raw' => $event]));
                }

                if (isset($part['functionCall'])) {
                    $toolCalls[] = $this->normalizeFunctionCall($part['functionCall']);
                    $handler->handle(AiStreamChunk::toolCall(null, ['raw' => $part['functionCall']]));
                }
            }
        }

        if (count($toolCalls) > 0) {
            return AiProviderResponse::toolCalls($toolCalls, ['events' => $rawEvents]);
        }

        return AiProviderResponse::message($content, ['events' => $rawEvents]);
    }

    public function supportsTools()
    {
        return true;
    }

    protected function post(AiProviderRequest $request, $stream)
    {
        $config = config('ai.providers.gemini', []);
        $key = isset($config['key']) ? $config['key'] : null;

        if (! $key) {
            throw new ProviderException('Gemini API key is not configured.');
        }

        $method = $stream ? 'streamGenerateContent' : 'generateContent';
        $url = rtrim($config['url'], '/').'/models/'.$this->model($request->model).':'.$method;

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => ['Content-Type' => 'application/json'],
                'query' => array_merge(['key' => $key], $stream ? ['alt' => 'sse'] : []),
                'json' => $this->payload($request),
                'stream' => (bool) $stream,
            ]);
        } catch (GuzzleException $exception) {
            throw new ProviderException('Gemini request failed: '.$exception->getMessage(), 0, $exception);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw new ProviderException("Gemini returned HTTP {$status}: {$body}");
        }

        if ($stream) {
            return $body;
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new ProviderException('Gemini returned invalid JSON.');
        }

        return $data;
    }

    protected function model($model)
    {
        return preg_replace('/^models\//', '', $model);
    }

    protected function payload(AiProviderRequest $request)
    {
        $payload = array_merge($request->options, [
            'contents' => $this->contents($request->messages),
        ]);

        $generationConfig = isset($payload['generationConfig']) ? $payload['generationConfig'] : [];

        foreach (['temperature', 'maxOutputTokens', 'topP', 'topK', 'candidateCount', 'stopSequences'] as $key) {
            if (isset($payload[$key])) {
                $generationConfig[$key] = $payload[$key];
                unset($payload[$key]);
            }
        }

        if (isset($payload['max_tokens'])) {
            $generationConfig['maxOutputTokens'] = (int) $payload['max_tokens'];
            unset($payload['max_tokens']);
        }

        if ($request->responseSchema) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $this->normalizeSchema($request->responseSchema);
        }

        if (count($generationConfig) > 0) {
            $payload['generationConfig'] = $generationConfig;
        }

        $systemInstruction = $this->systemInstruction($request->messages);

        if ($systemInstruction) {
            $payload['system_instruction'] = $systemInstruction;
        }

        if (count($request->tools) > 0) {
            $payload['tools'] = [[
                'function_declarations' => $this->tools($request->tools),
            ]];
        }

        return $payload;
    }

    protected function systemInstruction(array $messages)
    {
        $parts = [];

        foreach ($messages as $message) {
            if ($message->role === 'system' && $message->content) {
                $parts[] = ['text' => $message->content];
            }
        }

        return count($parts) > 0 ? ['parts' => $parts] : null;
    }

    protected function contents(array $messages)
    {
        $contents = [];

        foreach ($messages as $message) {
            if ($message->role === 'system') {
                continue;
            }

            if ($message->role === 'assistant') {
                $parts = [];

                if ($message->content) {
                    $parts[] = ['text' => $message->content];
                }

                if (isset($message->metadata['tool_calls'])) {
                    foreach ($message->metadata['tool_calls'] as $toolCall) {
                        $parts[] = [
                            'functionCall' => [
                                'name' => $toolCall->name,
                                'args' => (object) $toolCall->arguments,
                            ],
                        ];
                    }
                }

                if (count($parts) > 0) {
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                }

                continue;
            }

            if ($message->role === 'tool') {
                $decoded = json_decode($message->content, true);

                $contents[] = [
                    'role' => 'user',
                    'parts' => [[
                        'functionResponse' => [
                            'name' => $message->name,
                            'response' => is_array($decoded) ? $decoded : ['content' => $message->content],
                        ],
                    ]],
                ];

                continue;
            }

            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $message->content]],
            ];
        }

        return $contents;
    }

    protected function tools(array $tools)
    {
        return array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'parameters' => $this->normalizeSchema($tool['parameters']),
            ];
        }, $tools);
    }

    protected function normalizeSchema(array $schema)
    {
        $normalized = $schema;

        if (isset($normalized['type'])) {
            $normalized['type'] = strtoupper($normalized['type']);
        }

        if (isset($normalized['properties'])) {
            foreach ($normalized['properties'] as $name => $property) {
                if (isset($property['type'])) {
                    $property['type'] = strtoupper($property['type']);
                }

                $normalized['properties'][$name] = $property;
            }
        }

        return $normalized;
    }

    protected function toProviderResponse(array $data)
    {
        $parts = isset($data['candidates'][0]['content']['parts']) ? $data['candidates'][0]['content']['parts'] : [];
        $usage = isset($data['usageMetadata']) ? $data['usageMetadata'] : [];
        $content = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content .= $part['text'];
            }

            if (isset($part['functionCall'])) {
                $toolCalls[] = $this->normalizeFunctionCall($part['functionCall']);
            }
        }

        if (count($toolCalls) > 0) {
            return AiProviderResponse::toolCalls($toolCalls, $data, $usage);
        }

        return AiProviderResponse::message($content, $data, $usage);
    }

    protected function normalizeFunctionCall(array $functionCall)
    {
        return new AiToolCall(
            isset($functionCall['id']) ? $functionCall['id'] : uniqid('gemini-tool-call-', true),
            $functionCall['name'],
            isset($functionCall['args']) && is_array($functionCall['args']) ? $functionCall['args'] : []
        );
    }

    protected function streamEvents($body)
    {
        $events = [];
        $lines = preg_split('/\r\n|\r|\n/', $body);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, 'data:') !== 0) {
                continue;
            }

            $payload = trim(substr($line, 5));

            if ($payload === '[DONE]') {
                break;
            }

            $event = json_decode($payload, true);

            if (is_array($event)) {
                $events[] = $event;
            }
        }

        return $events;
    }
}
