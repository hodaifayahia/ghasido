<?php

namespace App\Enums;

/**
 * The answerable item types (PRAC-01..03, TEST-05, WRITE-01, spec 0003 B.8,
 * B.9; client report 2026-09-29).
 *
 * The same type serves lesson practice and both tests (PRAC-05, WRITE-05):
 * an activity is placed through activity_placements, never duplicated. The
 * payload shape for each case is fixed in spec 0003 B.9 (the seven cases
 * added for the client's ten question types are described on builderTypes())
 * and scored by App\Services\Learning\ActivityScorer.
 *
 * The admin builders offer exactly the ten builderTypes(); the older cases
 * stay for the activities and attempts already stored with them (DATA-11).
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
    case ShortAnswer = 'short_answer';
    case FillBlank = 'fill_blank';
    case Matching = 'matching';
    case Ordering = 'ordering';
    case AudioQuestion = 'audio_question';
    case ImageQuestion = 'image_question';
    case VideoQuestion = 'video_question';

    /**
     * The client's ten question types, in the order the Pre/Post-test
     * builder and the lesson activity chooser list them (client report
     * 2026-09-29). Every item of these types may carry a prompt `question`,
     * `image`, `audio` (an uploaded / recorded / library clip id) and
     * `audio_text` (a sentence played from stored TTS, CTRL-05):
     *
     * - multiple_choice / audio_question / image_question / video_question:
     *   `options: [{id, text, image?, audio?, audio_text?}]`, `option_style`
     *   text|image, `correct`; the media question needs its audio, image or
     *   `video` (+ optional `poster`).
     * - ordering: `sentences: [{id, text, image?}]`, `order`.
     * - matching: `prompts: [{id, text, image?, audio?, audio_text?}]`,
     *   `targets: [{id, text, image?}]`, `pairs: {prompt: target}`.
     * - short_answer: `accepted: [string]`.
     * - fill_blank: `sentence` with `[[b1]]` tokens, `blanks: [{id,
     *   accepted: [string]}]`.
     * - speaking: `expected_text` (what to say, pronunciation check) or an
     *   open question (AI judged), `max_seconds`.
     * - writing: `scenario`, `request_text`, `information`, `min_words`,
     *   `criteria: [{key, label}]` (rubric), `model_answer`.
     *
     * @return list<self>
     */
    public static function builderTypes(): array
    {
        return [
            self::MultipleChoice,
            self::Ordering,
            self::Matching,
            self::ShortAnswer,
            self::AudioQuestion,
            self::ImageQuestion,
            self::VideoQuestion,
            self::Speaking,
            self::FillBlank,
            self::Writing,
        ];
    }

    /**
     * An older type the builders no longer offer for new activities; its
     * stored activities stay editable and answerable (DATA-11).
     */
    public function isLegacy(): bool
    {
        return ! in_array($this, self::builderTypes(), true);
    }

    /**
     * The name the admin builders give the type (the client's ten names);
     * an older type keeps its learner-facing label.
     */
    public function builderLabel(?string $locale = null): string
    {
        return match ($this) {
            self::MultipleChoice => __('Multiple Choice', [], $locale),
            self::Writing => __('Writing Activity', [], $locale),
            default => $this->label($locale),
        };
    }

    /**
     * The skill a test's result breakdown groups this type under.
     */
    public function defaultSkillLabel(): string
    {
        return match ($this) {
            self::ListenChoose, self::BestResponse, self::ListenMatch, self::AudioQuestion => 'Listening',
            self::LookListen, self::ImageQuestion => 'Visual',
            self::WatchRespond, self::VideoQuestion => 'Video',
            self::WordsSentences, self::FillBlank => 'Vocabulary',
            self::DialogueOrder, self::PictureOrder, self::Ordering => 'Ordering',
            self::Matching => 'Matching',
            self::ShortAnswer => 'Short Answer',
            self::MultipleChoice => 'Multiple Choice',
            self::Speaking => 'Speaking',
            self::Writing => 'Writing',
        };
    }

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
            self::ShortAnswer => __('Short Answer', [], $locale),
            self::FillBlank => __('Fill in the Blank', [], $locale),
            self::Matching => __('Matching', [], $locale),
            self::Ordering => __('Ordering', [], $locale),
            self::AudioQuestion => __('Audio Question', [], $locale),
            self::ImageQuestion => __('Image Question', [], $locale),
            self::VideoQuestion => __('Video Question', [], $locale),
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
            self::ShortAnswer => __('Read the question and type a short answer.', [], $locale),
            self::FillBlank => __('Type the missing word in each blank.', [], $locale),
            self::Matching => __('Match each word with its pair.', [], $locale),
            self::Ordering => __('Put the sentences in the correct order.', [], $locale),
            self::AudioQuestion => __('Listen, then choose the correct answer.', [], $locale),
            self::ImageQuestion => __('Look at the picture, then choose the correct answer.', [], $locale),
            self::VideoQuestion => __('Watch the video, then choose the correct answer.', [], $locale),
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
            self::ListenChoose, self::PictureOrder, self::MultipleChoice, self::AudioQuestion => 'brand',
            self::LookListen, self::DialogueOrder, self::Ordering, self::ImageQuestion => 'success',
            self::BestResponse, self::ShortAnswer => 'sunset',
            self::ListenMatch, self::Speaking, self::Matching => 'ai',
            self::WatchRespond, self::VideoQuestion => 'blossom',
            self::WordsSentences, self::FillBlank => 'gold',
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
            self::ShortAnswer => 'TextCursorInput',
            self::FillBlank => 'Keyboard',
            self::Matching => 'Link',
            self::Ordering => 'ListOrdered',
            self::AudioQuestion => 'Headphones',
            self::ImageQuestion => 'Image',
            self::VideoQuestion => 'Video',
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
            self::MultipleChoice,
            self::AudioQuestion,
            self::ImageQuestion,
            self::VideoQuestion => AnswerShape::Option,
            self::ListenMatch, self::Matching => AnswerShape::PairMap,
            self::DialogueOrder,
            self::PictureOrder,
            self::Ordering => AnswerShape::OrderedList,
            self::ShortAnswer => AnswerShape::Typed,
            self::FillBlank => AnswerShape::Blanks,
            self::Speaking => AnswerShape::Recording,
            self::Writing => AnswerShape::Text,
        };
    }
}
