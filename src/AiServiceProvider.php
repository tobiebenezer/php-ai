<?php

namespace Tobiebenezer\Ai;

use Tobiebenezer\Ai\Providers\ProviderAdapterFactory;
use Tobiebenezer\Ai\Runtime\ToolLoopRunner;
use Tobiebenezer\Ai\Tools\ToolCatalog;
use Tobiebenezer\Ai\Tools\ToolExecutor;
use Tobiebenezer\Ai\Tools\ToolDiscovery;
use Tobiebenezer\Ai\Guardrails\GuardrailGatherer;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai.php', 'ai');

        $this->app->singleton(AiManager::class, function ($app) {
            return new AiManager($app);
        });

        $this->app->singleton(AiAssistant::class, function ($app) {
            return new AiAssistant(
                $app->make(ProviderAdapterFactory::class),
                $app->make(ToolCatalog::class),
                $app->make(GuardrailGatherer::class),
                $app->make(ToolLoopRunner::class)
            );
        });

        $this->app->singleton(ProviderAdapterFactory::class);
        $this->app->singleton(ToolDiscovery::class);
        $this->app->singleton(ToolCatalog::class);
        $this->app->singleton(ToolExecutor::class);
        $this->app->singleton(GuardrailGatherer::class);
        $this->app->singleton(ToolLoopRunner::class);
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai.php' => config_path('ai.php'),
            ], ['ai', 'ai-config']);

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], ['ai', 'ai-migrations']);

            $this->publishes([
                __DIR__.'/../stubs/provider.stub' => base_path('stubs/ai-provider.stub'),
                __DIR__.'/../stubs/tool.stub' => base_path('stubs/ai-tool.stub'),
                __DIR__.'/../stubs/guardrail.stub' => base_path('stubs/ai-guardrail.stub'),
                __DIR__.'/../stubs/queued-response-handler.stub' => base_path('stubs/ai-queued-response-handler.stub'),
            ], ['ai', 'ai-stubs']);
        }
    }
}
