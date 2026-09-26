<?php

namespace App\Enums;

/**
 * The eleven answerable item types (PRAC-01..03, TEST-05, WRITE-01, spec 0003
 * B.8, B.9).
 *
 * The same type serves lesson practice and both tests (PRAC-05, WRITE-05):
 * an activity is placed through activity_placements, never duplicated. The
 * payload shape for each case is fixed in spec 0003 B.9 and scored by
 * App\Services\Learning\ActivityScorer.
 */
enum ActivityType: string
{
    case ListenChoose = 'listen_choose';
    case LookListen = 'look_listen';
    case BestResponse = 'best_response';
    case ListenMatch = 'listen_match';
    case WatchRespond = 'watch_respond';
    case WordsSentences = 'words_sentences';
    case DialogueOrder = 'dialogue_order';
    case PictureOrder = 'picture_order';
    case MultipleChoice = 'multiple_choice';
    case Speaking = 'speaking';
    case Writing = 'writing';

    public function label(?string $locale = null): string
    {
        return match ($this) {
            self::ListenChoose => __('Listen & Choose', [], $locale),
            self::LookListen => __('Look & Listen', [], $locale),
            self::BestResponse => __('Best Response', [], $locale),
            self::ListenMatch => __('Listen & Match', [], $locale),
            self::WatchRespond => __('Watch & Respond', [], $locale),
            self::WordsSentences => __('Words & Sentences', [], $locale),
            self::DialogueOrder => __('Put the Dialogue in Order', [], $locale),
            self::PictureOrder => __('Ordering a Conversation', [], $locale),
            self::MultipleChoice => __('Choose the Best Answer', [], $locale),
            self::Speaking => __('Speaking', [], $locale),
            self::Writing => __('Writing', [], $locale),
        };
    }

    /**
     * The sentence under the title on the practice hub card (photo_7).
     */
    public function hubDescription(?string $locale = null): string
    {
        return match ($this) {
            self::ListenChoose => __('Listen to a word or sentence and choose the correct picture.', [], $locale),
            self::LookListen => __('Look at the picture and choose the correct audio.', [], $locale),
            self::BestResponse => __('Listen to the guest and choose the most appropriate response.', [], $locale),
            self::ListenMatch => __('Listen and match the words with the correct pictures.', [], $locale),
            self::WatchRespond => __('Watch a short video and choose what to say or do next.', [], $locale),
            self::WordsSentences => __('Complete short words or sentences (use the keyboard).', [], $locale),
            self::DialogueOrder => __('Listen and put the sentences in the correct order.', [], $locale),
            self::PictureOrder => __('Put the conversation in the correct order.', [], $locale),
            self::MultipleChoice => __('Read or look, then choose the best answer.', [], $locale),
            self::Speaking => __('Record a short spoken answer.', [], $locale),
            self::Writing => __('Write a short reply.', [], $locale),
        };
    }

    /**
     * The icon chip tone on the hub card (spec 0003 B.8). `sunset` and
     * `blossom` are Guesvia tokens, never Tailwind palette names; `blossom` is
     * the new token the frontend lane samples from photo_7/12.
     */
    public function tone(): string
    {
        return match ($this) {
            self::ListenChoose, self::PictureOrder, self::MultipleChoice => 'brand',
            self::LookListen, self::DialogueOrder => 'success',
            self::BestResponse => 'sunset',
            self::ListenMatch, self::Speaking => 'ai',
            self::WatchRespond => 'blossom',
            self::WordsSentences => 'gold',
            self::Writing => 'aqua',
        };
    }

    /**
     * The `@lucide/vue` export for the hub card chip. A proposal for the
     * practice lanes: the mockup glyphs that are solid become
     * `components/icons/*` per AGENTS.md §0.2 rule 5.
     */
    public function icon(): string
    {
        return match ($this) {
            self::ListenChoose => 'Headphones',
            self::LookListen => 'Image',
            self::BestResponse => 'MessageCircle',
            self::ListenMatch => 'Link',
            self::WatchRespond => 'Video',
            self::WordsSentences => 'Keyboard',
            self::DialogueOrder => 'ListOrdered',
            self::PictureOrder => 'Images',
            self::MultipleChoice => 'ListChecks',
            self::Speaking => 'Mic',
            self::Writing => 'PenLine',
        };
    }

    /**
     * Can the platform score this without an AI or a human (TEST-06, AIE-01)?
     *
     * Speaking and writing store the answer verbatim with `is_correct` null;
     * a queued evaluation fills `ai_feedback` later (WRITE-04, TEST-08).
     */
    public function isAutoScored(): bool
    {
        return ! in_array($this, [self::Speaking, self::Writing], true);
    }

    /**
     * How the raw answer for one item is shaped (spec 0003 B.9).
     */
    public function answerShape(): AnswerShape
    {
        return match ($this) {
            self::ListenChoose,
            self::LookListen,
            self::BestResponse,
            self::WatchRespond,
            self::WordsSentences,
            self::MultipleChoice => AnswerShape::Option,
            self::ListenMatch => AnswerShape::PairMap,
            self::DialogueOrder,
            self::PictureOrder => AnswerShape::OrderedList,
            self::Speaking => AnswerShape::Recording,
            self::Writing => AnswerShape::Text,
        };
    }
}
