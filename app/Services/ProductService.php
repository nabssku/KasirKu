<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $data['image']->store('products', 'public');
        }

        $modifierGroupIds = $data['modifier_group_ids'] ?? [];
        unset($data['modifier_group_ids']);

        $product = $this->repository->create($data);

        if (!empty($modifierGroupIds)) {
            $product->modifierGroups()->sync($modifierGroupIds);
        }

        return $product;
    }

    public function updateProduct(string $id, array $data): bool
    {
        $product = $this->repository->find($id);
        
        if (!$product) {
            return false;
        }

        if (array_key_exists('image', $data)) {
            if ($data['image'] instanceof UploadedFile) {
                // Delete old image if exists
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $data['image']->store('products', 'public');
            } elseif (empty($data['image'])) {
                // Explicitly removed
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = null;
            }
        }

        if (array_key_exists('modifier_group_ids', $data)) {
            $product->modifierGroups()->sync($data['modifier_group_ids'] ?? []);
            unset($data['modifier_group_ids']);
        } elseif (isset($data['clear_modifier_groups'])) {
            $product->modifierGroups()->sync([]);
            unset($data['clear_modifier_groups']);
        }

        return $this->repository->update($id, $data);
    }

    public function deleteProduct(string $id): bool
    {
        $product = $this->repository->find($id);

        if ($product && $product->image) {
            // We keep the image for soft deletes, but if we were to permanently delete:
            // Storage::disk('public')->delete($product->image);
        }

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
