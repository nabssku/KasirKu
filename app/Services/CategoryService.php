<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function __construct(
        protected CategoryRepositoryInterface $repository,
        protected PlanLimitService $planLimit
    ) {}

    public function getAllCategories(): Collection
    {
        return $this->repository->all();
    }

    public function createCategory(array $data): Category
    {
        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'categories');

        return $this->repository->create($data);
    }

    public function updateCategory(string $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    public function deleteCategory(string $id): bool
    {
        return $this->repository->delete($id);
    }

    public function getCategory(string $id): ?Category
    {
        return $this->repository->find($id);
    }
}
