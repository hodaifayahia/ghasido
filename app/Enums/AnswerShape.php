<?php

namespace App\Enums;

/**
 * The shapes a raw answer for one item can take (spec 0003 B.9).
 *
 * Not a stored column: ActivityType::answerShape() derives it, and the scorer
 * switches on it so every option-style type shares one comparison.
 */
enum AnswerShape: string
{
    /** One option id, e.g. `"a"`. */
    case Option = 'option';

    /** Prompt id to target id, e.g. `{"1": "a", "2": "b"}`. */
    case PairMap = 'pair_map';

    /** Item ids in the learner's order, e.g. `["s2", "s4", "s1"]`. */
    case OrderedList = 'ordered_list';

    /** `{"recording_media_id": 44, "duration_ms": 5200}`. */
    case Recording = 'recording';

    /** `{"text": "…"}`. */
    case Text = 'text';

    /** A typed short answer, e.g. `"Double room"` (or `{"text": "…"}`). */
    case Typed = 'typed';

    /** Blank id to typed word, e.g. `{"b1": "passport", "b2": "key"}`. */
    case Blanks = 'blanks';
}
