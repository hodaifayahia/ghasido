<?php

namespace App\Enums;

/**
 * The kinds of block a lesson is built from (LESSON-01, LESSON-02, CMS-02,
 * BLD-02, spec 0003 B.8).
 *
 * One block is one employee step, so the words here are what the step tracker
 * and the step heading print. The learner renderer maps each case to a Vue
 * component (`learning/steps/*Step.vue`); the admin builder lists every case
 * in its palette. Adding a case means adding a component, nothing else.
 */
enum BlockType: string
{
    case Situation = 'situation';
    case Vocabulary = 'vocabulary';
    case Expressions = 'expressions';
    case ListenRepeat = 'listen_repeat';
    case Dialogue = 'dialogue';
    case Video = 'video';
    case Practice = 'practice';
    case AiRoleplay = 'ai_roleplay';
    case Complete = 'complete';
    case Text = 'text';
    case Note = 'note';
    case Image = 'image';
    case Audio = 'audio';
    case EmailActivity = 'email_activity';
    case PhoneActivity = 'phone_activity';

    /**
     * The one word under the circle in the step tracker (spec 0003 B.8).
     */
    public function stepLabel(): string
    {
        return match ($this) {
            self::Situation => __('Situation'),
            self::Vocabulary => __('Vocabulary'),
            self::Expressions => __('Expressions'),
            self::ListenRepeat => __('Listen'),
            self::Dialogue => __('Dialogue'),
            self::Video => __('Video'),
            self::Practice => __('Practice'),
            self::AiRoleplay => __('AI Role-play'),
            self::Complete => __('Complete'),
            self::Text => __('Text'),
            self::Note => __('Note'),
            self::Image => __('Image'),
            self::Audio => __('Audio'),
            self::EmailActivity => __('Writing'),
            self::PhoneActivity => __('Speaking'),
        };
    }

    /**
     * The page title without its step number (spec 0003 B.8).
     */
    public function heading(): string
    {
        return match ($this) {
            self::Situation => __('Situation (Intro)'),
            self::Vocabulary => __('Vocabulary'),
            self::Expressions => __('Useful Expressions'),
            self::ListenRepeat => __('Listen & Repeat'),
            self::Dialogue => __('Dialogue'),
            self::Video => __('Video – Watch the Situation'),
            self::Practice => __('Practice'),
            self::AiRoleplay => __('AI Role-play'),
            self::Complete => __('Lesson Completed!'),
            self::Text => __('Text'),
            self::Note => __('Note'),
            self::Image => __('Image'),
            self::Audio => __('Audio'),
            self::EmailActivity => __('Email Activity'),
            self::PhoneActivity => __('Phone Activity'),
        };
    }

    /**
     * One line for the builder palette (BLD-02).
     */
    public function description(): string
    {
        return match ($this) {
            self::Situation => __('Introduce the situation, the objectives and a short quote.'),
            self::Vocabulary => __('Key words with picture, audio and meaning on demand.'),
            self::Expressions => __('Useful phrases with audio and meaning on demand.'),
            self::ListenRepeat => __('Listen to a sentence at two speeds and repeat it.'),
            self::Dialogue => __('A short conversation, line by line, with audio.'),
            self::Video => __('Watch the situation happen in a short video.'),
            self::Practice => __('A hub of practice activities.'),
            self::AiRoleplay => __('Practise the situation with an AI guest.'),
            self::Complete => __('The closing screen with the lesson summary.'),
            self::Text => __('A short paragraph of text.'),
            self::Note => __('A highlighted note.'),
            self::Image => __('A single image with a caption.'),
            self::Audio => __('A single audio clip with its text.'),
            self::EmailActivity => __('Write a reply to a guest email.'),
            self::PhoneActivity => __('Answer a guest on the phone.'),
        };
    }

    /**
     * The icon chip tone in the builder palette (colour usage law, AGENTS.md
     * §3). Practice and role-play take the tones the mockups paint them in.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Situation, self::Text, self::Note, self::Image => 'brand',
            self::Vocabulary, self::Expressions => 'aqua',
            self::ListenRepeat, self::Audio, self::Dialogue => 'success',
            self::Video => 'warning',
            self::Practice, self::EmailActivity, self::PhoneActivity => 'gold',
            self::AiRoleplay => 'ai',
            self::Complete => 'success',
        };
    }

    /**
     * The `@lucide/vue` export the palette draws (AGENTS.md §3 iconography).
     */
    public function icon(): string
    {
        return match ($this) {
            self::Situation => 'MessageSquareText',
            self::Vocabulary => 'BookA',
            self::Expressions => 'Quote',
            self::ListenRepeat => 'Volume2',
            self::Dialogue => 'MessagesSquare',
            self::Video => 'Video',
            self::Practice => 'ListChecks',
            self::AiRoleplay => 'Bot',
            self::Complete => 'Award',
            self::Text => 'Type',
            self::Note => 'StickyNote',
            self::Image => 'Image',
            self::Audio => 'AudioLines',
            self::EmailActivity => 'Mail',
            self::PhoneActivity => 'Phone',
        };
    }

    /**
     * Does this block own answerable activities through activity_placements?
     */
    public function holdsActivities(): bool
    {
        return in_array($this, [self::Practice, self::EmailActivity, self::PhoneActivity], true);
    }

    /**
     * Does this block draw its items from block_lexicon_item?
     */
    public function holdsLexicon(): bool
    {
        return in_array($this, [self::Vocabulary, self::Expressions], true);
    }
}
