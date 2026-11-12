<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Http\Requests\MasterDataRequest;
use App\Http\Resources\MasterDataResource;
use App\Services\DivisionOrder;
use App\Services\MasterDataService;
use Illuminate\Support\Facades\Gate;

class MasterDataController extends Controller
{
    public function __construct(private MasterDataService $service) {}

    public function index(ListRequest $request, string $module)
    {
        [$class,$column] = $this->service->definition($module);
        Gate::authorize('viewAny', $class);
        $data = $request->validated();
        $query = $class::query();
        if ($term = $data['search'] ?? null) {
            $query->where($column, 'like', "%{$term}%");
        }

        $sort = $data['sort'] ?? 'id';
        $direction = $data['direction'] ?? 'asc';

        if ($sort === 'id') {
            $query->orderBy('id', $direction);
        } else {
            $query->orderBy($column, $direction)->orderBy('id', 'asc');
        }

        return MasterDataResource::collection($query->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(MasterDataRequest $request, string $module)
    {
        [$class,$column] = $this->service->definition($module);
        Gate::authorize('create', $class);

        $payload = [$column => $request->validated('name')];
        if ($module === 'divisions' && $request->has('status')) {
            $payload['status'] = $request->validated('status');
        }

        return new MasterDataResource($class::create($payload));
    }

    public function show(string $module, int $record)
    {
        [$class] = $this->service->definition($module);
        $model = $class::findOrFail($record);
        Gate::authorize('view', $model);

        return new MasterDataResource($model);
    }

    public function update(MasterDataRequest $request, string $module, int $record)
    {
        [$class,$column] = $this->service->definition($module);
        $model = $class::findOrFail($record);
        Gate::authorize('update', $model);

        $payload = [];
        if ($request->has('name')) {
            $payload[$column] = $request->validated('name');
        }
        if ($module === 'divisions' && $request->has('status')) {
            $payload['status'] = $request->validated('status');
        }

        $model->update($payload);

        return new MasterDataResource($model);
    }

    public function destroy(string $module, int $record)
    {
        [$class,,$column] = $this->service->definition($module);
        $model = $class::findOrFail($record);
        Gate::authorize('delete', $model);
        $this->service->delete($model, $column);

        return response()->json(['message' => 'Record deleted.']);
    }
}
