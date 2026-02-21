<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $repository,
        protected PlanLimitService $planLimit
    ) {}

    public function getAllProducts(): Collection
    {
        return $this->repository->all();
    }

    public function createProduct(array $data): Product
    {
        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'products');

        return $this->repository->create($data);
    }

    public function updateProduct(string $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    public function deleteProduct(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getProduct(string $id): ?Product
    {
        return $this->repository->find($id);
    }

    public function checkLowStock(): Collection
    {
        // Default threshold of 5 can be moved to tenant settings
        return $this->repository->getLowStockProducts(5);
    }
}
