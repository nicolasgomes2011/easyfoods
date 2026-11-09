<?php

namespace App\Support;

use App\Models\DiningTable;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * QR codes printed on the tables. Each code encodes the table's public link
 * (/mesa/{uuid}); scanning it opens the menu already bound to that table.
 *
 * SVG comes from simple-qrcode. PNG is drawn with GD from the same Bacon
 * matrix, because simple-qrcode's PNG backend needs the imagick extension.
 */
class TableQrCode
{
    /** Quiet zone around the code, in modules (the QR spec asks for 4). */
    public const MARGIN = 4;

    /** Level M survives ~15% damage: enough for a printed, wiped-down card. */
    private const ERROR_CORRECTION = 'M';

    public function url(DiningTable $table): string
    {
        return url('/mesa/'.$table->uuid);
    }

    /** Standalone SVG document (with the XML prolog), for download. */
    public function svg(string $url, int $size = 320): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(self::MARGIN)
            ->errorCorrection(self::ERROR_CORRECTION)
            ->generate($url);
    }

    /** Same SVG without the XML prolog, to embed in an HTML page. */
    public function inlineSvg(string $url, int $size = 320): string
    {
        return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $this->svg($url, $size));
    }

    /** PNG of about $size pixels per side (rounded down to whole modules). */
    public function png(string $url, int $size = 1024): string
    {
        $matrix  = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrix->getWidth() + 2 * self::MARGIN;
        $scale   = max(1, intdiv($size, $modules));
        $pixels  = $modules * $scale;

        $image = imagecreate($pixels, $pixels);
        imagecolorallocate($image, 255, 255, 255); // first color = background
        $black = imagecolorallocate($image, 0, 0, 0);

        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                if ($matrix->get($x, $y) !== 1) {
                    continue;
                }

                $left = ($x + self::MARGIN) * $scale;
                $top  = ($y + self::MARGIN) * $scale;
                imagefilledrectangle($image, $left, $top, $left + $scale - 1, $top + $scale - 1, $black);
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
