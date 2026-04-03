<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PlanAdminValidationService
{
    /**
     * @throws ValidationException
     */
    public function assertBusinessRules(Request $request, ?int $excludePlanId = null): void
    {
        $isFree = $request->boolean('is_free');

        if ($isFree && $request->boolean('has_free_trial')) {
            throw ValidationException::withMessages([
                'has_free_trial' => ['A free plan cannot include a free trial.'],
            ]);
        }

        if ($isFree) {
            $q = Plan::query()->where('is_free', true);
            if ($excludePlanId !== null) {
                $q->where('id', '!=', $excludePlanId);
            }
            if ($q->exists()) {
                throw ValidationException::withMessages([
                    'is_free' => ['Only one free plan is allowed. Turn off “Free tier” on the other plan first.'],
                ]);
            }
        }
    }
}
