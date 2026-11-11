<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Gate;

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

        return new EmployeeResource($this->service->create($request->validated()));
    }

    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);

        return new EmployeeResource($employee->load(['divisionRecord', 'unitRecord', 'designationRecord', 'profile']));
    }

    public function update(EmployeeRequest $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        return new EmployeeResource($this->service->update($employee, $request->validated()));
    }

    public function destroy(Employee $employee)
    {
        Gate::authorize('delete', $employee);
        $this->service->archive($employee);

        return response()->json(['message' => 'Employee archived.']);
    }
}
