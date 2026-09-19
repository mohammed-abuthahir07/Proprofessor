<?php
/**
 * Current supported models for Professor BYOK (Settings dropdown).
 *
 * Compatible APIs:
 * - OpenAI: POST https://api.openai.com/v1/chat/completions
 * - Gemini: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 * - Claude: POST https://api.anthropic.com/v1/messages
 *
 * Update this file when providers add or retire models. Do not put retired IDs here.
 */
declare(strict_types=1);

return [
    'openai' => [
        'default' => 'gpt-5.6-terra',
        'models' => [
            'gpt-5.6-terra',
            'gpt-5.6-luna',
            'gpt-5.6-sol',
            'gpt-5.6',
            'gpt-4.1',
            'gpt-4.1-mini',
        ],
        'prefix' => 'gpt-',
        'docs_url' => 'https://platform.openai.com/api-keys',
    ],
    'gemini' => [
        'default' => 'gemini-3.8-flash',
        'models' => [
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
        ],
        'prefix' => 'gemini-',
        'docs_url' => 'https://aistudio.google.com/apikey',
    ],
    'claude' => [
        'default' => 'claude-sonnet-5',
        'models' => [
            'claude-sonnet-5',
            'claude-sonnet-4-6',
            'claude-opus-5',
            'claude-haiku-4-5',
        ],
        'prefix' => 'claude-',
        'docs_url' => 'https://console.anthropic.com/settings/keys',
    ],
];
