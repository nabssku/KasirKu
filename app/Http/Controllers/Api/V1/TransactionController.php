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
        $user    = auth()->user();

        $query = Transaction::with(['items', 'customer', 'user', 'payments', 'table']);

        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $transactions = $query->orderBy('created_at', 'desc')
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
            'type'               => ['nullable', 'string', 'in:dine_in,takeaway,delivery,walk_in,online'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'paid_amount'        => [($request->status === 'pending' ? 'nullable' : 'required'), 'numeric', 'min:0'],
            'payment_method'     => [($request->status === 'pending' ? 'nullable' : 'required'), 'string', 'in:cash,bank_transfer,e-wallet'],
            'status'             => ['nullable', 'string', 'in:pending,completed'],
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
            'data'    => new TransactionResource($transaction->load(['items', 'customer', 'payments', 'outlet'])),
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
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.price'        => ['required', 'numeric', 'min:0'],
            'customer_id'          => ['nullable', 'uuid', 'exists:customers,id'],
            'table_id'             => ['nullable', 'uuid', 'exists:restaurant_tables,id'],
            'shift_id'             => ['nullable', 'uuid', 'exists:shifts,id'],
            'type'                 => ['nullable', 'string', 'in:dine_in,takeaway,delivery'],
            'discount'             => ['nullable', 'numeric', 'min:0'],
            'paid_amount'          => [($request->status === 'pending' ? 'nullable' : 'required'), 'numeric', 'min:0'],
            'payment_method'       => [($request->status === 'pending' ? 'nullable' : 'required'), 'string', 'in:cash,bank_transfer,e-wallet'],
            'status'               => ['nullable', 'string', 'in:pending,completed'],
            'notes'                => ['nullable', 'string'],
            'payments'             => ['nullable', 'array'],
            'payments.*.method'    => ['required', 'string', 'in:cash,bank_transfer,e-wallet'],
            'payments.*.amount'    => ['required', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string'],
        ]);

        $dto = TransactionDTO::fromRequest($validated);
        $transaction = $this->transactionService->updateTransaction($id, $dto);

        return response()->json([
            'success' => true,
            'message' => 'Transaction updated successfully.',
            'data'    => new TransactionResource($transaction->load(['items', 'customer', 'payments', 'outlet'])),
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

    /**
     * Generate structured receipt data for Bluetooth printing.
     */
    public function receipt(string $id): JsonResponse
    {
        $transaction = Transaction::with(['items', 'customer', 'user', 'outlet', 'table'])->findOrFail($id);

        $outlet = $transaction->outlet;

        $receipt = [
            'store_name'     => $outlet?->name ?? config('app.name', 'KasirKu'),
            'store_address'  => $outlet?->address ?? '',
            'store_phone'    => $outlet?->phone ?? '',
            'invoice_number' => $transaction->invoice_number,
            'date'           => $transaction->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i'),
            'cashier'        => $transaction->user?->name ?? 'Kasir',
            'customer'       => $transaction->customer?->name ?? 'Umum',
            'type'           => $transaction->type,
            'table_id'       => $transaction->table_id,
            'table_name'     => $transaction->table?->name,
            'items'          => $transaction->items->map(fn($item) => [
                'name'     => $item->product_name,
                'quantity' => $item->quantity,
                'price'    => (float) $item->price,
                'subtotal' => (float) $item->subtotal,
            ]),
            'subtotal'       => (float) $transaction->subtotal,
            'discount'       => (float) $transaction->discount,
            'tax'            => (float) $transaction->tax,
            'tax_rate'       => (float) $transaction->tax_rate,
            'service_charge' => (float) $transaction->service_charge,
            'grand_total'    => (float) $transaction->grand_total,
            'paid_amount'    => (float) $transaction->paid_amount,
            'change_amount'  => (float) $transaction->change_amount,
            'payment_method' => $transaction->payments->first()?->method ?? 'cash',
            'status'         => $transaction->status,
            'notes'          => $transaction->notes,
            'receipt_settings' => $outlet?->receipt_settings,
        ];

        return response()->json(['success' => true, 'data' => $receipt]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|min:3',
        ]);

        try {
            $transaction = $this->transactionService->cancelTransaction($id, $request->notes);
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibatalkan',
                'data'    => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
