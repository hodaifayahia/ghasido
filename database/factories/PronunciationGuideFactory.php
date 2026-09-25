<?php

namespace Database\Factories;

use App\Enums\Accent;
use App\Enums\GenerationStatus;
use App\Models\AudioClip;
use App\Models\PronunciationGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PronunciationGuide>
 */
class PronunciationGuideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = 'Would you like a very quiet room?';

        return [
            'text' => $text,
            'text_hash' => AudioClip::hashFor($text),
            'accent' => Accent::American,
            'status' => GenerationStatus::Pending,
            'source' => PronunciationGuide::SOURCE_AI,
            'words' => null,
            'tips' => null,
            'calibration' => null,
        ];
    }

    /**
     * A generated guide whose "very" has the v → f trap.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GenerationStatus::Done,
            'ipa' => '/wʊd ju laɪk ə ˈvɛri ˈkwaɪət ruːm/',
            'words' => [
                ['word' => 'very', 'ipa' => '/ˈvɛri/', 'syllables' => 'VE·ry', 'sounds_like' => 'VAIR-ee', 'tip' => 'Top teeth on your lower lip for v.', 'traps' => [['heard_as' => 'ferry', 'sound' => 'v → f', 'tip' => 'Make v buzz: top teeth on your lip.']], 'homophones' => []],
            ],
            'tips' => ['Keep v and f apart.'],
            'provider' => 'fake',
            'model' => 'fake',
            'generated_at' => now(),
        ]);
    }
}
