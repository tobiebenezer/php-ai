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

class OpenRouterAdapter implements StreamingProviderAdapter
{
    protected $client;

    public function __construct(ClientInterface $client = null)
    {
        $this->client = $client ?: new Client([
            'http_errors' => false,
            'timeout' => (int) config('ai.providers.openrouter.timeout', 60),
        ]);
    }

    public function name()
    {
        return 'openrouter';
    }

    public function send(AiProviderRequest $request)
    {
        $data = $this->post($request, false);

        return $this->toProviderResponse($data);
    }

    public function stream(AiProviderRequest $request, StreamHandler $handler)
    {
        $data = $this->post($request, true);
        $content = '';
        $toolCalls = [];
        $rawEvents = [];

        foreach ($this->streamEvents($data) as $event) {
            $rawEvents[] = $event;

            $choice = isset($event['choices'][0]) ? $event['choices'][0] : [];
            $delta = isset($choice['delta']) ? $choice['delta'] : [];

            if (isset($delta['content']) && $delta['content'] !== '') {
                $content .= $delta['content'];
                $handler->handle(AiStreamChunk::text($delta['content'], ['raw' => $event]));
            }

            if (! empty($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCallDelta) {
                    $this->mergeToolCallDelta($toolCalls, $toolCallDelta);
                    $handler->handle(AiStreamChunk::toolCall(null, ['raw' => $toolCallDelta]));
                }
            }
        }

        if (count($toolCalls) > 0) {
            return AiProviderResponse::toolCalls($this->normalizeStreamedToolCalls($toolCalls), ['events' => $rawEvents]);
        }

        return AiProviderResponse::message($content, ['events' => $rawEvents]);
    }

    public function supportsTools()
    {
        return true;
    }

    protected function post(AiProviderRequest $request, $stream)
    {
        $config = config('ai.providers.openrouter', []);
        $key = isset($config['key']) ? $config['key'] : null;

        if (! $key) {
            throw new ProviderException('OpenRouter API key is not configured.');
        }

        $payload = $this->payload($request, $stream);

        try {
            $response = $this->client->request('POST', rtrim($config['url'], '/').'/chat/completions', [
                'headers' => $this->headers($config, $key),
                'json' => $payload,
                'stream' => (bool) $stream,
            ]);
        } catch (GuzzleException $exception) {
            throw new ProviderException('OpenRouter request failed: '.$exception->getMessage(), 0, $exception);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw new ProviderException("OpenRouter returned HTTP {$status}: {$body}");
        }

        if ($stream) {
            return $body;
        }

        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new ProviderException('OpenRouter returned invalid JSON.');
        }

        return $data;
    }

    protected function payload(AiProviderRequest $request, $stream)
    {
        $payload = array_merge($request->options, [
            'model' => $request->model,
            'messages' => $this->messages($request->messages),
            'stream' => (bool) $stream,
        ]);

        if (count($request->tools) > 0) {
            $payload['tools'] = $this->tools($request->tools);
            $payload['tool_choice'] = isset($payload['tool_choice']) ? $payload['tool_choice'] : 'auto';
        }

        return $payload;
    }

    protected function headers(array $config, $key)
    {
        $headers = [
            'Authorization' => 'Bearer '.$key,
            'Content-Type' => 'application/json',
        ];

        if (! empty($config['referer'])) {
            $headers['HTTP-Referer'] = $config['referer'];
        }

        if (! empty($config['title'])) {
            $headers['X-Title'] = $config['title'];
        }

        return $headers;
    }

    protected function messages(array $messages)
    {
        return array_map(function (AiMessage $message) {
            $data = [
                'role' => $message->role,
                'content' => $message->content,
            ];

            if ($message->name) {
                $data['name'] = $message->name;
            }

            if ($message->toolCallId) {
                $data['tool_call_id'] = $message->toolCallId;
            }

            if ($message->role === 'assistant' && isset($message->metadata['tool_calls'])) {
                $data['tool_calls'] = array_map(function (AiToolCall $toolCall) {
                    return [
                        'id' => $toolCall->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $toolCall->name,
                            'arguments' => json_encode($toolCall->arguments),
                        ],
                    ];
                }, $message->metadata['tool_calls']);
            }

            return $data;
        }, $messages);
    }

    protected function tools(array $tools)
    {
        return array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'],
                    'parameters' => $tool['parameters'],
                ],
            ];
        }, $tools);
    }

    protected function toProviderResponse(array $data)
    {
        $message = isset($data['choices'][0]['message']) ? $data['choices'][0]['message'] : [];
        $usage = isset($data['usage']) ? $data['usage'] : [];

        if (! empty($message['tool_calls'])) {
            return AiProviderResponse::toolCalls($this->normalizeToolCalls($message['tool_calls']), $data, $usage);
        }

        return AiProviderResponse::message(isset($message['content']) ? $message['content'] : '', $data, $usage);
    }

    protected function normalizeToolCalls(array $toolCalls)
    {
        return array_map(function ($toolCall) {
            $arguments = isset($toolCall['function']['arguments']) ? json_decode($toolCall['function']['arguments'], true) : [];

            return new AiToolCall(
                isset($toolCall['id']) ? $toolCall['id'] : uniqid('tool-call-', true),
                $toolCall['function']['name'],
                is_array($arguments) ? $arguments : []
            );
        }, $toolCalls);
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

    protected function mergeToolCallDelta(array &$toolCalls, array $delta)
    {
        $index = isset($delta['index']) ? $delta['index'] : count($toolCalls);

        if (! isset($toolCalls[$index])) {
            $toolCalls[$index] = [
                'id' => isset($delta['id']) ? $delta['id'] : uniqid('tool-call-', true),
                'name' => '',
                'arguments' => '',
            ];
        }

        if (isset($delta['id'])) {
            $toolCalls[$index]['id'] = $delta['id'];
        }

        if (isset($delta['function']['name'])) {
            $toolCalls[$index]['name'] .= $delta['function']['name'];
        }

        if (isset($delta['function']['arguments'])) {
            $toolCalls[$index]['arguments'] .= $delta['function']['arguments'];
        }
    }

    protected function normalizeStreamedToolCalls(array $toolCalls)
    {
        return array_map(function ($toolCall) {
            $arguments = json_decode($toolCall['arguments'], true);

            return new AiToolCall(
                $toolCall['id'],
                $toolCall['name'],
                is_array($arguments) ? $arguments : []
            );
        }, array_values($toolCalls));
    }
}
