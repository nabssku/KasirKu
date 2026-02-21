<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerService
{
    public function __construct(
        protected CustomerRepositoryInterface $repository,
        protected PlanLimitService $planLimit
    ) {}

    public function getAllCustomers(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage, $filters);
    }

    public function createCustomer(array $data): Customer
    {
        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'customers');

        /** @var Customer */
        return $this->repository->create($data);
    }

    public function getCustomer(string $id): ?Customer
    {
        /** @var Customer|null */
        return $this->repository->find($id);
    }

    public function updateCustomer(string $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    public function deleteCustomer(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
