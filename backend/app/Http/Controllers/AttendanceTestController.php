<?php

namespace App\Http\Controllers;

use App\Services\AttendanceTestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceTestController extends Controller
{
    public function __construct(private AttendanceTestService $tests) {}

    private function authorizeTest(Request $request): void
    {
        abort_unless($request->user()->role->canManageUsers(), 403);
        abort_unless($this->tests->available(), 403);
    }

    public function index(Request $request)
    {
        $available = $request->user()->role->canManageUsers() && $this->tests->available();

        return response()->json(['available' => $available, 'runs' => $available ? DB::table('attendance_test_runs')->get()->map(fn ($r) => $this->tests->details($r)) : []]);
    }

    public function create(Request $request)
    {
        $this->authorizeTest($request);

        return response()->json(['data' => $this->tests->create()], 201);
    }

    public function import(Request $request, int $id)
    {
        $this->authorizeTest($request);

        return response()->json(['data' => $this->tests->import($id)]);
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorizeTest($request);

        return response()->json(['message' => 'Temporary test data deleted. Existing employee records were preserved.', 'deleted' => $this->tests->cleanup($id)]);
    }
}
