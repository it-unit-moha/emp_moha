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
        $data = $request->validate(['type' => ['required', Rule::in(['attendance', 'leave-utilization', 'late-warning'])], 'month' => ['required', 'date_format:Y-m'], 'employee_id' => ['nullable', 'integer', 'exists:employee_data,id']]);

        return $reports->pdf($data['type'], $data['month'], $data['employee_id'] ?? null)->download('galms-'.$data['type'].'-'.$data['month'].'.pdf');
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
