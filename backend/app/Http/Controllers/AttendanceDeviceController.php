<?php

namespace App\Http\Controllers;

use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceDeviceController extends Controller
{
    public function index()
    {
        return response()->json(['devices' => DB::table('attendance_devices')->orderBy('name')->get(), 'mappings' => DB::table('attendance_device_enrollments')->join('employee_data', 'employee_data.id', '=', 'attendance_device_enrollments.employee_id')->select('attendance_device_enrollments.*', 'employee_data.name as employee_name')->orderBy('device_id')->orderBy('enroll_id')->get(), 'imports' => DB::table('attendance_imports')->leftJoin('attendance_devices', 'attendance_devices.id', '=', 'attendance_imports.device_id')->select('attendance_imports.*', 'attendance_devices.name as device_name')->orderByDesc('attendance_imports.id')->limit(30)->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role->canManageUsers(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:attendance_devices,name'], 'format' => ['required', Rule::in(['delimited', 'device_text'])], 'in_code' => ['required', 'string', 'max:20'], 'out_code' => ['required', 'string', 'max:20', 'different:in_code'], 'shared_enrollment' => ['required', 'boolean']]);
        $id = DB::transaction(function () use ($data) {
            $id = DB::table('attendance_devices')->insertGetId([...$data, 'created_at' => now(), 'updated_at' => now()]);
            app(WorkflowService::class)->audit('create', 'attendance_devices', $id, $data);

            return $id;
        });

        return response()->json(['data' => DB::table('attendance_devices')->find($id)], 201);
    }

    public function map(Request $request)
    {
        abort_unless($request->user()->role->canManageUsers(), 403);
        $data = $request->validate(['device_id' => ['required', 'integer', 'exists:attendance_devices,id'], 'enroll_id' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/'], 'employee_id' => ['required', 'integer', 'exists:employee_data,id']]);
        DB::transaction(function () use ($data) {
            $old = DB::table('attendance_device_enrollments')->where(['device_id' => $data['device_id'], 'enroll_id' => $data['enroll_id']])->first();
            if ($old && $old->employee_id !== (int) $data['employee_id']) {
                throw ValidationException::withMessages(['enroll_id' => ['This machine ID already belongs to another employee. Existing identity mappings cannot be silently reassigned.']]);
            }
            DB::table('attendance_device_enrollments')->updateOrInsert(['device_id' => $data['device_id'], 'enroll_id' => $data['enroll_id']], ['employee_id' => $data['employee_id']]);
            app(WorkflowService::class)->audit('map_enrollment', 'attendance_devices', $data['device_id'], $data);
        });

        return response()->json(['message' => 'Machine enrollment mapped to the employee.']);
    }
}
