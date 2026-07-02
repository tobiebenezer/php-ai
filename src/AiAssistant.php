<?php

namespace Tobiebenezer\Ai;

use Tobiebenezer\Ai\Contracts\StreamHandler;
use Tobiebenezer\Ai\DTO\AiMessage;
use Tobiebenezer\Ai\DTO\AiProviderRequest;
use Tobiebenezer\Ai\DTO\AiRequest;
use Tobiebenezer\Ai\Jobs\ProcessAiRequestJob;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;
use Tobiebenezer\Ai\Guardrails\GuardrailGatherer;
use Tobiebenezer\Ai\Providers\ProviderAdapterFactory;
use Tobiebenezer\Ai\Runtime\ToolLoopRunner;
use Tobiebenezer\Ai\Tools\ToolCatalog;
use InvalidArgumentException;

class AiAssistant
{
    protected $providers;
    protected $tools;
    protected $guardrails;
    protected $runner;

    public function __construct(
        ProviderAdapterFactory $providers,
        ToolCatalog $tools,
        GuardrailGatherer $guardrails,
        ToolLoopRunner $runner
    ) {
        $this->providers = $providers;
        $this->tools = $tools;
        $this->guardrails = $guardrails;
        $this->runner = $runner;
    }

    public function respond(AiRequest $request)
    {
        list($provider, $providerRequest, $toolSet, $pipeline, $context) = $this->prepare($request);

        return $this->runner->run($provider, $providerRequest, $toolSet, $pipeline, $context);
    }

    public function stream(AiRequest $request, StreamHandler $handler)
    {
        list($provider, $providerRequest, $toolSet, $pipeline, $context) = $this->prepare($request);

        return $this->runner->stream($provider, $providerRequest, $toolSet, $pipeline, $context, $handler);
    }

    public function queue(AiRequest $request, $handler = null)
    {
        $job = new ProcessAiRequestJob($request, $handler ?: config('ai.queue.default_handler'));
        $connection = config('ai.queue.connection');
        $queue = config('ai.queue.queue');

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        return dispatch($job);
    }

    protected function prepare(AiRequest $request)
    {
        $this->applyRuntimeSettings();

        $profileName = $request->profile ?: config('ai.default_profile', 'null');
        $profile = config("ai.profiles.{$profileName}");

        if (! is_array($profile)) {
            throw new InvalidArgumentException("Unknown AI profile [{$profileName}].");
        }

        $providerName = $profile['provider'];
        $provider = $this->providers->make($providerName);
        $toolSet = $provider->supportsTools() ? $this->tools->forProfile($profileName) : [];

        $context = new GuardrailContext([
            'user' => $request->user,
            'profile' => $profileName,
            'provider' => $providerName,
            'model' => $profile['model'],
            'tools' => $toolSet,
            'max_tool_calls' => isset($profile['max_tool_calls']) ? (int) $profile['max_tool_calls'] : 3,
            'metadata' => $request->metadata,
        ]);

        $pipeline = $this->guardrails->gather($profile, $toolSet, $context);
        $messages = $request->messages;

        foreach (array_reverse($pipeline->instructions($context)) as $instruction) {
            array_unshift($messages, AiMessage::system($instruction));
        }

        $providerRequest = new AiProviderRequest([
            'model' => $profile['model'],
            'messages' => $messages,
            'tools' => $this->tools->schemas($toolSet),
            'options' => array_merge(isset($profile['options']) ? $profile['options'] : [], $request->options),
            'response_schema' => $request->responseSchema,
        ]);

        return [$provider, $providerRequest, $toolSet, $pipeline, $context];
    }

    protected function applyRuntimeSettings()
    {
        $serviceClass = config('ai.settings_service');

        if (! $serviceClass || ! class_exists($serviceClass)) {
            return;
        }

        try {
            app($serviceClass)->apply();
        } catch (\Throwable $exception) {
            if (app()->bound(\Illuminate\Contracts\Debug\ExceptionHandler::class)) {
                report($exception);
            }
        }
    }
}
