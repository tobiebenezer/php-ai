<?php

namespace Tobiebenezer\Ai\Console;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;

class MakeAiToolCommand extends GeneratorCommand
{
    protected $name = 'make:ai-tool';
    protected $description = 'Create a new AI tool class';
    protected $type = 'AI Tool';

    protected function getStub()
    {
        if ($this->option('analytical')) {
            return __DIR__.'/stubs/analytical-tool.stub';
        }

        return __DIR__.'/stubs/tool.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Ai\Tools';
    }

    protected function buildClass($name)
    {
        $class = parent::buildClass($name);

        $toolName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($name)));
        $toolName = preg_replace('/_tool$/i', '', $toolName);

        return str_replace('{{ name }}', $toolName, $class);
    }

    protected function getOptions()
    {
        return [
            ['analytical', 'a', InputOption::VALUE_NONE, 'Generate a database-backed analytical query tool'],
        ];
    }
}
