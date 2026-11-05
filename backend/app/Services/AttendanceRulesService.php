<?php

namespace App\Services;

use InvalidArgumentException;

class AttendanceRulesService
{
    public function schedule(string $category): array
    {
        $schedules = config('galms.schedules');
        if (! isset($schedules[$category])) {
            throw new InvalidArgumentException('Employee category must be explicitly assigned before calculation.');
        }

return $schedules[$category];
    }
}
