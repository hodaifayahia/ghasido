<?php

namespace App\Services\Pronunciation;

/**
 * Names the sound that went wrong when a word was heard as another word
 * (spec 0006 §4 "what we know"): the sounds Arabic-speaking learners of
 * English most often swap, read off the letters that differ.
 *
 * "very" heard as "furry" differs at v/f → "v → f"; "think" heard as "sync"
 * at th/s → "th → s"; "reservation" heard as "resurfation" at e/u and v/f,
 * and the consonant wins. A deterministic fallback for when the Qwen guide
 * has no trap for the word; the guide's own trap wins when it has one.
 */
final class SoundDiff
{
    /**
     * Letter groups of the target sound → letter groups it is heard as →
     * the label shown to the learner. Consonants first: when a word differs
     * in a vowel and a consonant, the consonant is what the listener missed.
     *
     * @var list<array{0: list<string>, 1: list<string>, 2: string}>
     */
    public const PAIRS = [
        [['th'], ['s', 'c'], 'th → s'],
        [['th'], ['t'], 'th → t'],
        [['th'], ['d'], 'th → d'],
        [['th'], ['z'], 'th → z'],
        [['th'], ['f'], 'th → f'],
        [['ng'], ['n'], 'ng → n'],
        [['v'], ['f', 'ph'], 'v → f'],
        [['v'], ['w'], 'v → w'],
        [['w'], ['v'], 'w → v'],
        [['p'], ['b'], 'p → b'],
        [['b'], ['p'], 'b → p'],
        [['ch'], ['sh'], 'ch → sh'],
        [['sh'], ['ch', 's'], 'sh → ch'],
        [['r'], [''], 'missing r'],
        [['i'], ['e', 'ee', 'ea'], 'short i → long ee'],
        [['e'], ['i', 'a'], 'e → i'],
        [['a'], ['e'], 'a → e'],
        [['o'], ['u', 'oo'], 'o → u'],
        [['u'], ['o'], 'u → o'],
    ];

    /**
     * The swapped sound, or null when the difference is not a known swap.
     */
    public static function explain(string $target, string $heard): ?string
    {
        if ($target === $heard || $target === '' || $heard === '') {
            return null;
        }

        $segments = self::segments($target, $heard);

        foreach (self::PAIRS as [$from, $to, $label]) {
            foreach ($segments as [$before, $a, $b, $after]) {
                foreach ([[$a, $b], [$before.$a, $before.$b], [$a.$after, $b.$after]] as [$left, $right]) {
                    if (self::swaps($from, $to, $left, $right)) {
                        return $label;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $from
     * @param  list<string>  $to
     */
    private static function swaps(array $from, array $to, string $a, string $b): bool
    {
        foreach ($from as $source) {
            if (! str_starts_with($a, $source)) {
                continue;
            }

            foreach ($to as $replacement) {
                if ($replacement === '' ? ($b === '' && $a === $source) : str_starts_with($b, $replacement)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The runs of letters that differ between two words, each with the
     * shared letter just before and just after it, so a digraph split by a
     * shared letter — "th" in three / tree, "sh" in ship / chip — is still
     * seen whole.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private static function segments(string $a, string $b): array
    {
        $n = strlen($a);
        $m = strlen($b);
        $d = [];

        for ($i = 0; $i <= $n; $i++) {
            $d[$i][0] = $i;
        }

        for ($j = 0; $j <= $m; $j++) {
            $d[0][$j] = $j;
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                $d[$i][$j] = min(
                    $d[$i - 1][$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1),
                    $d[$i - 1][$j] + 1,
                    $d[$i][$j - 1] + 1,
                );
            }
        }

        // Walk back from the end, collecting [kind, a-char, b-char].
        $ops = [];
        $i = $n;
        $j = $m;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $d[$i][$j] === $d[$i - 1][$j - 1] + ($a[$i - 1] === $b[$j - 1] ? 0 : 1)) {
                $ops[] = [$a[$i - 1] === $b[$j - 1] ? '=' : '~', $a[$i - 1], $b[$j - 1]];
                $i--;
                $j--;
            } elseif ($i > 0 && $d[$i][$j] === $d[$i - 1][$j] + 1) {
                $ops[] = ['-', $a[$i - 1], ''];
                $i--;
            } else {
                $ops[] = ['+', '', $b[$j - 1]];
                $j--;
            }
        }

        $segments = [];
        $context = '';
        $open = null;

        foreach (array_reverse($ops) as [$kind, $left, $right]) {
            if ($kind === '=') {
                if ($open !== null) {
                    $open[3] = $left;
                    $segments[] = $open;
                    $open = null;
                }

                $context = $left;

                continue;
            }

            $open ??= [$context, '', '', ''];
            $open[1] .= $left;
            $open[2] .= $right;
        }

        if ($open !== null) {
            $segments[] = $open;
        }

        return $segments;
    }
}
