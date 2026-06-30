<?php

namespace Tobiebenezer\Ai;

use Tobiebenezer\Ai\Providers\ProviderAdapterFactory;
use Illuminate\Contracts\Container\Container;

class AiManager
{
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function assistant()
    {
        return $this->app->make(AiAssistant::class);
    }

    public function provider($provider)
    {
        return $this->app->make(ProviderAdapterFactory::class)->make($provider);
    }
}
