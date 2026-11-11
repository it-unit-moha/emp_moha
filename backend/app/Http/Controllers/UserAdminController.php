<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAdminController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->role->canManageUsers(), 403);

        return response()->json(['data' => User::select(['id', 'name', 'email', 'role', 'is_active'])->orderBy('name')->get()]);
    }

    public function save(Request $request, ?int $id = null)
    {
        abort_unless($request->user()->role->canManageUsers(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)], 'role' => ['required', Rule::enum(Role::class)], 'is_active' => ['required', 'boolean'], 'password' => [$id ? 'prohibited' : 'required', 'string', 'min:12', 'max:128']]);
        $user = DB::transaction(function () use ($data, $id, $request) {
            User::whereIn('role', [Role::SuperAdmin->value, Role::Admin->value])->orderBy('id')->lockForUpdate()->get();
            if ($id === $request->user()->id && (! $data['is_active'] || $data['role'] !== $request->user()->role->value)) {
                throw ValidationException::withMessages(['role' => ['You cannot disable or change the role of your own active account.']]);
            }
            $user = $id ? User::findOrFail($id) : new User;
            $user->fill($data);
            $user->is_active = $data['is_active'];
            $user->save();
            app(WorkflowService::class)->audit($id ? 'update_account' : 'create_account', 'users', $user->id, ['role' => $data['role'], 'is_active' => $data['is_active']]);

            return $user;
        });

        return response()->json(['data' => $user], $id ? 200 : 201);
    }
}
