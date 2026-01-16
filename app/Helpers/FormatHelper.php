<?php

namespace App\Helpers;

class FormatHelper
{
    public static function formatBytes(int $bytes): string
    {
        $units = ['bytes', 'KB', 'MB', 'GB', 'TB'];
        $factor = 1024;
        $i = 0;

        while ($bytes >= $factor && $i < count($units) - 1) {
            $bytes /= $factor;
            $i++;
        }

        return $i === 0
            ? $bytes . ' ' . $units[$i]
            : number_format($bytes, 1) . ' ' . $units[$i];
    }
}
