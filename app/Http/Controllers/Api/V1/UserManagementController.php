<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function __construct(protected PlanLimitService $planLimit) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::with(['roles', 'outlet'])
            ->when($request->query('outlet_id'), fn ($q, $v) => $q->where('outlet_id', $v))
            ->when($request->query('role'), fn ($q, $v) => $q->whereHas('roles', fn ($rq) => $rq->where('slug', $v)))
            ->orderBy('name')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json(['success' => true, 'data' => $users]);
    }

    public function show(string $id): JsonResponse
    {
        $user = User::with(['roles', 'outlet'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $user]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:8'],
            'role_slug' => ['required', 'string', 'exists:roles,slug'],
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
        ]);

        $tenantId = auth()->user()->tenant_id;

        $this->planLimit->enforce($tenantId, 'users');

        $user = User::create([
            'tenant_id' => $tenantId,
            'outlet_id' => $validated['outlet_id'] ?? null,
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $role = Role::where('slug', $validated['role_slug'])->firstOrFail();
        $user->roles()->sync([$role->id]);

        return response()->json([
            'success' => true,
            'data'    => $user->load(['roles', 'outlet']),
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', "unique:users,email,{$id}"],
            'password'  => ['nullable', 'string', 'min:8'],
            'role_slug' => ['sometimes', 'string', 'exists:roles,slug'],
            'outlet_id' => ['nullable', 'uuid', 'exists:outlets,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update(array_filter([
            'name'      => $validated['name'] ?? null,
            'email'     => $validated['email'] ?? null,
            'password'  => isset($validated['password']) ? Hash::make($validated['password']) : null,
            'outlet_id' => array_key_exists('outlet_id', $validated) ? $validated['outlet_id'] : null,
            'is_active' => $validated['is_active'] ?? null,
        ], fn ($v) => $v !== null));

        if (!empty($validated['role_slug'])) {
            $role = Role::where('slug', $validated['role_slug'])->firstOrFail();
            $user->roles()->sync([$role->id]);
        }

        return response()->json(['success' => true, 'data' => $user->load(['roles', 'outlet'])]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }

        // Prevent deleting owners
        if ($user->hasRole('owner')) {
            return response()->json(['message' => 'Cannot delete an owner account.'], 403);
        }

        $user->delete();

        return response()->json(['success' => true, 'message' => 'User deleted.']);
    }
}
