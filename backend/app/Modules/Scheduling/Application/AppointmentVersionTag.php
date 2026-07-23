<?php

declare(strict_types=1);

namespace App\Modules\Scheduling\Application;

/**
 * Format and parse the strong HTTP validator for an appointment representation.
 */
final class AppointmentVersionTag
{
    private const MAX_VERSION = 4_294_967_295;

    /**
     * Format an appointment version as a strong HTTP entity tag.
     */
    public static function format(int $version): string
    {
        return '"v'.$version.'"';
    }

    /**
     * Parse one canonical strong entity tag, returning null when it is invalid.
     */
    public static function parse(string $tag): ?int
    {
        if (preg_match('/\A"v([1-9][0-9]{0,9})"\z/D', $tag, $matches) !== 1) {
            return null;
        }

        $version = (int) $matches[1];

        return $version <= self::MAX_VERSION ? $version : null;
    }
}
