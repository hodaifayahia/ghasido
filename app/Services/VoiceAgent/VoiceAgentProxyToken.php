<?php

namespace App\Services\VoiceAgent;

use App\Models\RoleplayAttempt;
use Illuminate\Support\Facades\Date;

/**
 * A short-lived, URL-safe token that binds the Qwen proxy URL to one voice
 * call (spec 0004). Deepgram calls /voice-agent/llm/{token}/chat/completions
 * from its servers with no session, so the token is the only credential:
 * HMAC-SHA256 over "attempt.expiry" with the application key.
 */
final class VoiceAgentProxyToken
{
    public function issue(RoleplayAttempt $attempt, int $ttlSeconds): string
    {
        $payload = $attempt->id.'.'.(Date::now()->getTimestamp() + max(60, $ttlSeconds));

        return $this->encode($payload).'.'.$this->sign($payload);
    }

    /**
     * The attempt id the token was issued for, or null when it is forged,
     * malformed or expired.
     */
    public function verify(string $token): ?int
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        $payload = $this->decode($parts[0]);

        if ($payload === null || ! hash_equals($this->sign($payload), $parts[1])) {
            return null;
        }

        $fields = explode('.', $payload);

        if (count($fields) !== 2 || ! ctype_digit($fields[0]) || ! ctype_digit($fields[1])) {
            return null;
        }

        if ((int) $fields[1] < Date::now()->getTimestamp()) {
            return null;
        }

        return (int) $fields[0];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', 'voice-agent-llm|'.$payload, (string) config('app.key'));
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
