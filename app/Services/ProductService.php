<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Services\CloudinaryService;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $repository,
        protected PlanLimitService $planLimit
    ) {}

    public function getAllProducts(array $filters = []): Collection
    {
        return $this->repository->list($filters);
    }

    public function createProduct(array $data): Product
    {
        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'products');

        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $uploadedUrl = CloudinaryService::upload($data['image'], 'products');
            if ($uploadedUrl) {
                $data['image'] = $uploadedUrl;
            } else {
                $path = $data['image']->store('products', 'public');
                $data['image'] = Storage::url($path);
            }
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

        $removeImage = isset($data['remove_image']) || (request()->has('remove_image') && request()->input('remove_image') === '1');
        unset($data['remove_image']);

        if (array_key_exists('image', $data) || $removeImage) {
            $imageInput = $data['image'] ?? null;
            if ($imageInput instanceof UploadedFile) {
                // Delete old image if it was stored locally
                if ($product->image && !str_contains($product->image, 'cloudinary.com')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $product->image));
                }
                $uploadedUrl = CloudinaryService::upload($imageInput, 'products');
                if ($uploadedUrl) {
                    $data['image'] = $uploadedUrl;
                } else {
                    $path = $imageInput->store('products', 'public');
                    $data['image'] = Storage::url($path);
                }
            } elseif ($removeImage || (is_string($imageInput) && trim($imageInput) === '') || $imageInput === null) {
                // Explicitly removed image
                if ($product->image && !str_contains($product->image, 'cloudinary.com')) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $product->image));
                }
                $data['image'] = null;
            } elseif (is_string($imageInput) && !empty($imageInput)) {
                $data['image'] = $imageInput;
            } else {
                unset($data['image']);
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
