<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class MasterDataPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canWrite();
    }

    public function delete(User $user, Model $record): bool
    {
        return in_array($user->role, [Role::SuperAdmin, Role::Admin], true);
    }
}
