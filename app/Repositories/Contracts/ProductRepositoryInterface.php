<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface extends RepositoryInterface
{
    public function getLowStockProducts(int $threshold): Collection;
    public function findBySku(string $sku): ?Product;
    public function list(array $filters = []): Collection;
}
