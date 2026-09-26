<?php

// One-off deploy helper: copy the dev MySQL database into a fresh SQLite file
// migrated by the current code, harden seed passwords in the copy, and list
// the public media the data references. Reads MySQL only; writes only under
// storage/logs/deploy/.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../../vendor/autoload.php';
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = __DIR__.'/../../../storage/logs/deploy';
@mkdir($dir, 0775, true);
$file = $dir.'/database.sqlite';
@unlink($file);
@unlink($file.'-wal');
@unlink($file.'-shm');
touch($file);

config(['database.connections.deploy' => [
    'driver' => 'sqlite',
    'database' => $file,
    'prefix' => '',
    'foreign_key_constraints' => false,
]]);

$code = Artisan::call('migrate', ['--database' => 'deploy', '--force' => true]);
echo "migrate exit={$code}".PHP_EOL;

$src = DB::connection('mysql');
$dst = DB::connection('deploy');
$dst->statement('PRAGMA foreign_keys = OFF');

$skip = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];
$sourceTables = array_map(fn ($row) => array_values((array) $row)[0], $src->select('SHOW TABLES'));
$targetTables = array_flip(Schema::connection('deploy')->getTableListing(schemaQualified: false));

$mismatch = [];
$total = 0;
foreach ($sourceTables as $table) {
    if (in_array($table, $skip, true)) {
        continue;
    }
    if (! isset($targetTables[$table])) {
        $mismatch[] = "{$table}: not in migrated schema";

        continue;
    }

    $srcCols = Schema::connection('mysql')->getColumnListing($table);
    $dstCols = Schema::connection('deploy')->getColumnListing($table);
    $cols = array_values(array_intersect($srcCols, $dstCols));
    $missing = array_diff($srcCols, $dstCols);
    if ($missing !== []) {
        $mismatch[] = "{$table}: columns only in MySQL: ".implode(',', $missing);
    }

    $dst->beginTransaction();
    // Migrations seed some tables (permissions, plans...): the dev data wins.
    $dst->table($table)->delete();
    $batch = [];
    $count = 0;
    foreach ($src->table($table)->select($cols)->cursor() as $row) {
        $batch[] = (array) $row;
        if (count($batch) >= 200) {
            $dst->table($table)->insert($batch);
            $count += count($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        $dst->table($table)->insert($batch);
        $count += count($batch);
    }
    $dst->commit();

    $srcCount = $src->table($table)->count();
    $dstCount = $dst->table($table)->count();
    $total += $dstCount;
    if ($srcCount !== $dstCount) {
        $mismatch[] = "{$table}: mysql={$srcCount} sqlite={$dstCount}";
    }
}
echo "rows copied: {$total}".PHP_EOL;
echo 'mismatches: '.($mismatch === [] ? 'none' : PHP_EOL.'  '.implode(PHP_EOL.'  ', $mismatch)).PHP_EOL;

// Harden: no account on a public server keeps the seed password "password".
$locked = [];
$newOwnerPassword = null;
$ownerId = (int) $dst->table('model_has_roles')
    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
    ->where('roles.name', 'super_admin')
    ->min('model_has_roles.model_id');

foreach ($dst->table('users')->get(['id', 'username', 'email', 'password']) as $user) {
    if (! is_string($user->password) || ! Hash::check('password', $user->password)) {
        continue;
    }
    if ((int) $user->id === $ownerId) {
        $newOwnerPassword = 'Gv-'.Str::password(18, symbols: false);
        $dst->table('users')->where('id', $user->id)->update(['password' => Hash::make($newOwnerPassword)]);

        continue;
    }
    $dst->table('users')->where('id', $user->id)->update(['password' => Hash::make(Str::random(40))]);
    $locked[] = $user->username ?? $user->email;
}
$owner = $dst->table('users')->where('id', $ownerId)->first(['id', 'name', 'username', 'email']);
echo 'owner super admin: '.json_encode($owner).PHP_EOL;
echo 'owner password: '.($newOwnerPassword === null ? 'unchanged (not the seed password)' : 'RESET to '.$newOwnerPassword).PHP_EOL;
echo 'accounts locked (seed password replaced by an unknown random one): '.count($locked).PHP_EOL;
echo '  e.g. '.implode(', ', array_slice($locked, 0, 12)).PHP_EOL;

// Media the data references, for a targeted upload.
$refs = [];
$pattern = '#content/[A-Za-z0-9_\-./]+\.(?:mp3|wav|ogg|m4a|webm|webp|jpe?g|png|gif|svg|json|pdf)#';
foreach (array_keys($targetTables) as $table) {
    if (in_array($table, $skip, true) || $table === 'sqlite_sequence') {
        continue;
    }
    foreach ($dst->table($table)->cursor() as $row) {
        foreach ((array) $row as $value) {
            if (is_string($value) && str_contains($value, 'content/') && preg_match_all($pattern, str_replace('\\/', '/', $value), $m)) {
                foreach ($m[0] as $path) {
                    $refs[$path] = true;
                }
            }
        }
    }
}
$root = storage_path('app/public');
$existing = [];
$missingFiles = 0;
foreach (array_keys($refs) as $path) {
    if (is_file($root.'/'.$path)) {
        $existing[] = $path;
    } else {
        $missingFiles++;
    }
}
sort($existing);
file_put_contents($dir.'/media-list.txt', implode("\n", $existing)."\n");
$bytes = array_sum(array_map(fn ($p) => filesize($root.'/'.$p), $existing));
echo 'media referenced: '.count($refs).', on disk: '.count($existing).', missing: '.$missingFiles.', size: '.round($bytes / 1048576, 1).' MB'.PHP_EOL;

$dst->statement('VACUUM');
$mode = $dst->selectOne('PRAGMA journal_mode = WAL');
$dst->disconnect();
echo 'journal_mode: '.json_encode($mode).', sqlite size: '.round(filesize($file) / 1048576, 1).' MB'.PHP_EOL;
