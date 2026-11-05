<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Employee;
use App\Models\Unit;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $counts = Employee::query()->selectRaw('division, COUNT(*) as aggregate')->groupBy('division')->pluck('aggregate', 'division');

        return response()->json(['data' => [
            'total_employees' => Employee::count(), 'total_divisions' => Division::count(), 'total_units' => Unit::count(),
            'present' => null, 'absent' => null, 'late' => null, 'on_leave' => null, 'recent_attendance' => [],
            'employees_by_division' => Division::query()->select(['id', 'division'])->get()->map(fn ($division) => ['name' => $division->division ?? 'Unnamed division', 'employees' => (int) ($counts[$division->id] ?? 0)]),
            'attendance_status' => 'Attendance source has not been configured.',
        ]]);
    }
}
