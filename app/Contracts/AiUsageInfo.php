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
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public string $model,
        public string $provider,
    ) {}

    /**
     * The usage a fake or otherwise free call reports.
     */
    public static function none(string $provider = 'fake', string $model = 'fake'): self
    {
        return new self(0, 0, $model, $provider);
    }
}
