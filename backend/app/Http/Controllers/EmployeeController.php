<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $service) {}

    public function index(ListRequest $request)
    {
        Gate::authorize('viewAny', Employee::class);
        $data = $request->validated();
        $query = Employee::with(['divisionRecord', 'unitRecord', 'designationRecord', 'profile']);
        if ($term = $data['search'] ?? null) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('nic', 'like', "%{$term}%")->orWhere('emp_no', 'like', "%{$term}%"));
        }
        foreach (['division', 'unit', 'designation'] as $column) {
            if (isset($data[$column])) {
                $query->where($column, $data[$column]);
            }
        }
        if (($data['status'] ?? 'all') === 'active') {
            $query->where('is_active', 1);
        }
        if (($data['status'] ?? 'all') === 'archived') {
            $query->where('is_active', 0);
        }
        $sort = $data['sort'] ?? 'name';
        $query->orderBy($sort, $data['direction'] ?? 'asc');
        if ($sort !== 'id') {
            $query->orderBy('id');
        }

        return EmployeeResource::collection($query->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(EmployeeRequest $request)
    {
        Gate::authorize('create', Employee::class);

        return new EmployeeResource($this->saveRecord($request));
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);

        return new EmployeeResource($employee->load(['divisionRecord', 'unitRecord', 'designationRecord', 'profile']));
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        return new EmployeeResource($this->saveRecord($request, $employee));
    }

    public function destroy(Employee $employee)
    {
        Gate::authorize('delete', $employee);
        $this->service->archive($employee);

        return response()->json(['message' => 'Employee archived.']);
    }

    private function saveRecord(EmployeeRequest $request, ?Employee $employee = null): Employee
    {
        $data = $request->validated();
        $stored = [];
        $replaced = [];
        try {
            foreach (['application_form', 'signature', 'photo'] as $field) {
                unset($data[$field]);
                if ($file = $request->file($field)) {
                    $path = $file->store('employee-documents', 'local');
                    if (! $path) {
                        throw new \RuntimeException('Employee attachment could not be saved.');
                    }
                    $stored[] = $path;
                    $data[$field.'_path'] = $path;
                    if ($old = $employee?->profile?->{$field.'_path'}) {
                        $replaced[] = $old;
                    }
                }
            }
            $saved = $employee ? $this->service->update($employee, $data) : $this->service->create($data);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($stored);
            throw $error;
        }
        Storage::disk('local')->delete($replaced);

        return $saved;
    }

    public function attachment(Employee $employee, string $kind)
    {
        Gate::authorize('view', $employee);
        abort_unless(in_array($kind, ['application_form', 'signature', 'photo'], true), 404);
        $path = $employee->profile?->{$kind.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $name = 'employee-'.$employee->id.'-'.$kind.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $kind === 'application_form'
            ? Storage::disk('local')->download($path, $name, $headers)
            : Storage::disk('local')->response($path, $name, $headers);
    }
}
