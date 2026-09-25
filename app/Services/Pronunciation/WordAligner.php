<?php

namespace App\Services\Pronunciation;

use Closure;

/**
 * Lines the words a recogniser heard up against the words the learner was
 * asked to say (spec 0006 §5): a Needleman–Wunsch alignment over word keys.
 *
 * A same-word pair costs 0; a substitution costs 0.5–1, cheaper the more
 * alike the two words sound, so "very" pairs with "furry" rather than being
 * called missing next to an extra word; a missing or an extra word costs 1.
 * Ties prefer the diagonal, so a pairing wins over a gap.
 */
final class WordAligner
{
    public const MATCH = 'match';

    public const SUBSTITUTE = 'substitute';

    public const MISSING = 'missing';

    public const EXTRA = 'extra';

    /**
     * @param  list<string>  $reference
     * @param  list<string>  $heard
     * @param  Closure(int, string): bool  $same  is heard word `$h` the reference word at index `$r`?
     * @return list<array{op: string, ref: int|null, heard: int|null, similarity: float}>
     */
    public function align(array $reference, array $heard, Closure $same): array
    {
        $n = count($reference);
        $m = count($heard);

        /** @var array<int, array<int, float>> $cost */
        $cost = [];
        /** @var array<int, array<int, float>> $similarity */
        $similarity = [];

        for ($i = 0; $i <= $n; $i++) {
            $cost[$i][0] = (float) $i;
        }

        for ($j = 0; $j <= $m; $j++) {
            $cost[0][$j] = (float) $j;
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $isSame = $same($i - 1, $heard[$j - 1]);
                $alike = $isSame ? 1.0 : SpokenWords::similarity($reference[$i - 1], $heard[$j - 1]);
                $similarity[$i][$j] = $alike;

                $diagonal = $cost[$i - 1][$j - 1] + ($isSame ? 0.0 : 1.0 - 0.5 * $alike);

                $cost[$i][$j] = min(
                    $diagonal,
                    $cost[$i - 1][$j] + 1.0,
                    $cost[$i][$j - 1] + 1.0,
                );
            }
        }

        $ops = [];
        $i = $n;
        $j = $m;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0) {
                $isSame = $same($i - 1, $heard[$j - 1]);
                $step = $isSame ? 0.0 : 1.0 - 0.5 * $similarity[$i][$j];

                if (abs($cost[$i][$j] - ($cost[$i - 1][$j - 1] + $step)) < 1e-9) {
                    $ops[] = [
                        'op' => $isSame ? self::MATCH : self::SUBSTITUTE,
                        'ref' => $i - 1,
                        'heard' => $j - 1,
                        'similarity' => $similarity[$i][$j],
                    ];
                    $i--;
                    $j--;

                    continue;
                }
            }

            if ($i > 0 && abs($cost[$i][$j] - ($cost[$i - 1][$j] + 1.0)) < 1e-9) {
                $ops[] = ['op' => self::MISSING, 'ref' => $i - 1, 'heard' => null, 'similarity' => 0.0];
                $i--;

                continue;
            }

            $ops[] = ['op' => self::EXTRA, 'ref' => null, 'heard' => $j - 1, 'similarity' => 0.0];
            $j--;
        }

        return array_reverse($ops);
    }
}
