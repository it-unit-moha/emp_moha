<?php

namespace App\Http\Controllers;

use App\Services\DivisionOrder;
use App\Services\WorkflowService;
use Carbon\CarbonImmutable as Date;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceWorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'], 'date' => ['nullable', 'date_format:Y-m-d'],
            'division_id' => ['nullable', 'integer', 'exists:division,id'],
            'employee_id' => ['nullable', 'integer', 'exists:employee_data,id'], 'search' => ['nullable', 'string', 'max:100'],
        ]);
        $month = $data['month'];
        if (! empty($data['date']) && substr($data['date'], 0, 7) !== $month) {
            throw ValidationException::withMessages(['date' => ['Choose a date within the selected month.']]);
        }
        $divisions = DB::table('division')->select('division.*')->selectSub(DB::table('employee_data')->selectRaw('COUNT(*)')->whereColumn('employee_data.division', 'division.id'), 'employees')->orderByRaw(DivisionOrder::sql())->orderBy('division')->get();
        $query = DB::table('employee_data')->leftJoin('division', 'division.id', '=', 'employee_data.division')->leftJoin('unit', 'unit.id', '=', 'employee_data.unit')->leftJoin('designation', 'designation.id', '=', 'employee_data.designation')->leftJoin('employee_profiles', 'employee_profiles.employee_id', '=', 'employee_data.id')
            ->select('employee_data.id', 'employee_data.name', 'employee_data.emp_no', 'employee_data.email', 'employee_data.contact_no', 'employee_data.is_active', 'employee_data.division as division_id', 'division.division as division_name', 'unit.unit_name', 'designation.designation as designation_name', 'employee_profiles.staff_category', 'employee_profiles.service_grade', 'employee_profiles.enroll_id');
        if (! empty($data['division_id'])) {
            $query->where('employee_data.division', $data['division_id']);
        }
        if (! empty($data['employee_id'])) {
            $query->where('employee_data.id', $data['employee_id']);
        }
        if ($search = $data['search'] ?? null) {
            $query->where(fn ($q) => $q->where('employee_data.name', 'like', "%$search%")->orWhere('emp_no', 'like', "%$search%")->orWhere('enroll_id', 'like', "%$search%"));
        }
        $staff = $query->orderByRaw(DivisionOrder::sql())->orderBy('division.division')->orderBy('employee_data.name')->get();
        $records = DB::table('attendance_days')->whereIn('employee_id', $staff->pluck('id'))->where('date', 'like', $month.'%')->orderBy('date')->get();
        $sources = DB::table('attendance_import_punches')->join('attendance_imports', 'attendance_imports.id', '=', 'attendance_import_punches.import_id')->leftJoin('attendance_devices', 'attendance_devices.id', '=', 'attendance_imports.device_id')->whereIn('attendance_import_punches.employee_id', $staff->pluck('id'))->where('punched_at', 'like', $month.'%')->select('attendance_import_punches.employee_id', 'punched_at', 'attendance_devices.name')->get()->groupBy(fn ($s) => $s->employee_id.'|'.substr($s->punched_at, 0, 10));
        $records->each(function ($record) use ($sources) {
            $record->machine_names = $sources->get($record->employee_id.'|'.$record->date, collect())->map(fn ($s) => $s->name ?? 'Standard file')->unique()->values()->all();
        });
        $shortLeaves = DB::table('leave_applications')->whereIn('employee_id', $staff->pluck('id'))->where('status', 'approved')->where('type', 'like', 'short_%')->where('start_date', 'like', $month.'%')->get();
        $staff = $staff->map(function ($person) use ($records, $data, $shortLeaves) {
            $days = $records->where('employee_id', $person->id);
            $approvedShort = $shortLeaves->where('employee_id', $person->id);
            if (! empty($data['date'])) {
                $days = $days->where('date', $data['date']);
                $approvedShort = $approvedShort->where('start_date', $data['date']);
            }
            $person->summary = ['recorded' => $days->count(), 'late' => $days->where('late_minutes', '>', 0)->count(), 'covered' => $days->where('evaluation', 'late_covered')->count(), 'short_leave' => $days->where('evaluation', 'short_leave')->count() + $approvedShort->count(), 'half_days' => $days->where('evaluation', 'half_day')->count(), 'review' => $days->whereIn('evaluation', ['review', 'policy_review', 'leave_review'])->count()];

            return $person;
        });
        $days = [];
        if (! empty($data['employee_id'])) {
            $from = Date::parse($month.'-01', 'Asia/Colombo');
            $holidays = DB::table('holidays')->where('date', 'like', $month.'%')->pluck('name', 'date');
            $leaves = DB::table('leave_applications')->where('employee_id', $data['employee_id'])->where('status', 'approved')->where('start_date', '<=', $from->endOfMonth()->toDateString())->where('end_date', '>=', $from->toDateString())->get();
            for ($day = $from; $day <= $from->endOfMonth(); $day = $day->addDay()) {
                $date = $day->toDateString();
                if (! empty($data['date']) && $data['date'] !== $date) {
                    continue;
                }
                $record = $records->firstWhere('date', $date);
                if ($record) {
                    $days[] = $record;

                    continue;
                }
                $leave = $leaves->first(fn ($a) => $a->start_date <= $date && $a->end_date >= $date);
                $status = $leave ? 'approved_leave' : ($holidays->has($date) ? 'public_holiday' : ($day->isWeekend() ? 'weekend' : ($date > now('Asia/Colombo')->toDateString() ? 'not_due' : 'no_record')));
                $days[] = ['date' => $date, 'actual_in' => null, 'actual_out' => null, 'late_minutes' => 0, 'required_out' => null, 'evaluation' => $status, 'remarks' => $leave ? 'Approved '.str_replace('_', ' ', $leave->type).' leave.' : ($holidays->get($date) ?? ($status === 'no_record' ? 'No attendance uploaded — absence has not been inferred.' : str_replace('_', ' ', ucfirst($status)))), 'half_day' => 0];
            }
        }

        return response()->json(['divisions' => $divisions, 'employees' => $staff, 'days' => $days, 'month' => $month, 'imports' => DB::table('attendance_imports')->orderByDesc('id')->limit(10)->get()]);
    }

    public function recalculate(Request $request, WorkflowService $workflow)
    {
        abort_unless($request->user()->role->canWrite(), 403);
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $from = Date::parse($data['month'].'-01');
        $workflow->refreshDates($from->toDateString(), $from->endOfMonth()->toDateString());
        $workflow->audit('recalculate_month', 'attendance_days', null, $data);

        return response()->json(['message' => 'Monthly attendance recalculated in date order.']);
    }
}
