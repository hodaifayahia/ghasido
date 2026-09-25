<?php

namespace App\Enums;

/**
 * Which certificate an employee earned (CERT-02, CERT-04, spec 0003 B.6).
 *
 * Which one is issued is decided by the admin-defined completion condition,
 * never by a constant: `completion` may require a minimum Post-test score,
 * `participation` only that the training was followed.
 */
enum CertificateType: string
{
    case Participation = 'participation';
    case Completion = 'completion';

    public function label(): string
    {
        return match ($this) {
            self::Participation => __('Certificate of Participation'),
            self::Completion => __('Certificate of Completion'),
        };
    }
}
