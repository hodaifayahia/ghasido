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
            self::Situation => 'Situation',
            self::Vocabulary => 'Vocabulary',
            self::Expressions => 'Expressions',
            self::ListenRepeat => 'Listen',
            self::Dialogue => 'Dialogue',
            self::Video => 'Video',
            self::Practice => 'Practice',
            self::AiRoleplay => 'AI Role-play',
            self::Complete => 'Complete',
            self::Text => 'Text',
            self::Note => 'Note',
            self::Image => 'Image',
            self::Audio => 'Audio',
            self::EmailActivity => 'Writing',
            self::PhoneActivity => 'Speaking',
        };
    }

    /**
     * The page title without its step number (spec 0003 B.8).
     */
    public function heading(): string
    {
        return match ($this) {
            self::Situation => 'Situation (Intro)',
            self::Vocabulary => 'Vocabulary',
            self::Expressions => 'Useful Expressions',
            self::ListenRepeat => 'Listen & Repeat',
            self::Dialogue => 'Dialogue',
            self::Video => 'Video – Watch the Situation',
            self::Practice => 'Practice',
            self::AiRoleplay => 'AI Role-play',
            self::Complete => 'Lesson Completed!',
            self::Text => 'Text',
            self::Note => 'Note',
            self::Image => 'Image',
            self::Audio => 'Audio',
            self::EmailActivity => 'Email Activity',
            self::PhoneActivity => 'Phone Activity',
        };
    }

    /**
     * One line for the builder palette (BLD-02).
     */
    public function description(): string
    {
        return match ($this) {
            self::Situation => 'Introduce the situation, the objectives and a short quote.',
            self::Vocabulary => 'Key words with picture, audio and meaning on demand.',
            self::Expressions => 'Useful phrases with audio and meaning on demand.',
            self::ListenRepeat => 'Listen to a sentence at two speeds and repeat it.',
            self::Dialogue => 'A short conversation, line by line, with audio.',
            self::Video => 'Watch the situation happen in a short video.',
            self::Practice => 'A hub of practice activities.',
            self::AiRoleplay => 'Practise the situation with an AI guest.',
            self::Complete => 'The closing screen with the lesson summary.',
            self::Text => 'A short paragraph of text.',
            self::Note => 'A highlighted note.',
            self::Image => 'A single image with a caption.',
            self::Audio => 'A single audio clip with its text.',
            self::EmailActivity => 'Write a reply to a guest email.',
            self::PhoneActivity => 'Answer a guest on the phone.',
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
