<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;
use Throwable;

/**
 * The SQLite → MySQL move (client request 2026-09-26): every row arrives
 * with its id, Arabic text, dates and JSON intact, and a row MySQL would
 * refuse stops the copy before anything is written.
 *
 * Works on its own temporary SQLite file and its own MySQL database, never
 * on the suite's test database. Needs a MySQL server: set
 * COPY_TEST_MYSQL_DATABASE (plus DB_HOST, DB_USERNAME, DB_PASSWORD) to a
 * database the test may wipe. Skipped otherwise, as in CI.
 */
class CopySqliteToMysqlTest extends TestCase
{
    // The suite's own database, rolled back as usual; the copy works on the
    // two connections below.
    use RefreshDatabase;

    private string $source;

    protected function setUp(): void
    {
        $database = getenv('COPY_TEST_MYSQL_DATABASE');

        if ($database === false || $database === '') {
            $this->markTestSkipped('Set COPY_TEST_MYSQL_DATABASE to test the MySQL copy.');
        }

        parent::setUp();

        $this->source = tempnam(sys_get_temp_dir(), 'copy-source-').'.sqlite';
        touch($this->source);

        config([
            'database.connections.copysource' => array_merge(config('database.connections.sqlite'), ['database' => $this->source]),
            'database.connections.copytarget' => array_merge(config('database.connections.mysql'), ['database' => $database]),
        ]);

        try {
            DB::connection('copytarget')->getPdo();
        } catch (Throwable $e) {
            $this->markTestSkipped('MySQL is not reachable: '.$e->getMessage());
        }

        Artisan::call('migrate:fresh', ['--database' => 'copysource', '--force' => true]);
        Artisan::call('migrate:fresh', ['--database' => 'copytarget', '--force' => true]);
    }

    protected function tearDown(): void
    {
        if (isset($this->source)) {
            DB::purge('copysource');
            DB::purge('copytarget');
            @unlink($this->source);
        }

        parent::tearDown();
    }

    public function test_every_row_is_copied_with_its_id_arabic_text_dates_and_json()
    {
        $source = DB::connection('copysource');
        $id = $this->insertUser(['name' => 'سميرة 🌴 Benali', 'last_login_at' => '2026-09-20T08:15:30.000000Z']);
        $source->table('contact_messages')->insert([
            'name' => 'Karim', 'email' => 'k@example.com', 'message' => 'مرحبا، نريد عرض سعر',
            'created_at' => '2026-09-26 10:00:00', 'updated_at' => '2026-09-26T10:05:00+01:00',
        ]);
        $source->table('landing_page_contents')->insert([
            'id' => 1, 'content' => json_encode(['hero' => ['title' => 'أهلا', 'z' => 1, 'a' => 2]]),
        ]);

        $this->copy()->expectsOutputToContain('every row count matches')->assertSuccessful();

        $target = DB::connection('copytarget');

        foreach (['users', 'contact_messages', 'landing_page_contents', 'permissions', 'subscription_plans'] as $table) {
            $this->assertSame($source->table($table)->count(), $target->table($table)->count(), $table);
        }

        $this->assertSame('سميرة 🌴 Benali', $target->table('users')->where('id', $id)->value('name'));
        $this->assertSame('2026-09-20 08:15:30', $target->table('users')->where('id', $id)->value('last_login_at'));
        $this->assertSame('2026-09-26 09:05:00', $target->table('contact_messages')->value('updated_at'));
        $this->assertSame(
            ['hero' => ['a' => 2, 'title' => 'أهلا', 'z' => 1]],
            $this->sorted(json_decode((string) $target->table('landing_page_contents')->value('content'), true)),
        );
    }

    public function test_a_value_mysql_would_refuse_stops_the_copy_before_writing()
    {
        $this->insertUser(['username' => str_repeat('x', 300)]);
        DB::connection('copysource')->table('landing_page_contents')->insert(['id' => 1, 'content' => '{not json']);

        $this->copy()
            ->expectsOutputToContain('users.username')
            ->expectsOutputToContain('landing_page_contents.content')
            ->assertFailed();

        $this->assertSame(0, DB::connection('copytarget')->table('users')->count());
    }

    public function test_it_refuses_a_target_on_other_migrations()
    {
        DB::connection('copytarget')->table('migrations')->insert(['migration' => '2099_01_01_000000_not_in_sqlite', 'batch' => 99]);

        $this->copy()->expectsOutputToContain('not on the same migrations')->assertFailed();
    }

    private function copy(): PendingCommand
    {
        $command = $this->artisan('db:copy-sqlite-to-mysql', [
            '--from' => 'copysource',
            '--sqlite-path' => $this->source,
            '--to' => 'copytarget',
            '--fresh' => true,
        ]);
        assert($command instanceof PendingCommand);

        return $command;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertUser(array $overrides): int
    {
        $row = array_merge(User::factory()->make()->getAttributes(), [
            'password' => 'hashed',
            'created_at' => '2026-09-01 09:00:00',
            'updated_at' => '2026-09-01 09:00:00',
        ], $overrides);

        return (int) DB::connection('copysource')->table('users')->insertGetId($row);
    }

    private function sorted(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map($this->sorted(...), $value);
        ksort($value);

        return $value;
    }
}
