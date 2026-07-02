<?php

namespace Tobiebenezer\Ai\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeAiGuardrailCommand extends GeneratorCommand
{
    protected $name = 'make:ai-guardrail';
    protected $description = 'Create a new AI guardrail class';
    protected $type = 'AI Guardrail';

    protected function getStub()
    {
        if ($this->option('runtime')) {
            return __DIR__.'/stubs/runtime-guardrail.stub';
        }

        return __DIR__.'/stubs/instruction-guardrail.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Ai\Guardrails';
    }

    protected function getOptions()
    {
        return [
            ['runtime', 'r', InputOption::VALUE_NONE, 'Generate a runtime execution guardrail'],
        ];
    }
}
