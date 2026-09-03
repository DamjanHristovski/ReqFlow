<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | ReqFlow talks to LLMs through Prism (prism-php/prism), which supports many
    | providers. We only expose the two below in the UI. Each user brings their
    | own API key (stored encrypted on their profile) and picks one of these
    | providers — the key is injected per request, so no global key is required.
    |
    | 'model' is the default model used for that provider; override per install
    | via the AI_OPENAI_MODEL / AI_GEMINI_MODEL env vars. The key must match the
    | corresponding Prism provider key in config/prism.php.
    |
    */

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'model' => env('AI_OPENAI_MODEL', 'gpt-5.4-mini'),
        ],
        'gemini' => [
            'label' => 'Google Gemini',
            'model' => env('AI_GEMINI_MODEL', 'gemini-3.6-flash'),
        ],
    ],
];
