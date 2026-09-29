<?php

namespace App\Services\Ai;

use App\Enums\AiFeature;
use App\Enums\ApiAccount;
use RuntimeException;

/**
 * Thrown by UsageMeter::assertWithinLimits() when a daily AI quota is spent
 * (AIL-01..AIL-03).
 *
 * The message is user facing on purpose: callers render it as a flash error
 * and leave the rest of the platform usable (AIL-03). Nothing is dispatched
 * once this is thrown, so no API budget is spent past the limit.
 */
final class AiLimitReached extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly AiFeature $feature,
        public readonly string $scope,
        public readonly int $limit,
    ) {
        parent::__construct($message);
    }

    public static function forEmployee(AiFeature $feature, int $limit): self
    {
        return new self(
            __("You have used today's AI practice limit (:limit turns). Your lessons and phrasebook are still open; try the AI guest again tomorrow.", ['limit' => $limit]),
            $feature,
            'employee',
            $limit,
        );
    }

    /**
     * An admin has spent today's content-generation allowance (lessons or
     * images), checked before anything is queued (AIL-01..03; spec 0004).
     */
    public static function forGeneration(AiFeature $feature, int $limit): self
    {
        $message = $feature === AiFeature::ImageGenerate
            ? __("You have used today's AI image limit (:limit images). Existing lessons stay editable; try again tomorrow.", ['limit' => $limit])
            : __("You have used today's AI lesson limit (:limit lessons). Existing lessons stay editable; try again tomorrow.", ['limit' => $limit]);

        return new self($message, $feature, 'admin', $limit);
    }

    public static function forHotel(AiFeature $feature, int $limit): self
    {
        return new self(
            __("Your hotel has used today's AI practice limit (:limit turns). Your lessons and phrasebook are still open; try the AI guest again tomorrow.", ['limit' => $limit]),
            $feature,
            'hotel',
            $limit,
        );
    }

    /**
     * A paid API account the platform owner funds is spent or paused
     * (spec 0007, D7). Nothing that needs it is dispatched; everything
     * else keeps working.
     */
    public static function forCredit(ApiAccount $account, bool $paused = false): self
    {
        $message = $paused
            ? __('AI features that use :service are paused by the platform owner. Everything else still works; please try again later.', ['service' => $account->label()])
            : __("The platform's :service credit has run out, so the AI features that use it are paused. Everything else still works; the platform owner can recharge it.", ['service' => $account->label()]);

        return new self($message, AiFeature::RoleplayTurn, 'credit', 0);
    }

    /**
     * The same stop, worded for a learner: nothing about credit or owners,
     * just that AI practice is paused and the rest is open (AIL-03).
     */
    public static function forLearnerCredit(): self
    {
        return new self(
            __('AI practice is paused for now. Your lessons and phrasebook are still open; please try the AI guest again later.'),
            AiFeature::RoleplayTurn,
            'credit',
            0,
        );
    }

    /**
     * An individual subscriber whose plan does not include AI (or voice)
     * practice. Lessons, tests and the phrasebook stay open.
     */
    public static function forIndividualPlan(AiFeature $feature): self
    {
        return new self(
            $feature === AiFeature::VoiceCall
                ? __('Voice practice is not included in your subscription. Your lessons and phrasebook are still open; contact GHASIDO support to add it.')
                : __('AI practice is not included in your subscription. Your lessons and phrasebook are still open; contact GHASIDO support to add it.'),
            $feature,
            'employee',
            0,
        );
    }

    public static function forPoints(int $required, int $available, bool $individual = false): self
    {
        return new self(
            __($individual
                ? 'You have :available AI points remaining, but this feature needs :required points. Contact GHASIDO support to add more points to your subscription.'
                : 'You have :available AI points remaining, but this feature needs :required points. Ask your hotel manager to adjust your allocation.', [
                    'available' => $available,
                    'required' => $required,
                ]),
            AiFeature::RoleplayTurn,
            'employee',
            $required,
        );
    }
}
