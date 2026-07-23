<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use SensitiveParameter;

/**
 * Render a provisioning URI as a script-free, high-contrast SVG QR code.
 */
final class TotpQrCodeRenderer
{
    /**
     * Render the sensitive provisioning URI without persisting it.
     */
    public function render(#[SensitiveParameter] string $provisioningUri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                256,
                4,
                null,
                null,
                Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(10, 50, 67),
                ),
            ),
            new SvgImageBackEnd,
        );
        $svg = (new Writer($renderer))->writeString($provisioningUri);
        $declarationEnd = strpos($svg, "\n");

        return trim($declarationEnd === false ? $svg : substr($svg, $declarationEnd + 1));
    }
}
