<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Shift;
use App\Models\CashDrawerLog;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftService
{
    public function openShift(array $data): Shift
    {
        // Check for existing open shift in this outlet
        $existingShift = Shift::where('outlet_id', $data['outlet_id'])
            ->where('status', 'open')
            ->first();

        if ($existingShift) {
            throw ValidationException::withMessages([
                'shift' => 'There is already an open shift for this outlet.',
            ]);
        }

        return Shift::create([
            'tenant_id'    => auth()->user()->tenant_id,
            'outlet_id'    => $data['outlet_id'],
            'opened_by'    => auth()->id(),
            'opening_cash' => $data['opening_cash'] ?? 0,
            'status'       => 'open',
            'opened_at'    => now(),
            'notes'        => $data['notes'] ?? null,
        ]);
    }

    public function closeShift(Shift $shift, array $data): Shift
    {
        if (!$shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'This shift is already closed.',
            ]);
        }

        // Check for active transactions
        $activeTransactions = Transaction::where('shift_id', $shift->id)
            ->whereIn('status', ['pending', 'open'])
            ->exists();

        if ($activeTransactions) {
            throw ValidationException::withMessages([
                'shift' => 'Masih ada transaksi aktif di shift ini. Selesaikan atau batalkan semua transaksi sebelum menutup shift.',
            ]);
        }

        return DB::transaction(function () use ($shift, $data) {
            $closingCash = $data['closing_cash'] ?? 0;
            
            // Temporary set for report calculation
            $shift->closing_cash = $closingCash;
            $report = $shift->report;

            $shift->update([
                'closed_by'       => auth()->id(),
                'closing_cash'    => $closingCash,
                'expected_cash'   => $report['expected_cash'],
                'cash_difference' => $report['difference'],
                'status'          => 'closed',
                'closed_at'       => now(),
                'notes'           => $data['notes'] ?? $shift->notes,
            ]);

            return $shift->fresh(['openedBy', 'closedBy', 'transactions']);
        });
    }

    public function getCurrentShift(string $outletId): ?Shift
    {
        return Shift::where('outlet_id', $outletId)
            ->where('status', 'open')
            ->first();
    }

    public function addCashDrawerLog(Shift $shift, array $data): CashDrawerLog
    {
        if (!$shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => 'Cannot log cash movement on a closed shift.',
            ]);
        }

        return CashDrawerLog::create([
            'shift_id'   => $shift->id,
            'user_id'    => auth()->id(),
            'expense_id' => $data['expense_id'] ?? null,
            'type'       => $data['type'],
            'amount'     => $data['amount'],
            'reason'     => $data['reason'] ?? null,
        ]);
    }
}
