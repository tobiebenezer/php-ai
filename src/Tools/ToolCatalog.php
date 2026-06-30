<?php

namespace Tobiebenezer\Ai\Tools;

use Tobiebenezer\Ai\Contracts\Tool;
use Tobiebenezer\Ai\Exceptions\ToolException;
use Illuminate\Contracts\Container\Container;

class ToolCatalog
{
    protected $app;
    protected $discovery;

    public function __construct(Container $app, ToolDiscovery $discovery)
    {
        $this->app = $app;
        $this->discovery = $discovery;
    }

    public function forProfile($profileName)
    {
        $tools = [];

        foreach ($this->discovery->discover(
            (array) config('ai.tool_discovery.paths', []),
            (array) config('ai.tool_discovery.namespaces', [])
        ) as $class) {
            $tool = $this->app->make($class);

            if (! $tool instanceof Tool) {
                throw new ToolException('AI tool must implement '.Tool::class.'.');
            }

            $profiles = $tool->profiles();

            if (in_array('*', $profiles, true) || in_array($profileName, $profiles, true)) {
                $tools[$tool->name()] = $tool;
            }
        }

        return $tools;
    }

    public function schemas(array $tools)
    {
        $schemas = [];

        foreach ($tools as $tool) {
            $schemas[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->schema(),
            ];
        }

        return $schemas;
    }
}
