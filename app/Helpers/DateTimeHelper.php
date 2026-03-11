<?php

namespace App\Helpers;

class DateTimeHelper
{
    /**
     * Formats the elapsed time since a given start time.
     *
     * @param float $startTime The start time obtained from microtime(true).
     * @return string The formatted elapsed time string.
     */
    public static function formatElapsedTime(float $startTime): string
    {
        $endTime = microtime(true);
        $elapsedSeconds = $endTime - $startTime;

        $hours = floor($elapsedSeconds / 3600);
        $minutes = floor(($elapsedSeconds % 3600) / 60);
        $seconds = round($elapsedSeconds % 60);

        $parts = [];

        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }

        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }

        if ($seconds > 0 || empty($parts)) {
            $parts[] = "{$seconds}s";
        }

        return implode(' ', $parts);
    }
}
