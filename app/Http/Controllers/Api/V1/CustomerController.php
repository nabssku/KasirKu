<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = (int) $request->query('per_page', 15);
        $filters = $request->only(['search']);

        $customers = $this->customerService->getAllCustomers($perPage, $filters);

        return CustomerResource::collection($customers);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => [
                'nullable', 'email',
                "unique:customers,email,NULL,id,tenant_id,{$tenantId}",
            ],
            'phone'   => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $customer = $this->customerService->createCustomer($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data'    => new CustomerResource($customer),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $customer = $this->customerService->getCustomer($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new CustomerResource($customer),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $customer = $this->customerService->getCustomer($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'email'   => [
                'sometimes', 'nullable', 'email',
                "unique:customers,email,{$id},id,tenant_id,{$tenantId}",
            ],
            'phone'   => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string'],
        ]);

        $this->customerService->updateCustomer($id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data'    => new CustomerResource($customer->refresh()),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $customer = $this->customerService->getCustomer($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);
        }

        $this->customerService->deleteCustomer($id);

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }
}
