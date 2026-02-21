<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OutletService
{
    public function __construct(protected PlanLimitService $planLimit) {}

    public function all(int $perPage = 15): LengthAwarePaginator
    {
        return Outlet::orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Outlet
    {
        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'outlets');

        return Outlet::create(array_merge($data, [
            'tenant_id' => $tenantId,
        ]));
    }

    public function update(Outlet $outlet, array $data): Outlet
    {
        $outlet->update($data);
        return $outlet->fresh();
    }

    public function delete(Outlet $outlet): bool
    {
        return $outlet->delete();
    }

    /**
     * Assign a user to an outlet.
     */
    public function assignUser(Outlet $outlet, User $user): void
    {
        if ($user->tenant_id !== $outlet->tenant_id) {
            abort(403, 'User and outlet do not belong to the same tenant.');
        }

        $user->update(['outlet_id' => $outlet->id]);
    }

    public function getUsers(Outlet $outlet, int $perPage = 15): LengthAwarePaginator
    {
        return $outlet->users()->with('roles')->paginate($perPage);
    }
}
