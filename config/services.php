<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | AI, text-to-speech and speech-to-text providers
    |--------------------------------------------------------------------------
    |
    | Provider, model, endpoint and key are configuration, never code
    | (API-02, API-04, SEC-03; spec 0003 Part C). `fake` is the default so a
    | fresh clone, CI and the seeder all work with no key at all. The
    | bindings in AppServiceProvider::register() read these at resolve time.
    |
    */

    'ai' => [
        'provider' => env('AI_PROVIDER', 'fake'),
        'model' => env('AI_MODEL'),
        'base_url' => env('AI_BASE_URL'),
        'key' => env('AI_KEY'),
        // Lower-latency model for turns a learner waits on (role-play lines).
        'fast_model' => env('AI_FAST_MODEL'),
        // Image generation (DashScope async task API). The base URL is the
        // provider's native /api/v1 root, not the compatible-mode one; it
        // falls back to the AI key when no separate key is set.
        'image_provider' => env('AI_IMAGE_PROVIDER', env('AI_PROVIDER') === 'qwen' ? 'qwen' : 'fake'),
        'image_model' => env('AI_IMAGE_MODEL'),
        'image_base_url' => env('AI_IMAGE_BASE_URL'),
        'image_key' => env('AI_IMAGE_KEY', env('AI_KEY')),
    ],

    /*
    | Deepgram Voice Agent (live spoken role-play). The key never reaches the
    | browser: the server exchanges it for a short-lived token (SEC-03).
    */
    'voice_agent' => [
        'key' => env('DEEPGRAM_AGENT_KEY', env('DEEPGRAM_API_KEY')),
        'url' => env('DEEPGRAM_AGENT_URL', 'wss://agent.deepgram.com/v1/agent/converse'),
        'token_ttl' => (int) env('DEEPGRAM_AGENT_TOKEN_TTL', 60),
    ],

    'tts' => [
        'provider' => env('TTS_PROVIDER', 'fake'),
        'voice' => env('TTS_VOICE', 'flux-brittany-en'),
        'base_url' => env('TTS_BASE_URL'),
        // Deepgram accepts the same server-side key slot as the other TTS
        // providers, with a named fallback for the native integration
        // (API-02, API-04; spec 0003 Part C).
        'key' => env('TTS_KEY', env('DEEPGRAM_API_KEY')),
        'model' => env('TTS_MODEL', 'flux-brittany-en'),
        'expressivity' => (int) env('TTS_EXPRESSIVITY', 0),
    ],

    // Recorded spoken answers (RESP-05, TEST-07). `deepgram` = pre-recorded
    // /v1/listen (nova-3 by default); `openai` = any /audio/transcriptions.
    'stt' => [
        'provider' => env('STT_PROVIDER', 'fake'),
        'base_url' => env('STT_BASE_URL'),
        // `?:` not a default argument: a blank `STT_KEY=` line reads as ''.
        'key' => env('STT_KEY') ?: env('DEEPGRAM_API_KEY'),
        'model' => env('STT_MODEL') ?: (env('STT_PROVIDER') === 'deepgram' ? 'nova-3' : null),
    ],

];
