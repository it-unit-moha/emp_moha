<?php

namespace App\Services;

use DomainException;

class LeaveRulesService
{
    public function rules(): array
    {
        return config('galms.leave');
    }

    public function validateShortLeave(int $minutes, int $usedThisMonth): bool
    {
        return $minutes > 0 && $minutes <= config('galms.leave.short_leave_minutes') && $usedThisMonth >= 0 && $usedThisMonth < config('galms.leave.short_leave_monthly_limit');
    }

    public function applyLatePenalty(): never
    {
        throw new DomainException('Automatic deductions are disabled pending stakeholder approval and verified eligibility, holidays, and calculation rules.');
    }
}
