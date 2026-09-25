<?php

namespace Tests\Unit\Pronunciation;

use App\Services\Pronunciation\SoundDiff;
use App\Services\Pronunciation\SpokenWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The sound swaps the probe produced (spec 0006 §2) are named, and the
 * word comparison sorts close words from different ones.
 */
class SoundDiffTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: string|null}>
     */
    public static function swaps(): array
    {
        return [
            'very → furry' => ['very', 'furry', 'v → f'],
            'very → ferry' => ['very', 'ferry', 'v → f'],
            'very → free' => ['very', 'free', 'v → f'],
            'think → sync' => ['think', 'sync', 'th → s'],
            'think → sink' => ['think', 'sink', 'th → s'],
            'three → tree' => ['three', 'tree', 'th → t'],
            'parking → barking' => ['parking', 'barking', 'p → b'],
            'bill → pill' => ['bill', 'pill', 'b → p'],
            'reservation → resurfation' => ['reservation', 'resurfation', 'v → f'],
            'this → dis' => ['this', 'dis', 'th → d'],
            'ship → chip' => ['ship', 'chip', 'sh → ch'],
            'room → rooms (not a swap)' => ['room', 'rooms', null],
            'same word' => ['room', 'room', null],
        ];
    }

    #[DataProvider('swaps')]
    public function test_it_names_the_swapped_sound(string $target, string $heard, ?string $expected)
    {
        $this->assertSame($expected, SoundDiff::explain($target, $heard));
    }

    public function test_close_words_score_above_the_threshold_and_unrelated_words_below()
    {
        foreach ([['very', 'furry'], ['think', 'sync'], ['bill', 'pill'], ['vegetarian', 'sagittarian'], ['parking', 'barking'], ['three', 'tree']] as [$a, $b]) {
            $this->assertGreaterThanOrEqual(0.5, SpokenWords::similarity($a, $b), "$a / $b");
        }

        foreach ([['room', 'sim'], ['towel', 'breakfast'], ['help', 'key']] as [$a, $b]) {
            $this->assertLessThan(0.5, SpokenWords::similarity($a, $b), "$a / $b");
        }
    }

    public function test_keys_fold_british_spelling_and_punctuation()
    {
        $this->assertSame('color', SpokenWords::key('Colour,'));
        $this->assertSame('favorite', SpokenWords::key('favourite'));
        $this->assertSame('organized', SpokenWords::key('organised'));
        $this->assertSame('center', SpokenWords::key('Centre.'));
        $this->assertSame("i'm", SpokenWords::key('I’m'));
        $this->assertSame('tour', SpokenWords::key('tour'));
        $this->assertSame('hour', SpokenWords::key('hour'));
    }
}
