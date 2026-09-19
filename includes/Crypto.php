<?php
declare(strict_types=1);

/**
 * Server-side encryption for secrets (professor API keys).
 * Key material comes from config/env — never from the client.
 */
final class Crypto
{
    public static function encrypt(string $plaintext): string
    {
        $key = self::binaryKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            throw new RuntimeException('Unable to encrypt secret.');
        }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RuntimeException('Unable to decrypt secret.');
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::binaryKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Unable to decrypt secret.');
        }
        return $plain;
    }

    private static function binaryKey(): string
    {
        $configured = trim((string)config('encryption_key', ''));
        if ($configured === '') {
            $configured = trim((string)(getenv('PPAI_ENCRYPTION_KEY') ?: ''));
        }
        if ($configured !== '') {
            return hash('sha256', $configured, true);
        }
        $db = config('db') ?? [];
        $material = implode('|', [
            (string)($db['host'] ?? ''),
            (string)($db['port'] ?? ''),
            (string)($db['name'] ?? ''),
            (string)($db['user'] ?? ''),
            (string)($db['pass'] ?? ''),
            (string)config('session_name', 'ppai_session'),
        ]);
        return hash('sha256', $material, true);
    }
}
