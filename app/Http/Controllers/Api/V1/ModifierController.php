<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use App\Models\Modifier;
use App\Services\PlanLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModifierController extends Controller
{
    public function __construct(protected PlanLimitService $planLimit) {}

    // ─── Modifier Groups ──────────────────────────────────────────────────────

    public function indexGroups(Request $request): JsonResponse
    {
        $groups = ModifierGroup::with('modifiers')
            ->orderBy('sort_order')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json(['success' => true, 'data' => $groups]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'required'   => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $this->planLimit->enforce(auth()->user()->tenant_id, 'modifier_groups');

        $group = ModifierGroup::create($validated);

        return response()->json(['success' => true, 'data' => $group->load('modifiers')], 201);
    }

    public function showGroup(string $id): JsonResponse
    {
        $group = ModifierGroup::with('modifiers')->findOrFail($id);

        return response()->json(['success' => true, 'data' => $group]);
    }

    public function updateGroup(Request $request, string $id): JsonResponse
    {
        $group = ModifierGroup::findOrFail($id);

        $validated = $request->validate([
            'name'       => ['sometimes', 'string', 'max:255'],
            'required'   => ['nullable', 'boolean'],
            'min_select' => ['nullable', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $group->update($validated);

        return response()->json(['success' => true, 'data' => $group->load('modifiers')]);
    }

    public function destroyGroup(string $id): JsonResponse
    {
        ModifierGroup::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Modifier group deleted.']);
    }

    // ─── Modifiers (within a group) ───────────────────────────────────────────

    public function storeModifier(Request $request, string $groupId): JsonResponse
    {
        $group = ModifierGroup::findOrFail($groupId);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer'],
        ]);

        $modifier = $group->modifiers()->create($validated);

        return response()->json(['success' => true, 'data' => $modifier], 201);
    }

    public function updateModifier(Request $request, string $groupId, string $modifierId): JsonResponse
    {
        $modifier = Modifier::where('modifier_group_id', $groupId)->findOrFail($modifierId);

        $validated = $request->validate([
            'name'         => ['sometimes', 'string', 'max:255'],
            'price'        => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer'],
        ]);

        $modifier->update($validated);

        return response()->json(['success' => true, 'data' => $modifier]);
    }

    public function destroyModifier(string $groupId, string $modifierId): JsonResponse
    {
        Modifier::where('modifier_group_id', $groupId)->findOrFail($modifierId)->delete();

        return response()->json(['success' => true, 'message' => 'Modifier deleted.']);
    }
}
