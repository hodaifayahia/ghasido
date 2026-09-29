<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\BlockType;
use App\Models\Block;
use Faker\Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression, client report 2026-09-29: the "Note / Tip" tile answered
 * "500 Server Error". Adding a block took its default settings from the
 * model factory, whose generic types called `fake()`; production installs
 * Composer with `--no-dev`, so Faker is missing there and the call threw.
 * Here Faker is made to fail the same way after the fixtures are built.
 */
class NoteBlockRegressionTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();

        // What a --no-dev install looks like to the `fake()` helper.
        $locale = (string) config('app.faker_locale', 'en_US');
        $this->app->singleton(Generator::class.':'.$locale, function (): never {
            throw new RuntimeException('Faker is a dev dependency and is not installed in production.');
        });
    }

    public function test_the_note_tip_tile_adds_a_note_block_without_the_faker_dev_dependency()
    {
        $this->actingAs($this->owner)
            ->post(route('blocks.store', $this->lesson), ['type' => 'note'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $note = $this->lesson->blocks()->where('type', BlockType::Note->value)->firstOrFail();

        $this->assertSame(['body' => '', 'arabic' => null, 'image' => null, 'audio_text' => null], $note->settings);

        // The editor then opens and saves it.
        $this->actingAs($this->owner)
            ->get(route('lessons.edit', $this->lesson))
            ->assertOk();

        $this->actingAs($this->owner)
            ->patch(route('blocks.update', $note), [
                'title' => 'Tip',
                'settings' => ['body' => 'Smile and make eye contact.', 'arabic' => null, 'image' => null, 'audio_text' => null],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Smile and make eye contact.', $note->fresh()?->setting('body'));
    }

    public function test_every_block_type_can_be_added_without_the_faker_dev_dependency()
    {
        foreach (BlockType::cases() as $type) {
            $this->actingAs($this->owner)
                ->post(route('blocks.store', $this->lesson), ['type' => $type->value])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(
            count(BlockType::cases()),
            Block::query()->where('lesson_id', $this->lesson->id)->count() - 9,
        );
    }

    public function test_a_new_lesson_gets_its_default_blocks_without_the_faker_dev_dependency()
    {
        $this->actingAs($this->owner)
            ->post(route('lessons.store'), ['unit_id' => $this->unit->id, 'title' => 'Checking Out'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
