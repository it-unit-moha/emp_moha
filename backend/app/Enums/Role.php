<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case HR = 'HR Officer';
    case Viewer = 'Authorized Viewer';

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageUsers(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }
}
