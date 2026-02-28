<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function __construct(
        protected ShiftService $shiftService
    ) {}

    public function getAllCategories()
    {
        return ExpenseCategory::all();
    }

    public function createCategory(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data);
    }

    public function updateCategory(string $id, array $data): ExpenseCategory
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function deleteCategory(string $id): void
    {
        $category = ExpenseCategory::findOrFail($id);
        $category->delete();
    }

    public function getAllExpenses(array $filters = [])
    {
        $query = Expense::with(['category', 'user', 'outlet']);

        if (!empty($filters['outlet_id'])) {
            $query->withoutGlobalScope(\App\Core\Scopes\OutletScope::class)->where('outlet_id', $filters['outlet_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('date', '<=', $filters['end_date']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function createExpense(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            // Handle file upload if present
            if (isset($data['attachment']) && $data['attachment'] instanceof \Illuminate\Http\UploadedFile) {
                $path = $data['attachment']->store('expenses', 'public');
                $data['attachment'] = $path;
            }

            $expense = Expense::create([
                'tenant_id'        => auth()->user()->tenant_id,
                'outlet_id'        => $data['outlet_id'],
                'category_id'      => $data['category_id'],
                'user_id'          => auth()->id(),
                'amount'           => $data['amount'],
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
                'date'             => $data['date'] ?? now()->toDateString(),
                'attachment'       => $data['attachment'] ?? null,
                'shift_id'         => $data['shift_id'] ?? null,
            ]);

            // If payment method is cash, integrate with CashDrawerLog
            if ($data['payment_method'] === 'cash') {
                $shift = null;
                if (!empty($data['shift_id'])) {
                    $shift = Shift::find($data['shift_id']);
                } else {
                    $shift = $this->shiftService->getCurrentShift($data['outlet_id']);
                }

                if ($shift && $shift->isOpen()) {
                    $expense->update(['shift_id' => $shift->id]);
                    $this->shiftService->addCashDrawerLog($shift, [
                        'type'   => 'out',
                        'amount' => $data['amount'],
                        'reason' => "Expense: " . ($expense->category->name ?? 'General') . " - " . ($data['notes'] ?? ''),
                    ]);
                }
            }

            return $expense;
        });
    }

    public function updateExpense(string $id, array $data): Expense
    {
        $expense = Expense::findOrFail($id);

        if (isset($data['attachment']) && $data['attachment'] instanceof \Illuminate\Http\UploadedFile) {
            // Delete old attachment if exists
            if ($expense->attachment) {
                Storage::disk('public')->delete($expense->attachment);
            }
            $path = $data['attachment']->store('expenses', 'public');
            $data['attachment'] = $path;
        }

        $expense->update($data);
        return $expense;
    }

    public function deleteExpense(string $id): void
    {
        $expense = Expense::findOrFail($id);
        
        if ($expense->attachment) {
            Storage::disk('public')->delete($expense->attachment);
        }

        $expense->delete();
    }
}
