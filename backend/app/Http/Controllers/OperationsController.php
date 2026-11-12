<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\AttendanceImportService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsController extends Controller
{
    public function __construct(private WorkflowService $workflow) {}

    private function writer(Request $request): void
    {
        abort_unless($request->user()->role->canWrite(), 403);
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role->canManageUsers(), 403);
    }

    public function overview(Request $request)
    {
        $validated = $request->validate(['year' => ['sometimes', 'integer', 'between:2000,2100']]);
        $year = (int) ($validated['year'] ?? now('Asia/Colombo')->year);

        return response()->json(['data' => [
            'employees' => Employee::with('profile')->orderBy('name')->get()->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'division_id' => $e->division, 'active' => $e->is_active, 'category' => $e->profile?->staff_category, 'enroll_id' => $e->profile?->enroll_id]),
            'unmapped' => Employee::where('is_active', 1)->whereDoesntHave('profile', fn ($q) => $q->whereNotNull('staff_category')->whereNotNull('enroll_id')->whereNotNull('service_grade'))->count(),
            'balances' => $this->workflow->accounts($year),
            'ledger' => DB::table('leave_ledger')->join('leave_accounts', 'leave_accounts.id', '=', 'leave_ledger.account_id')->select('leave_ledger.*', 'leave_accounts.employee_id', 'leave_accounts.type')->where('year', $year)->orderByDesc('leave_ledger.id')->limit(200)->get(),
            'holidays' => DB::table('holidays')->whereYear('date', $year)->orderBy('date')->get(),
            'schedules' => config('galms.schedules'), 'year' => $year,
            'deductions_enabled' => $this->workflow->deductionsEnabled(),
        ]]);
    }

    public function initialize(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['year' => ['required', 'integer', 'between:2000,2100']]);
        DB::transaction(function () use ($data) {
            foreach (Employee::where('is_active', 1)->pluck('id') as $id) {
                $this->workflow->initialize($id, $data['year']);
            } $this->workflow->audit('initialize_year', 'leave_accounts', null, $data);
        });

        return response()->json(['message' => 'Missing annual accounts initialized. Existing balances were preserved.']);
    }

    public function attendance(Request $request)
    {
        $data = $request->validate(['month' => ['sometimes', 'date_format:Y-m'], 'employee_id' => ['nullable', 'integer', 'exists:employee_data,id']]);
        $query = DB::table('attendance_days')->join('employee_data', 'employee_data.id', '=', 'attendance_days.employee_id')->select('attendance_days.*', 'employee_data.name')->where('date', 'like', ($data['month'] ?? now('Asia/Colombo')->format('Y-m')).'%');
        if (! empty($data['employee_id'])) {
            $query->where('employee_id', $data['employee_id']);
        }

        return response()->json(['data' => $query->orderByDesc('date')->orderBy('name')->get(), 'imports' => DB::table('attendance_imports')->orderByDesc('id')->limit(30)->get(), 'meta' => ['implemented' => true, 'schedules' => config('galms.schedules')]]);
    }

    public function import(Request $request)
    {
        $this->writer($request);
        $request->validate([
            'file' => ['required_without:files', 'file', 'max:10240', 'extensions:csv,txt,dat'],
            'device_id' => ['nullable', 'integer', 'exists:attendance_devices,id'],
            'files' => ['required_without:file', 'array', 'min:1', 'max:16'],
            'files.*' => ['file', 'max:10240', 'extensions:csv,txt,dat'],
            'device_ids' => ['nullable', 'array', 'max:16'],
            'device_ids.*' => ['nullable', 'integer', 'exists:attendance_devices,id'],
        ]);
        if ($request->hasFile('file') && $request->hasFile('files')) {
            throw ValidationException::withMessages(['files' => ['Use either a single file or a multi-file batch, not both.']]);
        }
        $files = [];
        foreach ($request->file('files', []) as $index => $file) {
            $files[] = ['path' => $file->getRealPath(), 'filename' => $file->getClientOriginalName(), 'device_id' => $request->input("device_ids.$index")];
        }
        if ($file = $request->file('file')) {
            $files[] = ['path' => $file->getRealPath(), 'filename' => $file->getClientOriginalName(), 'device_id' => $request->input('device_id')];
        }
        $result = app(AttendanceImportService::class)->importMany($files);

        return response()->json(['data' => $request->hasFile('file') ? $result['files'][0] : $result]);
    }

    public function template()
    {
        return response("enroll_id,timestamp,state\n", 200, ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="attendance-template.csv"']);
    }

    public function holiday(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'unique:holidays,date'], 'name' => ['required', 'string', 'max:255'], 'kind' => ['required', Rule::in(['public', 'mercantile'])]]);
        if (DB::table('leave_applications')->where('status', 'approved')->where('start_date', '<=', $data['date'])->where('end_date', '>=', $data['date'])->exists()) {
            throw ValidationException::withMessages(['date' => ['Cancel the affected leave approval before changing this calendar date, then resubmit it under the corrected calendar.']]);
        }
        DB::transaction(function () use ($data) {
            $id = DB::table('holidays')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            $this->workflow->refreshDates($data['date'], $data['date']);
            $this->workflow->audit('create', 'holidays', $id, $data);
        });

        return response()->json(['message' => 'Holiday added; attendance recalculated.'], 201);
    }

    public function deleteHoliday(Request $request, int $id)
    {
        $this->admin($request);
        DB::transaction(function () use ($id) {
            $holiday = DB::table('holidays')->find($id);
            abort_unless($holiday, 404);
            if (DB::table('leave_applications')->where('status', 'approved')->where('start_date', '<=', $holiday->date)->where('end_date', '>=', $holiday->date)->exists()) {
                throw ValidationException::withMessages(['date' => ['Cancel the affected leave approval before changing this calendar date.']]);
            }
            DB::table('holidays')->delete($id);
            $this->workflow->refreshDates($holiday->date, $holiday->date);
            $this->workflow->audit('delete', 'holidays', $id, ['date' => $holiday->date]);
        });

        return response()->json(['message' => 'Holiday removed; attendance recalculated.']);
    }

    public function leaves(Request $request)
    {
        $data = $request->validate(['year' => ['sometimes', 'integer', 'between:2000,2100']]);

        return response()->json(['data' => DB::table('leave_applications')->join('employee_data', 'employee_data.id', '=', 'leave_applications.employee_id')->select('leave_applications.*', 'employee_data.name')->whereYear('start_date', $data['year'] ?? now('Asia/Colombo')->year)->orderByDesc('id')->get(), 'meta' => ['implemented' => true, 'rules' => config('galms.leave')]]);
    }

    public function requestLeave(Request $request)
    {
        $this->writer($request);
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employee_data,id'], 'type' => ['required', Rule::in(['casual', 'vacation', 'maternity', 'paternity', 'lapsed', 'short_morning', 'short_afternoon'])], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'], 'scope' => ['required', Rule::in(['domestic', 'foreign'])], 'minutes' => ['nullable', 'integer', 'between:1,90'], 'half_day' => ['sometimes', 'boolean'], 'reason' => ['required', 'string', 'max:1000']]);
        abort_unless(Employee::find($data['employee_id'])->is_active === 1, 422, 'Archived employees cannot request leave.');

        return response()->json(['data' => $this->workflow->requestLeave($data)], 201);
    }

    public function review(Request $request, int $id)
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected', 'cancelled'])]]);
        $this->workflow->reviewLeave($id, $data['status']);

        return response()->json(['message' => 'Leave '.$data['status'].'.']);
    }

    public function adjust(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employee_data,id'], 'year' => ['required', 'integer', 'between:2000,2100'], 'type' => ['required', Rule::in(['casual', 'vacation', 'maternity', 'paternity', 'lapsed'])], 'amount' => ['required', 'numeric', 'between:-366,366', 'not_in:0'], 'reason' => ['required', 'string', 'max:255']]);
        DB::transaction(function () use ($data) {
            $this->workflow->initialize($data['employee_id'], $data['year']);
            $account = DB::table('leave_accounts')->where(['employee_id' => $data['employee_id'], 'year' => $data['year'], 'type' => $data['type']])->lockForUpdate()->first();
            $id = DB::table('leave_ledger')->insertGetId(['account_id' => $account->id, 'amount' => $data['amount'], 'reference' => 'adjustment:'.Str::uuid(), 'description' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $this->workflow->audit('adjust_balance', 'leave_ledger', $id, $data);
        });

        return response()->json(['message' => 'Audited balance adjustment recorded.']);
    }

    public function penaltySettings(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'confirm_rules' => ['required', 'accepted']]);
        DB::transaction(function () use ($data) {
            DB::table('system_settings')->updateOrInsert(['key' => 'automatic_deductions'], ['value' => $data['enabled'] ? '1' : '0']);
            $this->workflow->refreshDates('2000-01-01', '2100-12-31');
            $this->workflow->audit('configure_penalties', 'system_settings', null, ['enabled' => (bool) $data['enabled']]);
        });

        return response()->json(['message' => 'Penalty policy saved and affected ledger entries reconciled.']);
    }

    public function audit(Request $request)
    {
        $this->admin($request);

        return response()->json(['data' => DB::table('audit_logs')->orderByDesc('id')->limit(200)->get()]);
    }
}
