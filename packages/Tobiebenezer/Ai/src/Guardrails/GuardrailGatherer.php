<?php

namespace Tobiebenezer\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\InstructionGuardrail;
use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;
use Tobiebenezer\Ai\Exceptions\GuardrailException;
use Illuminate\Contracts\Container\Container;

class GuardrailGatherer
{
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    public function gather(array $profile, array $tools, GuardrailContext $context)
    {
        $classes = $this->classesFor($profile, $tools);
        $instructionGuardrails = [];
        $runtimeGuardrails = [];

        foreach ($classes as $class) {
            $guardrail = $this->app->make($class);

            if ($guardrail instanceof InstructionGuardrail && $guardrail->appliesTo($context)) {
                $instructionGuardrails[] = $guardrail;
            }

            if ($guardrail instanceof RuntimeGuardrail && $guardrail->appliesTo($context)) {
                $runtimeGuardrails[] = $guardrail;
            }

            if (! $guardrail instanceof InstructionGuardrail && ! $guardrail instanceof RuntimeGuardrail) {
                throw new GuardrailException('AI guardrail must implement an instruction or runtime guardrail contract.');
            }
        }

        return new GuardrailPipeline($instructionGuardrails, $runtimeGuardrails);
    }

    protected function classesFor(array $profile, array $tools)
    {
        $classes = config('ai.guardrails.global', []);
        $groups = config('ai.guardrails.groups', []);

        foreach (isset($profile['guardrails']) ? $profile['guardrails'] : [] as $group) {
            $classes = array_merge($classes, isset($groups[$group]) ? $groups[$group] : []);
        }

        foreach ($tools as $tool) {
            if (method_exists($tool, 'guardrails')) {
                $classes = array_merge($classes, $tool->guardrails());
            }
        }

        return array_values(array_unique($classes));
    }
}
