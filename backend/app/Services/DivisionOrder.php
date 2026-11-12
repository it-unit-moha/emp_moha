<?php

namespace App\Services;

class DivisionOrder
{
    public const NAMES = [
        'Administration Division', 'Home Affairs Division', 'Finance Division',
        'Planning Division', 'Development Division', 'Engineering Division',
        'Regional Administration Reforms Division', 'Internal Audit Division', 'Investigation Division',
    ];

    public static function rank(?string $name): int
    {
        $name = strtolower(trim(preg_replace('/\s+/', ' ', $name ?? '')));
        foreach (self::NAMES as $index => $division) {
            if ($name === strtolower($division) || $name === strtolower(str_replace(' Division', '', $division))) {
                return $index;
            }
        }

        return 99;
    }

    public static function sql(string $column = 'division.division'): string
    {
        $parts = [];
        foreach (self::NAMES as $index => $name) {
            $parts[] = "WHEN LOWER(TRIM($column)) IN ('".strtolower($name)."', '".strtolower(str_replace(' Division', '', $name))."') THEN $index";
        }

        return 'CASE '.implode(' ', $parts).' ELSE 99 END';
    }
}
