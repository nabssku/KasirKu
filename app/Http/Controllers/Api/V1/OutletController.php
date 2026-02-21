<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Services\OutletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function __construct(protected OutletService $outletService) {}

    public function index(Request $request): JsonResponse
    {
        $outlets = $this->outletService->all((int) $request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $outlets]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'address'        => ['nullable', 'string'],
            'phone'          => ['nullable', 'string'],
            'email'          => ['nullable', 'email'],
            'tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $outlet = $this->outletService->create($validated);

        return response()->json(['success' => true, 'data' => $outlet], 201);
    }

    public function show(string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        return response()->json(['success' => true, 'data' => $outlet]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);

        $validated = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'address'        => ['nullable', 'string'],
            'phone'          => ['nullable', 'string'],
            'email'          => ['nullable', 'email'],
            'tax_rate'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_charge' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active'      => ['nullable', 'boolean'],
        ]);

        $outlet = $this->outletService->update($outlet, $validated);

        return response()->json(['success' => true, 'data' => $outlet]);
    }

    public function destroy(string $id): JsonResponse
    {
        $outlet = Outlet::findOrFail($id);
        $this->outletService->delete($outlet);

        return response()->json(['success' => true, 'message' => 'Outlet deleted.']);
    }
}
