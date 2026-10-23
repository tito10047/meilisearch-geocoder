<?php

namespace Tito10047\GeoBundle\Command\Output;

/**
 * Formats the counts printed by the commands.
 *
 * @internal
 */
final class Numbers
{
    private function __construct()
    {
    }

    /**
     * "4 420 000": thin groups readable in any locale.
     */
    public static function format(int $number): string
    {
        return number_format($number, 0, '.', ' ');
    }
}
