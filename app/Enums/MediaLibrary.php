<?php

namespace App\Enums;

/**
 * Which shelf of the image library a media asset sits on (MED-02, MED-03,
 * spec 0003 B.3).
 *
 * The first three are the tabs the CMS image picker shows. `generated` is
 * TTS output, `recordings` is learner audio (private, PRIV-04), `seed` is the
 * mockup crops from spec 0003 Part F that the CMS may replace.
 */
enum MediaLibrary: string
{
    case MyImages = 'my_images';
    case GuesviaLibrary = 'guesvia_library';
    case IconsStickers = 'icons_stickers';
    case Generated = 'generated';
    case Recordings = 'recordings';
    case Seed = 'seed';

    public function label(): string
    {
        return match ($this) {
            self::MyImages => __('My Images'),
            self::GuesviaLibrary => __('GHASIDO Library'),
            self::IconsStickers => __('Icons & Stickers'),
            self::Generated => __('Generated audio'),
            self::Recordings => __('Recordings'),
            self::Seed => __('Seed content'),
        };
    }

    /**
     * Is this shelf offered in the CMS image picker?
     */
    public function isPickable(): bool
    {
        return in_array($this, [self::MyImages, self::GuesviaLibrary, self::IconsStickers, self::Seed], true);
    }
}
