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
        if ($module === 'divisions') {
            $query->orderByRaw(DivisionOrder::sql('division'));
        }

        return MasterDataResource::collection($query->orderBy($column, $data['direction'] ?? 'asc')->orderBy('id')->paginate($data['per_page'] ?? 15)->withQueryString());
    }

    public function store(MasterDataRequest $request, string $module)
    {
        [$class,$column] = $this->service->definition($module);
        Gate::authorize('create', $class);

        return new MasterDataResource($class::create([$column => $request->validated('name')]));
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
        $model->update([$column => $request->validated('name')]);

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
