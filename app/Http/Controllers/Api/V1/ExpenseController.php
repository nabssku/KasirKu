<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $expenseService
    ) {}

    // Categories
    public function indexCategories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->expenseService->getAllCategories(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = $this->expenseService->createCategory($request->all() + ['tenant_id' => auth()->user()->tenant_id]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data'    => $category,
        ], 201);
    }

    // Expenses
    public function index(Request $request): JsonResponse
    {
        $expenses = $this->expenseService->getAllExpenses($request->all());

        return response()->json([
            'success' => true,
            'data'    => $expenses->items(),
            'meta'    => [
                'current_page' => $expenses->currentPage(),
                'last_page'    => $expenses->lastPage(),
                'per_page'     => $expenses->perPage(),
                'total'        => $expenses->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->has('items') && is_string($request->items)) {
            $decoded = json_decode($request->items, true);
            if (is_array($decoded)) {
                $request->merge(['items' => $decoded]);
            }
        }

        $validator = Validator::make($request->all(), [
            'outlet_id'      => 'required|exists:outlets,id',
            'category_id'    => 'required|exists:expense_categories,id',
            'amount'         => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,bank_transfer,other',
            'date'           => 'required|date',
            'notes'          => 'nullable|string',
            'reference_number' => 'nullable|string',
            'attachment'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'shift_id'       => 'nullable|exists:shifts,id',
            'type'           => 'required|in:operational,ingredient_purchase',
            'items'          => 'required_if:type,ingredient_purchase|array',
            'items.*.ingredient_id' => 'required_with:items|exists:ingredients,id',
            'items.*.quantity'      => 'required_with:items|numeric|min:0.0001',
            'items.*.unit_price'    => 'required_with:items|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $expense = $this->expenseService->createExpense($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Expense recorded successfully',
            'data'    => $expense,
        ], 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->expenseService->deleteExpense($id);

        return response()->json([
            'success' => true,
            'message' => 'Expense deleted successfully',
        ]);
    }
}
