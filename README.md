# AI Package (`ai`)

This package provides a Laravel-compatible AI Assistant infrastructure designed to integrate LLMs (e.g., OpenRouter, Gemini) with analytical tools and guardrails.

---

## Features

- **Multi-Provider Support**: Adapter system supporting OpenRouter and Google Gemini APIs.
- **Dynamic Tool Discovery**: Scans and loads analytical tools automatically.
- **Guardrail Pipeline**: Pre-execution system instructions (`InstructionGuardrail`) and run-time safety check filters (`RuntimeGuardrail`).
- **Interactive Livewire Integration**: Dynamic, user-friendly client-side chat interface.

---

## Installation

Add the local repository to your host project's `composer.json` file:

```json
"repositories": [
    {
        "type": "path",
        "url": "packages/Tobiebenezer/Ai"
    }
]
```

Then install the package:

```bash
composer require tobiebenezer/calsoft-ai
```

### Configuration & Migrations

Publish the configuration file, migrations, and stubs:

```bash
# Publish everything
php artisan vendor:publish --provider="Tobiebenezer\Ai\AiServiceProvider"

# Or publish individually
php artisan vendor:publish --tag="calsoft-ai-config"
php artisan vendor:publish --tag="calsoft-ai-migrations"
php artisan vendor:publish --tag="calsoft-ai-stubs"
```

Run the migrations to create the configuration table:

```bash
php artisan migrate
```

---

## How to Write Custom AI Tools

The package discovers all classes implementing `Tobiebenezer\Ai\Contracts\Tool` inside the configured path (default: `app/Ai/Tools/`).

### Extending `AnalyticalTool`

For database-backed, analytical querying tools, you should extend the abstract `Tobiebenezer\Ai\Tools\AnalyticalTool` class. This provides built-in query building, grouping, formatting, filter validation, and aggregation.

Here is an example tool that allows the AI to query a `Sales` table:

```php
<?php

namespace App\Ai\Tools;

use Tobiebenezer\Ai\Tools\AnalyticalTool;
use App\Models\Sale;

class QuerySalesTool extends AnalyticalTool
{
    /**
     * Define the target Eloquent model.
     */
    protected function modelClass()
    {
        return Sale::class;
    }

    /**
     * List of columns the AI can filter results by.
     */
    protected function filterableColumns()
    {
        return ['branch_id', 'staff_id', 'status_id'];
    }

    /**
     * List of columns the AI is allowed to group results by.
     */
    protected function groupableColumns()
    {
        return ['branch_id', 'staff_id', 'status_id'];
    }

    /**
     * List of columns that support math aggregation (sum, avg, etc.).
     */
    protected function aggregateableColumns()
    {
        return ['total', 'discount'];
    }

    /**
     * Default SELECT array returned to the AI.
     */
    protected function defaultSelects()
    {
        return [
            'sales.id',
            'sales.total',
            'sales.discount',
            'sales.created_at',
            'branches.name as branch_name',
        ];
    }

    /**
     * SQL joins needed for select/filter clauses.
     */
    protected function joins()
    {
        return [
            ['branches', 'sales.branch_id', '=', 'branches.id'],
        ];
    }

    /**
     * User-facing description explaining to the LLM what this tool does.
     */
    public function description()
    {
        return 'Query sales transactions with totals, discounts, and branch associations.';
    }
}
```

### Implementing `Tool` directly

If you need a tool that does not query the database (e.g. calling an external API), implement `Tobiebenezer\Ai\Contracts\Tool` directly:

```php
<?php

namespace App\Ai\Tools;

use Tobiebenezer\Ai\Contracts\Tool;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;

class ExternalWeatherTool implements Tool
{
    public function name()
    {
        return 'get_weather';
    }

    public function description()
    {
        return 'Retrieve current weather for a city.';
    }

    public function schema()
    {
        return [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'The city name']
            ],
            'required' => ['city']
        ];
    }

    public function profiles()
    {
        return ['*'];
    }

    public function isReadOnly()
    {
        return true;
    }

    public function execute(array $arguments, GuardrailContext $context)
    {
        // call external API...
        return ['temp' => '27C', 'condition' => 'Sunny'];
    }
}
```

---

## Guardrails

Guardrails monitor prompt context and run-time actions:

1. **`InstructionGuardrail`**: Prepends context-specific instructions to the LLM prompt before execution.
2. **`RuntimeGuardrail`**: Triggers events during execution phases (`BEFORE_PROVIDER_REQUEST`, `BEFORE_TOOL_CALL`, `AFTER_TOOL_RESULT`, `BEFORE_FINAL_RESPONSE`) to allow sanitizing inputs or denying requests.

### Custom Guardrail Example

```php
<?php

namespace App\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\RuntimeGuardrail;
use Tobiebenezer\Ai\Guardrails\GuardrailDecision;
use Tobiebenezer\Ai\Guardrails\GuardrailEvent;

class BlockListGuardrail implements RuntimeGuardrail
{
    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function check(GuardrailEvent $event, GuardrailContext $context)
    {
        if ($event->phase === GuardrailEvent::BEFORE_PROVIDER_REQUEST) {
            // Check payload text...
        }
        return GuardrailDecision::allow();
    }
}
```

### Overriding Package Guardrails

If you want to customize or replace a default package guardrail (such as `CapabilitiesGuardrail`), you can override it in your application using one of the following two approaches:

#### Method 1: Container Binding (Recommended)
You can register an override in your application's `AppServiceProvider` so that the Laravel container resolves your custom guardrail class whenever the package asks for the default one.

```php
// app/Providers/AppServiceProvider.php
use Tobiebenezer\Ai\Guardrails\CapabilitiesGuardrail as BaseCapabilities;
use App\Ai\Guardrails\CustomCapabilitiesGuardrail;

public function boot()
{
    $this->app->bind(BaseCapabilities::class, CustomCapabilitiesGuardrail::class);
}
```

#### Method 2: Configuration Replacement
Alternatively, publish the configuration file (`config/ai.php`) and swap the package guardrail class in the `guardrails.global` or `guardrails.groups` array with your custom class:

```php
// config/ai.php
'guardrails' => [
    'global' => [
        // Replace base guardrail with custom class
        \App\Ai\Guardrails\CustomCapabilitiesGuardrail::class,
        
        \Tobiebenezer\Ai\Guardrails\MaxToolCallsGuardrail::class,
        \Tobiebenezer\Ai\Guardrails\ReadOnlyToolsGuardrail::class,
        \Tobiebenezer\Ai\Guardrails\HtmlStyleGuardrail::class,
        \Tobiebenezer\Ai\Guardrails\PromptSanitizerGuardrail::class,
    ],
];
```

Your custom guardrail must implement the respective contract (`Tobiebenezer\Ai\Contracts\InstructionGuardrail` or `Tobiebenezer\Ai\Contracts\RuntimeGuardrail`):

```php
<?php

namespace App\Ai\Guardrails;

use Tobiebenezer\Ai\Contracts\InstructionGuardrail;
use Tobiebenezer\Ai\Guardrails\GuardrailContext;

class CustomCapabilitiesGuardrail implements InstructionGuardrail
{
    public function appliesTo(GuardrailContext $context)
    {
        return true;
    }

    public function instructions(GuardrailContext $context)
    {
        return [
            "Your custom capabilities instructions override here...",
        ];
    }
}
```

