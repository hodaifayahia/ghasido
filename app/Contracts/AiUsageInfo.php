<?php

namespace App\Contracts;

/**
 * What one provider call cost, as the provider reported it.
 *
 * Every DTO an AiProvider returns carries one of these so the caller can
 * meter it into ai_usages without knowing which provider ran (AIL-04,
 * API-03; spec 0003 Part C).
 */
final readonly class AiUsageInfo
{
    /**
     * `promptTokens` is every input token the call used. Of those,
     * `cacheReadTokens` were read from the prompt cache and
     * `cacheWriteTokens` written to it; providers that bill them at other
     * rates (Anthropic: 0.1x and 1.25x input) report them, so the meter can
     * price them right (API-03; spec 0007, D5).
     */
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public string $model,
        public string $provider,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
    ) {}

    /**
     * The usage a fake or otherwise free call reports.
     */
    public static function none(string $provider = 'fake', string $model = 'fake'): self
    {
        return new self(0, 0, $model, $provider);
    }
}
