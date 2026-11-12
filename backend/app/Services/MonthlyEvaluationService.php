<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlyEvaluationService
{
    // Always replay the complete month in date order, including when earlier punches arrive later.
    public function evaluate(int $employee, string $month): void
    {
        $category = DB::table('employee_profiles')->where('employee_id', $employee)->value('staff_category');
        $schedule = config('galms.schedules.'.$category);
        $days = DB::table('attendance_days')->where('employee_id', $employee)->where('date', 'like', $month.'%')->orderBy('date')->get();
        $end = CarbonImmutable::parse($month.'-01')->endOfMonth()->toDateString();
        $approved = DB::table('leave_applications')->where('employee_id', $employee)->where('status', 'approved')->where('start_date', '<=', $end)->where('end_date', '>=', $month.'-01')->get();
        $reservedTotal = $approved->filter(fn ($a) => str_starts_with($a->type, 'short_'))->count();
        $lateCount = 0;
        $shortCount = 0;
        foreach ($days as $day) {
            $approvedUsed = $approved->filter(fn ($a) => str_starts_with($a->type, 'short_') && $a->start_date <= $day->date)->count();
            $early = $schedule && $day->actual_out ? max(0, (int) ceil((strtotime($day->date.' '.$schedule['end']) - strtotime($day->actual_out)) / 60)) : 0;
            $evaluation = $day->status;
            $remarks = match ($day->status) {
                'on_time' => 'On time — standard working hours completed.',
                'non_working_day' => 'Non-working day — weekend or configured public holiday.',
                'leave_review' => 'Approved leave overlaps this date — attendance requires review.',
                default => 'Review required — missing, invalid or unpaired punches; no automatic conversion.',
            };
            $half = 0;
            $lateSequence = 0;
            $shortSequence = 0;
            $leave = $approved->filter(fn ($a) => $a->start_date <= $day->date && $a->end_date >= $day->date);
            if ($leave->contains(fn ($a) => ! str_starts_with($a->type, 'short_'))) {
                $evaluation = 'leave_review';
                $remarks = 'Approved leave overlaps this date — attendance requires review; no automatic conversion.';
            } elseif ($schedule && in_array($day->status, ['on_time', 'late_covered', 'late_uncovered', 'early_departure', 'leave_review'])) {
                $reserved = $leave->filter(fn ($a) => str_starts_with($a->type, 'short_'))->count();
                if ($day->late_minutes > 0) {
                    $lateSequence = ++$lateCount;
                }
                $covered = $day->late_minutes > 0 && $day->actual_out >= $day->required_out;
                $convert = $day->late_minutes > 0 && (! $covered || $lateCount > 2);
                if ($reserved) {
                    $shortSequence = $shortCount + $approvedUsed;
                    $evaluation = 'approved_short_leave';
                    $remarks = 'SL — Short Leave approved; included in the monthly allowance. Attendance outside the approved window requires review.';
                } elseif ($convert || $early > 0) {
                    $shortCount++;
                    $shortSequence = $shortCount + $approvedUsed;
                    // The image specifies Other Staff windows only. PL conversions require an explicit policy decision.
                    $eligible = $category === 'other' && (! $day->late_minutes || substr($day->actual_in, 11, 5) <= '10:00') && (! $early || substr($day->actual_out, 11, 5) >= '14:45');
                    if ($category === 'pl') {
                        $evaluation = 'policy_review';
                        $remarks = 'LC — Late Come / early departure: short-leave conversion requires review because PL Staff short-leave windows were not supplied.';
                        $shortCount--;
                        $shortSequence = 0;
                    } elseif ($eligible && $shortCount + $reservedTotal <= 2) {
                        $evaluation = 'short_leave';
                        $remarks = 'SL — Short Leave '.$shortSequence.' of 2: '.($day->late_minutes ? ($covered ? 'LC — Late Come exceeds the two-late monthly allowance despite covering.' : 'LC — Late Come was not covered by the required OUT time.') : 'Early departure within the afternoon short-leave window.');
                    } else {
                        $evaluation = 'half_day';
                        $half = 0.5;
                        $remarks = 'HD — Half Day: '.($eligible ? 'the two monthly short leaves have been used.' : 'arrival/departure falls outside the Other Staff short-leave windows (10:00 AM / 2:45 PM).');
                        if (! $eligible) {
                            $shortCount--;
                            $shortSequence = 0;
                        }
                    }
                } elseif ($covered) {
                    $evaluation = 'late_covered';
                    $remarks = 'LC — Late Come '.$lateCount.' of 2; Late Covered by working until the required OUT time on the same day.';
                }
            }
            DB::table('attendance_days')->where('id', $day->id)->update(['evaluation' => $evaluation, 'remarks' => $remarks, 'early_minutes' => $early, 'late_sequence' => $lateSequence, 'short_sequence' => $shortSequence, 'half_day' => $half]);
        }
    }
}
