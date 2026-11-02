<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceImportRequest;
use App\Services\LeaveRulesService;

class FoundationController extends Controller
{
    public function attendance()
    {
        return response()->json(['data' => [], 'meta' => ['implemented' => false, 'message' => 'No verified device format or attendance log schema is available. Import and calculation are disabled.', 'schedules' => config('galms.schedules')]]);
    }

    public function import(AttendanceImportRequest $request)
    {
        return response()->json(['message' => 'Upload validated, but import is disabled until a real device sample, parser, duplicate strategy, and transactional import tables are reviewed. No file or records were saved.'], 409);
    }

    public function leaves(LeaveRulesService $rules)
    {
        return response()->json(['data' => [], 'meta' => ['implemented' => false, 'message' => 'Leave applications await approved eligibility, holiday and workflow rules.', 'rules' => $rules->rules()]]);
    }
}
