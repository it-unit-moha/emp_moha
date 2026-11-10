<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $counts = Employee::query()->selectRaw('division, COUNT(*) as aggregate')->groupBy('division')->pluck('aggregate', 'division');

        $date = now('Asia/Colombo')->toDateString();
        $hasImports = DB::table('attendance_imports')->exists();
        $daily = DB::table('attendance_days')->where('date', $date);
        $leave = DB::table('leave_applications')->where('status', 'approved')->where('type', 'not like', 'short_%')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->distinct()->count('employee_id');
        $recent = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->select('attendance_days.*', 'employee_data.name')->orderByDesc('date')->orderByDesc('attendance_days.id')->limit(10)->get();

        return response()->json(['data' => [
            'total_employees' => Employee::count(), 'total_divisions' => Division::count(), 'total_units' => Unit::count(),
            'present' => $hasImports ? (clone $daily)->whereNotNull('actual_in')->count() : null, 'absent' => null, 'late' => $hasImports ? (clone $daily)->where('late_minutes', '>', 0)->where('status', '!=', 'non_working_day')->count() : null, 'on_leave' => $hasImports || $leave ? $leave : null, 'recent_attendance' => $recent,
            'employees_by_division' => Division::query()->select(['id', 'division'])->get()->map(fn ($division) => ['name' => $division->division ?? 'Unnamed division', 'employees' => (int) ($counts[$division->id] ?? 0)]),
            'attendance_status' => $hasImports ? 'Recorded punches are shown. Absence counts require a verified full-day roster; review missing punches and leave exceptions.' : 'Upload the first verified attendance file from Attendance & upload.',
        ]]);
    }
}
