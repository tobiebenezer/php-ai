<?php

namespace Tobiebenezer\Ai\Console;

use Illuminate\Console\GeneratorCommand;

class MakeAiProviderCommand extends GeneratorCommand
{
    protected $name = 'make:ai-provider';
    protected $description = 'Create a new AI provider adapter class';
    protected $type = 'AI Provider Adapter';

    protected function getStub()
    {
        return __DIR__.'/stubs/provider.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Ai\Providers';
    }

    protected function buildClass($name)
    {
        $class = parent::buildClass($name);

        $providerName = strtolower(class_basename($name));
        $providerName = preg_replace('/adapter$/i', '', $providerName);
        $providerName = preg_replace('/provider$/i', '', $providerName);

        return str_replace('{{ name }}', $providerName, $class);
    }
}
