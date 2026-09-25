<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VoiceRecording;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VoiceRecording>
 */
class VoiceRecordingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            // getMorphClass() so the stored type matches whatever morph map
            // the content lane registers for blocks.
            'recordable_type' => (new Block)->getMorphClass(),
            'recordable_id' => Block::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'duration_ms' => 5200,
            'transcript' => null,
        ];
    }
}
