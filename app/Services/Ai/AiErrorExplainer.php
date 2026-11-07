<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Plain words for the AI provider errors an admin can act on (client
 * report 2026-11-07: "AI creation does not work" — the panel only showed
 * "HTTP request returned status code 400"). The original message is kept
 * after the explanation, shortened, for whoever debugs it.
 */
final class AiErrorExplainer
{
    public static function explain(?Throwable $exception, string $fallback): string
    {
        if ($exception === null) {
            return $fallback;
        }

        $raw = $exception->getMessage();

        if ($exception instanceof RequestException) {
            $raw .= ' '.(string) $exception->response->body();
        }

        $text = strtolower($raw);

        $plain = match (true) {
            str_contains($text, 'credit balance is too low') => __('The Claude (Anthropic) account that writes the text has no credit left. Add credit at console.anthropic.com (Settings, then Billing), then press Try again.'),
            str_contains($text, 'access to model denied'), str_contains($text, 'not eligible') => __('The image account (Alibaba Model Studio) is not allowed to use this image model. Enable the model in the Alibaba console, or choose another image model in Settings, AI models.'),
            str_contains($text, 'invalid x-api-key'), str_contains($text, 'invalid api key'), str_contains($text, 'invalid_api_key'), str_contains($text, 'authentication_error') => __('The AI provider refused the API key. Check the key on the owner console (/owner) or in the .env file.'),
            str_contains($text, 'rate_limit'), str_contains($text, 'status code 429') => __('The AI provider is busy (too many requests). Wait a minute, then press Try again.'),
            str_contains($text, 'overloaded'), str_contains($text, 'status code 529'), str_contains($text, 'status code 503') => __('The AI provider is overloaded right now. Wait a few minutes, then press Try again.'),
            str_contains($text, 'timed out'), str_contains($text, 'curl error 28') => __('The AI provider took too long to answer. Press Try again.'),
            default => null,
        };

        if (! is_string($plain)) {
            return $raw !== '' ? mb_substr($raw, 0, 500) : $fallback;
        }

        return $plain.' ('.mb_substr(trim($exception->getMessage()), 0, 160).')';
    }
}
