<?php

return [
    'schedules' => ['pl' => ['start' => '08:00', 'end' => '16:45'], 'other' => ['start' => '08:30', 'end' => '16:15']],
    'leave' => ['casual_days' => 21, 'vacation_days' => [24, 28], 'maternity_working_days' => 84, 'paternity_days' => 3, 'short_leave_minutes' => 90, 'short_leave_monthly_limit' => 2, 'late_days_per_half_day' => 3, 'automatic_deductions_enabled' => env('GALMS_AUTOMATIC_DEDUCTIONS', false)],
    'attendance' => ['accepted_extensions' => ['csv', 'txt', 'dat'], 'max_upload_kb' => 10240, 'parser_verified' => false],
];
