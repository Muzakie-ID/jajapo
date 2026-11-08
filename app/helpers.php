<?php

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

if (! function_exists('initials')) {
    function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(fn ($w) => Str::upper(Str::substr($w, 0, 1)), array_slice($words, 0, 2));

        return implode('', $letters) ?: '?';
    }
}

if (! function_exists('rupiah')) {
    function rupiah(int|float $amount): string
    {
        return ($amount < 0 ? '-' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
    }
}

if (! function_exists('tanggal')) {
    /**
     * "Sab, 14 Nov" atau dengan jam: "Sab, 14 Nov, 10.00".
     */
    function tanggal(?CarbonInterface $date, bool $withTime = false, bool $withYear = false): string
    {
        if (! $date) {
            return '';
        }
        $date = $date->copy()->setTimezone(config('jajapo.timezone'));
        $days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $text = $days[$date->dayOfWeek].', '.$date->day.' '.$months[$date->month - 1];
        if ($withYear) {
            $text .= ' '.$date->year;
        }

        return $withTime ? $text.', '.$date->format('H.i') : $text;
    }
}

if (! function_exists('jam')) {
    function jam(?CarbonInterface $date): string
    {
        return $date ? $date->copy()->setTimezone(config('jajapo.timezone'))->format('H.i') : '';
    }
}
