<?php

namespace Database\Factories;

use App\Models\AudioClip;
use App\Models\VoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceLine>
 */
class VoiceLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->unique()->sentence(6);

        return [
            'ai_scenario_id' => null,
            'voice' => 'aura-2-thalia-en',
            'text' => $text,
            'text_hash' => AudioClip::hashFor($text),
            'audio_clip_id' => null,
            'reusable' => true,
            'uses' => 1,
            'reuses' => 0,
            'last_used_at' => now(),
        ];
    }

    /** With a generated recording in the same voice. */
    public function recorded(): static
    {
        return $this->afterMaking(function (VoiceLine $line): void {
            $line->audio_clip_id = AudioClip::factory()->done()->create([
                'text' => $line->text,
                'voice' => $line->voice,
            ])->id;
        });
    }
}
