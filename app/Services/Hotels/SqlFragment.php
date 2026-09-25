<?php

namespace App\Services\Hotels;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A piece of SQL composed at runtime, for the capacity filter's correlated
 * subqueries (Hotel::scopeWithCapacity).
 *
 * whereRaw() is typed for literal strings, which a subquery built from
 * another query's toSql() can never be. Handing it an Expression instead
 * keeps the composition explicit and the static analysis honest.
 */
final class SqlFragment implements Expression
{
    public function __construct(private readonly string $sql) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->sql;
    }
}
