<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An AI service's credit is running low (20% left) or used up (spec 0007,
 * D12). Sent to the platform owner, who can recharge on the owner console,
 * and to every Super Admin, who is told to ask for a recharge before the
 * AI features pause for her learners. Carries the Super Admin's figures
 * only (CreditSummary), never the owner's cost.
 */
class ApiCreditAlertMail extends BrandedMailable implements ShouldQueue
{
    public const string LOW = 'low';

    public const string EMPTY = 'empty';

    /**
     * @param  array{account: string, service: string, provider: string, state: string, mode: string, creditUsd: float, remainingUsd: float|null, usedUsd: float|null, shareLeft: float|null, meters: list<array{meter: string, label: string, granted: int, left: int}>}  $summary
     */
    public function __construct(
        public string $level,
        public array $summary,
        public bool $forOwner,
        public string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->level === self::EMPTY
                ? __('GHASIDO: the :service credit has run out', ['service' => $this->summary['service']])
                : __('GHASIDO: the :service credit is running low', ['service' => $this->summary['service']]),
        );
    }

    public function content(): Content
    {
        $lines = [];

        foreach ($this->summary['meters'] as $meter) {
            $left = $meter['meter'] === 'seconds' ? intdiv($meter['left'], 60) : $meter['left'];
            $granted = $meter['meter'] === 'seconds' ? intdiv($meter['granted'], 60) : $meter['granted'];
            $unit = match ($meter['meter']) {
                'seconds' => __('minutes'),
                'characters' => __('speech characters'),
                default => __('tokens'),
            };
            $lines[] = __(':left of :granted :unit left', [
                'left' => number_format($left),
                'granted' => number_format($granted),
                'unit' => $unit,
            ]);
        }

        return new Content(
            view: 'mail.api-credit-alert',
            text: 'mail.text.api-credit-alert',
            with: [
                'name' => $this->recipientName,
                'empty' => $this->level === self::EMPTY,
                'service' => $this->summary['service'],
                'provider' => $this->summary['provider'],
                'dollars' => $this->summary['creditUsd'] > 0 && $this->summary['remainingUsd'] !== null
                    ? __('$:left left of $:credit', [
                        'left' => number_format($this->summary['remainingUsd'], 2),
                        'credit' => number_format($this->summary['creditUsd'], 2),
                    ])
                    : null,
                'units' => $lines,
                'forOwner' => $this->forOwner,
                'url' => $this->forOwner ? route('owner.dashboard') : route('dashboard'),
            ],
        );
    }
}
