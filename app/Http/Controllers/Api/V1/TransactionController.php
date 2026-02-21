<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\TransactionDTO;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);

        $transactions = Transaction::with(['items', 'customer', 'user', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => TransactionResource::collection($transactions),
            'meta'    => [
                'current_page' => $transactions->currentPage(),
                'last_page'    => $transactions->lastPage(),
                'per_page'     => $transactions->perPage(),
                'total'        => $transactions->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'items.*.price'      => ['required', 'numeric', 'min:0'],
            'customer_id'        => ['nullable', 'uuid', 'exists:customers,id'],
            'table_id'           => ['nullable', 'uuid', 'exists:restaurant_tables,id'],
            'shift_id'           => ['nullable', 'uuid', 'exists:shifts,id'],
            'type'               => ['nullable', 'string', 'in:dine_in,takeaway,delivery'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => ['required', 'numeric', 'min:0'],
            'payment_method'     => ['required', 'string', 'in:cash,bank_transfer,e-wallet'],
            'notes'              => ['nullable', 'string'],
            'payments'           => ['nullable', 'array'],
            'payments.*.method'  => ['required', 'string', 'in:cash,bank_transfer,e-wallet'],
            'payments.*.amount'  => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string'],
        ]);

        $dto = TransactionDTO::fromRequest($validated);
        $transaction = $this->transactionService->createTransaction($dto);

        return response()->json([
            'success' => true,
            'message' => 'Transaction completed successfully.',
            'data'    => new TransactionResource($transaction->load(['items', 'customer', 'payments'])),
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $transaction = Transaction::with(['items', 'customer', 'user', 'payments'])->findOrFail($id);

        $this->authorize('view', $transaction);

        return response()->json([
            'success' => true,
            'data'    => new TransactionResource($transaction),
        ]);
    }
    public function destroy(string $id): JsonResponse
    {
        $transaction = Transaction::with(['items', 'customer', 'user', 'payments'])->findOrFail($id);

        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction deleted successfully.',
        ]);
    }
}
