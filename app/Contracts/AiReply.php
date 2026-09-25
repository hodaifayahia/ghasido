<?php

namespace App\Contracts;

/**
 * One guest turn in an AI role-play (RP-03; spec 0003 Part C).
 */
final readonly class AiReply
{
    public function __construct(
        public string $text,
        public AiUsageInfo $usage,
    ) {}
}
