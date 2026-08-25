<?php
/**
 * ProProfessor AI - Application configuration
 *
 * For hosting, copy config.local.php.example → config.local.php
 * and set your MySQL credentials. Leave base_url as 'auto' unless
 * the app is behind a reverse proxy that breaks auto-detect.
 */
declare(strict_types=1);

return [
    'app_name'   => 'ProProfessor AI',
    'app_tagline'=> "India's AI-Native Academic Operating System",
    // 'auto' detects /professor or /demo/professor from the server path
    'base_url'   => getenv('PPAI_BASE_URL') ?: 'auto',
    'env'        => getenv('PPAI_ENV') ?: 'local',
    'debug'      => (getenv('PPAI_DEBUG') ?: '1') === '1',
    'timezone'   => 'Asia/Kolkata',

    'db' => [
        // 'host'    => getenv('PPAI_DB_HOST') ?: '127.0.0.1',
        // 'port'    => (int)(getenv('PPAI_DB_PORT') ?: 3306),
        // 'name'    => getenv('PPAI_DB_NAME') ?: 'proprofessor',
        // 'user'    => getenv('PPAI_DB_USER') ?: 'root',
        // 'pass'    => getenv('PPAI_DB_PASS') !== false ? getenv('PPAI_DB_PASS') : '',
         'host' => '127.0.0.1',
         'port' => 3307,
         'name' => 'proprofessor',
         'user' => 'root',
         'pass' => '',
         'charset' => 'utf8mb4',
    ],

    // Get a key from https://aistudio.google.com/apikey
    'gemini' => [
        'api_key' => getenv('GEMINI_API_KEY') ?: '',
        'model'   => getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
        'embed_model' => 'text-embedding-004',
        'endpoint' => 'https://generativelanguage.googleapis.com/v1beta',
    ],

    'session_name' => 'ppai_session',
    'upload_max_mb'=> 10,
    'attendance_min_pct' => 75,

    // Optional integrations (disabled until real credentials/providers are set).
    'google_slides' => [
        'enabled' => false,
        'client_id' => getenv('GOOGLE_SLIDES_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_SLIDES_CLIENT_SECRET') ?: '',
    ],
    'narration' => [
        'enabled' => false,
        'provider' => getenv('NARRATION_PROVIDER') ?: '', // e.g. google_tts, elevenlabs
    ],
    'ai_content_detection' => [
        'enabled' => false,
        'provider' => getenv('AI_CONTENT_DETECTION_PROVIDER') ?: '',
    ],

    // Notification delivery channels. Leave disabled until real credentials are set.
    // Never put API keys in the frontend — only server-side config / env vars.
    'notifications' => [
        'email' => [
            'enabled' => (getenv('PPAI_MAIL_ENABLED') ?: '') === '1',
            'from' => getenv('PPAI_MAIL_FROM') ?: '',
        ],
        'whatsapp' => [
            'enabled' => (getenv('PPAI_WHATSAPP_ENABLED') ?: '') === '1',
            'provider' => getenv('PPAI_WHATSAPP_PROVIDER') ?: '',
            'api_key' => getenv('PPAI_WHATSAPP_API_KEY') ?: '',
        ],
        'sms' => [
            'enabled' => (getenv('PPAI_SMS_ENABLED') ?: '') === '1',
            'provider' => getenv('PPAI_SMS_PROVIDER') ?: '',
            'api_key' => getenv('PPAI_SMS_API_KEY') ?: '',
        ],
    ],

];

