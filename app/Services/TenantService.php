<?php

namespace App\Services;

use App\DTOs\RegisterTenantDTO;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantService
{
    protected $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function register(RegisterTenantDTO $dto): array
    {
        return DB::transaction(function () use ($dto) {
            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $dto->tenant_name,
                'email' => $dto->email,
                'domain' => $dto->domain,
                'status' => 'active',
            ]);

            // Start Trial Subscription
            $this->subscriptionService->startTrial($tenant);

            // 2. Create Owner Role if not exists
            $ownerRole = Role::firstOrCreate(
                ['slug' => 'owner'],
                ['name' => 'Owner']
            );

            // 3. Create User (Owner)
            // We set the current tenant context manually for this creation
            app()->instance('current_tenant_id', $tenant->id);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $dto->owner_name,
                'email' => $dto->email,
                'password' => Hash::make($dto->password),
            ]);

            $user->roles()->attach($ownerRole->id);

            return [
                'tenant' => $tenant,
                'user' => $user,
            ];
        });
    }
}
