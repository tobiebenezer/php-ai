<?php

namespace Tobiebenezer\Ai\Providers;

use Tobiebenezer\Ai\Contracts\ProviderAdapter;
use Tobiebenezer\Ai\Exceptions\ProviderException;
use Illuminate\Contracts\Container\Container;

class ProviderAdapterFactory
{
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function make($provider)
    {
        $config = config("ai.providers.{$provider}");

        if (! is_array($config) || empty($config['adapter'])) {
            throw new ProviderException("Unsupported AI provider [{$provider}].");
        }

        $adapter = $this->app->make($config['adapter']);

        if (! $adapter instanceof ProviderAdapter) {
            throw new ProviderException('AI provider adapter must implement '.ProviderAdapter::class.'.');
        }

        return $adapter;
    }
}
