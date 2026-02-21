<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\ModifierGroup;
use App\Models\Outlet;
use App\Models\Plan;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\User;

class PlanLimitService
{
    /**
     * Resource map: resource key => [ model class, plan column, human-readable label ]
     */
    private array $resourceMap = [
        'outlets'         => [Outlet::class,          'max_outlets',    'outlet'],
        'products'        => [Product::class,          'max_products',   'produk'],
        'customers'       => [Customer::class,         'max_customers',  'pelanggan'],
        'categories'      => [Category::class,         'max_categories', 'kategori'],
        'users'           => [User::class,             'max_users',      'pengguna'],
        'tables'          => [RestaurantTable::class,  'max_tables',     'meja'],
        'ingredients'     => [Ingredient::class,       'max_ingredients','bahan baku'],
        'modifier_groups' => [ModifierGroup::class,    'max_modifiers',  'modifier group'],
    ];

    /**
     * Enforce the plan limit for a given resource and tenant.
     * Aborts with HTTP 403 if the limit is reached.
     *
     * Pass -1 as a plan column value to allow unlimited creation.
     */
    public function enforce(string $tenantId, string $resource): void
    {
        if (!isset($this->resourceMap[$resource])) {
            return; // Unknown resource – skip silently
        }

        [$modelClass, $planColumn, $label] = $this->resourceMap[$resource];

        // Fetch the active subscription with its plan
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        // No active subscription → block resource creation
        if (!$subscription || !$subscription->plan) {
            throw new PlanLimitExceededException(
                'Anda tidak memiliki langganan aktif. Silakan berlangganan untuk melanjutkan.'
            );
        }

        /** @var Plan $plan */
        $plan  = $subscription->plan;
        $limit = (int) ($plan->{$planColumn} ?? 0);

        // -1 means unlimited
        if ($limit === -1) {
            return;
        }

        // Count existing (non-deleted) records for this tenant
        $count = $modelClass::where('tenant_id', $tenantId)->count();

        if ($count >= $limit) {
            throw new PlanLimitExceededException(
                sprintf(
                    'Batas maksimum %s pada paket "%s" Anda telah tercapai (maks: %d). Silakan upgrade paket Anda.',
                    $label,
                    $plan->name,
                    $limit
                ),
                maxAllowed: $limit,
                currentCount: $count,
                resource: $resource
            );
        }
    }

    /**
     * Return current usage and limit for all resources in a tenant.
     * Useful for displaying usage stats on the dashboard.
     */
    public function getUsage(string $tenantId): array
    {
        $subscription = Subscription::with('plan')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('status', 'trial')->where('trial_ends_at', '>=', now())
                  ->orWhere(fn ($q2) => $q2->where('status', 'active')->where('ends_at', '>=', now()));
            })
            ->latest()
            ->first();

        if (!$subscription || !$subscription->plan) {
            return [];
        }

        $plan   = $subscription->plan;
        $result = [];

        foreach ($this->resourceMap as $key => [$modelClass, $planColumn, $label]) {
            $limit = (int) ($plan->{$planColumn} ?? 0);
            $count = $modelClass::where('tenant_id', $tenantId)->count();

            $result[$key] = [
                'label'     => $label,
                'used'      => $count,
                'limit'     => $limit,
                'unlimited' => $limit === -1,
            ];
        }

        return $result;
    }
}
