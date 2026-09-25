<?php

namespace App\Enums;

/**
 * A lexicon item is either a word or an expression (CMS-06, spec 0003 B.5).
 *
 * The vocabulary block draws words, the expressions block draws expressions;
 * both share the one lexicon_items table so the phrasebook and Show Meaning
 * are built once (CTRL-03, PHRASE-02).
 */
enum LexiconKind: string
{
    case Word = 'word';
    case Expression = 'expression';

    public function label(): string
    {
        return match ($this) {
            self::Word => __('Word'),
            self::Expression => __('Expression'),
        };
    }
}
