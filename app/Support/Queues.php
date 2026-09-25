<?php

namespace App\Support;

/**
 * Queue names (PERF-04). Each has its own worker in compose.yaml, so a
 * backlog of slow media jobs (hundreds of audio clips, images that take two
 * minutes) can never hold up a reply a learner or admin is waiting for.
 */
final class Queues
{
    /** A person is watching a spinner: role-play turns, evaluations, drafts, provider checks. */
    public const INTERACTIVE = 'interactive';

    /** Longer generation runs and mail: whole lessons, test questions, reminders. */
    public const DEFAULT = 'default';

    /** Bulk synthesis: lesson/test audio clips and generated images. */
    public const MEDIA = 'media';
}
