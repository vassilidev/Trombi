<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openrouter' => [
        'key' => env('OPENROUTER_API_KEY'),
        'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

        // Modèles par défaut (swappables). Cf. PRD §3.
        'vision_model' => env('OPENROUTER_VISION_MODEL', 'openai/gpt-4o'),
        'embedding_model' => env('OPENROUTER_EMBEDDING_MODEL', 'openai/text-embedding-3-small'),
        'parsing_model' => env('OPENROUTER_PARSING_MODEL', 'google/gemini-2.5-flash'),

        'embedding_dimensions' => 1536,

        // Modèles vision candidats proposés dans la page benchmark (tous multimodaux
        // sur OpenRouter). Ajuste librement selon ce qui est dispo sur ton compte.
        'benchmark_models' => [
            'openai/gpt-4o',
            'openai/gpt-4o-mini',
            'openai/gpt-4.1',
            'openai/gpt-4.1-mini',
            'google/gemini-2.5-flash',
            'google/gemini-2.5-pro',
            'google/gemini-2.0-flash-001',
            'anthropic/claude-3.5-sonnet',
            'anthropic/claude-3.7-sonnet',
            'meta-llama/llama-3.2-90b-vision-instruct',
            'qwen/qwen2.5-vl-72b-instruct',
            'mistralai/pixtral-large-2411',
            'x-ai/grok-2-vision-1212',
        ],
    ],

];
