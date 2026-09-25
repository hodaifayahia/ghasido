<?php

namespace App\Services\Reports\Exporters;

use App\Models\Attempt;

/**
 * The research export: the long-format answers keyed on the participant
 * code only (REP-07). No name, username or email leaves the platform.
 * Requires `reports.export_anonymised`, checked by the form request.
 */
final class AnonymisedExport extends AnswersExport
{
    public function key(): string
    {
        return self::DATASET_ANONYMISED;
    }

    public function title(): string
    {
        return __('Anonymised Research Export');
    }

    /**
     * @return list<string>
     */
    protected function identityHeaders(): array
    {
        return ['Answer ID', 'Participant code'];
    }

    /**
     * @return list<mixed>
     */
    protected function identityCells(Attempt $attempt): array
    {
        return [
            $attempt->id,
            $attempt->user?->participant_code,
        ];
    }
}
