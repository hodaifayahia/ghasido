<?php

use App\Models\User;

foreach (User::query()->orderBy('id')->get(['username', 'role', 'status']) as $u) {
    echo $u->username.' | '.$u->role->value.' | '.$u->status->value.PHP_EOL;
}
