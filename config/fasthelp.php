<?php

use App\Models\User;

return [
    // The host application's authenticatable model used for agents/auth clients.
    'user_model' => env('FASTHELP_USER_MODEL', User::class),

    // Auth guard used to resolve the current user (null = default guard).
    'auth_guard' => env('FASTHELP_AUTH_GUARD'),

    // Cookie name used to identify guest visitors.
    'cookie' => env('FASTHELP_COOKIE', 'fasthelp_visitor'),

    // Agent eligibility.
    'agents' => [
        // Optional callable fn(Authenticatable $user): bool. Null = use the fasthelp_agents pivot table.
        'resolver' => null,
    ],

    // Presence settings.
    'presence' => [
        // Seconds after which an agent's "online" status is considered stale.
        'stale_after' => (int) env('FASTHELP_PRESENCE_STALE_AFTER', 60),
    ],

    // Broadcasting.
    'broadcasting' => [
        'channel_prefix' => env('FASTHELP_CHANNEL_PREFIX', 'fasthelp'),
    ],

    // Floating widget appearance/behavior.
    'widget' => [
        'enabled' => (bool) env('FASTHELP_WIDGET_ENABLED', true),
        'position' => env('FASTHELP_WIDGET_POSITION', 'bottom-right'), // bottom-right|bottom-left
        'colors' => [
            'primary' => env('FASTHELP_WIDGET_PRIMARY', '#4f46e5'),
            'on_primary' => env('FASTHELP_WIDGET_ON_PRIMARY', '#ffffff'),
        ],
        'title' => env('FASTHELP_WIDGET_TITLE', 'Need help?'),
        'launcher_icon' => env('FASTHELP_WIDGET_ICON', 'heroicon-o-chat-bubble-left-right'),
        'greeting' => env('FASTHELP_WIDGET_GREETING', 'Hi! How can we help you today?'),
        'auto_theme' => (bool) env('FASTHELP_WIDGET_AUTO_THEME', true),
    ],

    // AI first-line responder.
    'ai' => [
        'enabled' => (bool) env('FASTHELP_AI_ENABLED', false),
        'driver' => env('FASTHELP_AI_DRIVER', 'gemini'),
        'api_key' => env('FASTHELP_GEMINI_KEY'), // nullable — never resolved/validated at boot
        'model' => env('FASTHELP_GEMINI_MODEL', 'gemini-1.5-flash'),
        'system_prompt' => env('FASTHELP_AI_SYSTEM_PROMPT', 'You are a helpful, friendly customer-support assistant. Answer concisely. If you cannot help or the user asks for a human, say so clearly.'),
        'handoff_keywords' => ['human', 'agent', 'representative', 'person', 'talk to someone'],
        'confidence_threshold' => (float) env('FASTHELP_AI_CONFIDENCE', 0.5),
        'offline_behavior' => env('FASTHELP_AI_OFFLINE_BEHAVIOR', 'capture_email'), // ai_only|capture_email
    ],

    // Route registration for the widget endpoints (polling fallback, start, post).
    'routes' => [
        'prefix' => env('FASTHELP_ROUTE_PREFIX', 'fasthelp'),
        'middleware' => ['web'],
    ],

    // Knowledge-base crawler and retrieval settings.
    'kb' => [
        'enabled' => (bool) env('FASTHELP_KB_ENABLED', false),
        'base_url' => env('FASTHELP_KB_BASE_URL', env('APP_URL', 'http://localhost')),
        'max_pages' => (int) env('FASTHELP_KB_MAX_PAGES', 100),
        'same_domain_only' => (bool) env('FASTHELP_KB_SAME_DOMAIN', true),
        'respect_robots' => (bool) env('FASTHELP_KB_RESPECT_ROBOTS', true),
        'embedding_model' => env('FASTHELP_KB_EMBEDDING_MODEL', 'text-embedding-004'),
        'retrieve_top_k' => (int) env('FASTHELP_KB_TOP_K', 4),
        'min_similarity' => (float) env('FASTHELP_KB_MIN_SIMILARITY', 0.65),
        'user_agent' => env('FASTHELP_KB_USER_AGENT', 'FastHelpBot/1.0 (+https://github.com/taba2177/laravel-fasthelp)'),
        'schedule' => env('FASTHELP_KB_SCHEDULE', 'off'), // off|daily|weekly
    ],
];
