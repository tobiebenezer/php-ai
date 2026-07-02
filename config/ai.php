<?php

return [
    'default_profile' => env('AI_PROFILE', 'null'),
    'settings_service' => env('AI_SETTINGS_SERVICE', App\Http\Services\Ai\AiSettingsService::class),

    'profiles' => [
        'null' => [
            'provider' => 'null',
            'model' => 'null-model',
            'max_tool_calls' => 3,
            'guardrails' => ['default'],
            'options' => [],
        ],

        'openrouter' => [
            'provider' => 'openrouter',
            'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
            'max_tool_calls' => 3,
            'guardrails' => ['default'],
            'options' => [
                'max_tokens' => 2000,
            ],
        ],

        'gemini' => [
            'provider' => 'gemini',
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'max_tool_calls' => 3,
            'guardrails' => ['default'],
            'options' => [],
        ],
    ],

    'providers' => [
        'null' => [
            'driver' => 'null',
            'adapter' => Tobiebenezer\Ai\Providers\NullProviderAdapter::class,
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'adapter' => Tobiebenezer\Ai\Providers\OpenRouterAdapter::class,
            'key' => env('OPENROUTER_API_KEY'),
            'url' => env('OPENROUTER_URL', 'https://openrouter.ai/api/v1'),
            'referer' => env('OPENROUTER_REFERER'),
            'title' => env('OPENROUTER_TITLE', env('APP_NAME', 'Calsoft')),
            'timeout' => env('OPENROUTER_TIMEOUT', 60),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'adapter' => Tobiebenezer\Ai\Providers\GeminiAdapter::class,
            'key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => env('GEMINI_TIMEOUT', 60),
        ],
    ],

    'budget' => [
        'monthly_token_limit' => env('AI_MONTHLY_TOKEN_LIMIT', null),
    ],

    'queue' => [
        'connection' => env('AI_QUEUE_CONNECTION', null),
        'queue' => env('AI_QUEUE', null),
        'default_handler' => Tobiebenezer\Ai\Queue\NullQueuedResponseHandler::class,
    ],

    'tool_discovery' => [
        'paths' => [
            app_path('Ai/Tools'),
        ],
        'namespaces' => [
            'App\\Ai\\Tools',
        ],
    ],

    'guardrails' => [
        'global' => [
            Tobiebenezer\Ai\Guardrails\MaxToolCallsGuardrail::class,
            Tobiebenezer\Ai\Guardrails\ReadOnlyToolsGuardrail::class,
            Tobiebenezer\Ai\Guardrails\HtmlStyleGuardrail::class,
            Tobiebenezer\Ai\Guardrails\CapabilitiesGuardrail::class,
            Tobiebenezer\Ai\Guardrails\PromptSanitizerGuardrail::class,
            Tobiebenezer\Ai\Guardrails\BudgetGuardrail::class,
        ],

        'groups' => [
            'default' => [],
        ],
    ],
];
