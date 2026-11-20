<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function download(Request $request, ReportService $reports)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['attendance', 'divisional', 'leave-utilization', 'late-warning'])],
            'month' => ['required', 'date_format:Y-m'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'employee_id' => ['nullable', 'integer', 'exists:employee_data,id'],
            'division_id' => ['nullable', 'integer', 'exists:division,id'],
            'unit_id' => ['nullable', 'integer', 'exists:unit,id'],
        ]);

        $fileDate = $data['date'] ?? $data['month'];
        $filename = 'galms-'.$data['type'].'-'.$fileDate;
        if (!empty($data['employee_id'])) {
            $filename .= '-employee-'.$data['employee_id'];
        }
        if (!empty($data['division_id'])) {
            $filename .= '-division-'.$data['division_id'];
        }

        if (! empty($data['unit_id'])) {
            $filename .= '-unit-'.$data['unit_id'];
        }

        return $reports->pdf(
            $data['type'],
            $data['month'],
            $data['employee_id'] ?? null,
            $data['division_id'] ?? null,
            $data['date'] ?? null,
            $data['unit_id'] ?? null
        )->download($filename.'.pdf');
    }

    public function snapshots()
    {
        return response()->json(['data' => DB::table('report_runs')->orderByDesc('month')->get()]);
    }

    public function snapshot(int $id)
    {
        $report = DB::table('report_runs')->find($id);
        abort_unless($report && Storage::disk('local')->exists($report->path), 404);

        return Storage::disk('local')->download($report->path);
    }
}
