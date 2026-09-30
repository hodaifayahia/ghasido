<?php

namespace App\Support;

/**
 * Free text written by a person (a reminder body, a message to a customer)
 * turned into safe email HTML: escaped, line breaks kept, links clickable.
 * Outlook ignores `white-space: pre-line`, so breaks become <br>.
 */
final class MailText
{
    public static function paragraph(string $text): string
    {
        $escaped = e(trim($text));

        $linked = (string) preg_replace_callback(
            '~https?://[^\s<]+[^\s<.,;:!?)\]]~u',
            fn (array $match): string => '<a href="'.$match[0].'" target="_blank" style="color: #0b5cff; text-decoration: underline; word-break: break-all;">'.$match[0].'</a>',
            $escaped,
        );

        return nl2br($linked);
    }
}
