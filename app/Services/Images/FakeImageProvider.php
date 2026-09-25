<?php

namespace App\Services\Images;

use App\Contracts\AiUsageInfo;
use App\Contracts\GeneratedImage;
use App\Contracts\ImageProvider;
use GdImage;

/**
 * The image provider tests, CI and local runs use: no key, no network
 * (API-04). It draws a small valid PNG with GD, tinted from the prompt so two
 * prompts give two different pictures; without GD it returns a 1×1 PNG.
 */
final class FakeImageProvider implements ImageProvider
{
    public const string PROVIDER = 'fake';

    /** A valid 1×1 PNG, for a PHP build without GD. */
    private const string ONE_PIXEL_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function generate(string $prompt, string $size = self::SIZE_LANDSCAPE): GeneratedImage
    {
        [$width, $height] = $size === self::SIZE_SQUARE ? [64, 64] : [96, 54];

        $binary = $this->draw($prompt, $width, $height);

        if ($binary === null) {
            return new GeneratedImage(
                binary: (string) base64_decode(self::ONE_PIXEL_PNG, true),
                mime: 'image/png',
                extension: 'png',
                width: 1,
                height: 1,
                usage: AiUsageInfo::none(self::PROVIDER),
            );
        }

        return new GeneratedImage(
            binary: $binary,
            mime: 'image/png',
            extension: 'png',
            width: $width,
            height: $height,
            usage: AiUsageInfo::none(self::PROVIDER),
        );
    }

    /**
     * @param  int<1, max>  $width
     * @param  int<1, max>  $height
     */
    private function draw(string $prompt, int $width, int $height): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $image = imagecreatetruecolor($width, $height);

        if (! $image instanceof GdImage) {
            return null;
        }

        $hash = md5($prompt, true);
        $red = 80 + ord($hash[0]) % 150;
        $green = 80 + ord($hash[1]) % 150;
        $blue = 150 + ord($hash[2]) % 100;

        $fill = imagecolorallocate($image, $red, $green, $blue);
        $band = imagecolorallocate($image, 255, 255, 255);

        if ($fill === false || $band === false) {
            return null;
        }

        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $fill);
        imagefilledrectangle($image, 0, (int) ($height * 0.7), $width - 1, $height - 1, $band);

        ob_start();
        $written = imagepng($image);
        $binary = (string) ob_get_clean();

        return $written && $binary !== '' ? $binary : null;
    }
}
