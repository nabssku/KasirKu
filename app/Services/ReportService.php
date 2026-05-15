<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private function tenantId(): string
    {
        return auth()->user()->tenant_id;
    }

    private function timezone(): string
    {
        return 'Asia/Jakarta';
    }

    public function getDailySales(string $date, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->setTimezone('UTC');
        $end = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->setTimezone('UTC');

        $transactions = Transaction::whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['completed', 'refunded', 'cancelled'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $grossSales = (float) $transactions->sum('grand_total');
        $refunds = (float) $transactions->whereIn('status', ['refunded', 'cancelled'])->sum('grand_total');

        return [
            'date'                => $date,
            'total_sales'         => $transactions->where('status', 'completed')->count(),
            'total_refunds'       => $transactions->whereIn('status', ['refunded', 'cancelled'])->count(),
            'gross_sales'         => $grossSales,
            'refund_amount'       => $refunds,
            'total_revenue'       => $grossSales - $refunds, // Net Sales
            'total_discount'      => (float) $transactions->where('status', 'completed')->sum('discount'),
            'average_transaction' => (float) ($transactions->where('status', 'completed')->avg('grand_total') ?? 0),
        ];
    }

    public function getMonthlyRevenue(int $year, int $month, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $start = \Carbon\Carbon::createFromDate($year, $month, 1, $timezone)->startOfMonth()->setTimezone('UTC');
        $end = \Carbon\Carbon::createFromDate($year, $month, 1, $timezone)->endOfMonth()->setTimezone('UTC');

        $transactions = Transaction::whereBetween('created_at', [$start, $end])
            ->whereIn('status', ['completed', 'refunded', 'cancelled'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $sales = $transactions->groupBy(function ($item) use ($timezone) {
            return $item->created_at->setTimezone($timezone)->format('Y-m-d');
        })->map(function ($items, $date) {
            $dayGross = (float) $items->sum('grand_total');
            $dayRefund = (float) $items->whereIn('status', ['refunded', 'cancelled'])->sum('grand_total');
            return [
                'date' => $date,
                'revenue' => $dayGross - $dayRefund,
                'gross_sales' => $dayGross,
                'refund_amount' => $dayRefund,
                'transaction_count' => $items->where('status', 'completed')->count()
            ];
        })->values();

        return [
            'year' => $year,
            'month' => $month,
            'total_revenue' => (float) $sales->sum('revenue'),
            'sales' => $sales,
        ];
    }

    public function getTopSellingProducts(int $limit = 10, ?string $outletId = null): Collection
    {
        return Transaction::join('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.status', 'completed')
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                'transaction_items.product_id',
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->groupBy('transaction_items.product_id', 'transaction_items.product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();
    }

    public function exportTransactionsToCsv(string $startDate, string $endDate, ?string $outletId = null): string
    {
        $transactions = Transaction::with(['user', 'customer'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $headers = ['Invoice Number', 'Date', 'User', 'Customer', 'Subtotal', 'Tax', 'Service Charge', 'Discount', 'Grand Total', 'Status'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($transactions as $transaction) {
            fputcsv($handle, [
                $transaction->invoice_number,
                $transaction->created_at->format('Y-m-d H:i:s'),
                $transaction->user->name,
                $transaction->customer?->name ?? 'N/A',
                $transaction->subtotal,
                $transaction->tax,
                $transaction->service_charge,
                $transaction->discount,
                $transaction->grand_total,
                $transaction->status,
            ]);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    public function getLeastSellingProducts(int $limit = 5, ?string $outletId = null): Collection
    {
        return TransactionItem::join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->where('transactions.status', 'completed')
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                'transaction_items.product_id',
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.quantity) as total_quantity'),
                DB::raw('SUM(transaction_items.subtotal) as total_revenue')
            )
            ->groupBy('transaction_items.product_id', 'transaction_items.product_name')
            ->orderBy('total_quantity', 'asc')
            ->limit($limit)
            ->get();
    }

    public function getPaymentMethodSummary(?string $outletId = null, string $startDate = null, string $endDate = null): Collection
    {
        $timezone = $this->timezone();
        
        if ($startDate && $endDate) {
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
            $end = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');
        } else {
            $date = $startDate ?? now()->format('Y-m-d');
            $start = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->setTimezone('UTC');
            $end = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->setTimezone('UTC');
        }

        return Payment::join('transactions', 'payments.transaction_id', '=', 'transactions.id')
            ->whereIn('transactions.status', ['completed', 'refunded', 'cancelled'])
            ->whereBetween('transactions.created_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                DB::raw('COALESCE(payments.payment_method_name, payments.payment_method) as payment_method'),
                DB::raw("COUNT(DISTINCT CASE WHEN LOWER(transactions.status) = 'completed' THEN transactions.id END) as total_transactions"),
                DB::raw("SUM(CASE 
                    WHEN LOWER(transactions.status) = 'completed' THEN 
                        (payments.amount - (CASE WHEN LOWER(payments.payment_method) = 'cash' THEN transactions.change_amount ELSE 0 END))
                    WHEN LOWER(transactions.status) IN ('refunded', 'cancelled') THEN -payments.amount 
                    ELSE 0 END) as total_revenue")
            )
            ->groupBy(DB::raw('COALESCE(payments.payment_method_name, payments.payment_method)'))
            ->get();
    }

    public function getPeakHours(?string $outletId = null, string $date = null): Collection
    {
        $date = $date ?? now()->format('Y-m-d');
        $timezone = $this->timezone();
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->setTimezone('UTC');
        $end = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->setTimezone('UTC');

        $transactions = Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        $hours = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = 0;
        }

        foreach ($transactions as $tx) {
            $hour = $tx->created_at->setTimezone($timezone)->hour;
            $hours[$hour]++;
        }

        return collect($hours)->map(fn($count, $hour) => [
            'hour' => sprintf('%02d:00', $hour),
            'count' => $count
        ])->values();
    }

    public function getRecentActivities(int $limit = 5, ?string $outletId = null, string $date = null): Collection
    {
        $timezone = $this->timezone();
        
        return Transaction::with(['user'])
            ->whereIn('status', ['completed', 'cancelled', 'refunded'])
            ->when($date, function($q) use ($date, $timezone) {
                $start = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->setTimezone('UTC');
                $end = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->setTimezone('UTC');
                return $q->whereBetween('created_at', [$start, $end]);
            })
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($tx) => [
                'id' => $tx->id,
                'invoice_number' => $tx->invoice_number,
                'cashier_name' => $tx->user->name,
                'grand_total' => (float) $tx->grand_total,
                'status' => $tx->status,
                'cancel_reason' => $tx->cancel_reason,
                'time' => $tx->created_at->setTimezone($timezone)->format('H:i'),
                'created_at' => $tx->created_at->toDateTimeString(),
            ]);
    }

    /**
     * Profit report: revenue - COGS (cost_price × quantity) per product
     */
    public function getProfitReport(string $startDate, string $endDate, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        $query = Transaction::join('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startUtc, $endUtc])
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                DB::raw('SUM(transaction_items.subtotal) as total_revenue'),
                DB::raw('SUM(products.cost_price * transaction_items.quantity) as total_cogs'),
                DB::raw('SUM(transaction_items.subtotal) - SUM(products.cost_price * transaction_items.quantity) as gross_profit'),
                DB::raw('COUNT(DISTINCT transactions.id) as transaction_count')
            )
            ->first();

        $productBreakdown = Transaction::join('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startUtc, $endUtc])
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                'transaction_items.product_name',
                DB::raw('SUM(transaction_items.quantity) as qty_sold'),
                DB::raw('SUM(transaction_items.subtotal) as revenue'),
                DB::raw('SUM(products.cost_price * transaction_items.quantity) as cogs'),
                DB::raw('SUM(transaction_items.subtotal) - SUM(products.cost_price * transaction_items.quantity) as profit')
            )
            ->groupBy('transaction_items.product_id', 'transaction_items.product_name')
            ->orderByDesc('profit')
            ->get();

        return [
            'period'             => ['start' => $startDate, 'end' => $endDate],
            'total_revenue'      => (float) $query?->total_revenue ?? 0,
            'total_cogs'         => (float) $query?->total_cogs ?? 0,
            'gross_profit'       => (float) $query?->gross_profit ?? 0,
            'transaction_count'  => (int) $query?->transaction_count ?? 0,
            'product_breakdown'  => $productBreakdown,
        ];
    }

    /**
     * Sales by staff (user)
     */
    public function getSalesByStaff(string $startDate, string $endDate, ?string $outletId = null): Collection
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        return Transaction::join('users', 'transactions.user_id', '=', 'users.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startUtc, $endUtc])
            ->when($outletId, fn ($q) => $q->where('transactions.outlet_id', $outletId))
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.grand_total) as total_revenue'),
                DB::raw('AVG(transactions.grand_total) as avg_transaction')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * Sales by outlet
     */
    public function getSalesByOutlet(string $startDate, string $endDate, ?string $outletId = null): Collection
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        return Transaction::join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startUtc, $endUtc])
            ->when($outletId, fn ($q) => $q->where('outlets.id', $outletId))
            ->select(
                'outlets.id as outlet_id',
                'outlets.name as outlet_name',
                DB::raw('COUNT(transactions.id) as transaction_count'),
                DB::raw('SUM(transactions.grand_total) as total_revenue')
            )
            ->groupBy('outlets.id', 'outlets.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    /**
     * Dead stock: ingredients with zero movement in the past X days
     */
    public function getDeadStockReport(?string $outletId = null, int $days = 30): array
    {
        $tenantId = $this->tenantId();
        $since    = now()->subDays($days)->format('Y-m-d');

        $deadIngredients = Ingredient::where('tenant_id', $tenantId)
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->where('current_stock', '>', 0)
            ->whereDoesntHave('stockMovements', fn ($q) => $q->where('created_at', '>=', $since))
            ->select('id', 'name', 'unit', 'current_stock', 'min_stock', 'cost_per_unit')
            ->get()
            ->map(fn ($i) => array_merge($i->toArray(), [
                'estimated_value' => (float) $i->current_stock * $i->cost_per_unit,
            ]));

        return [
            'period_days'       => $days,
            'since'             => $since,
            'total_items'       => $deadIngredients->count(),
            'total_est_value'   => $deadIngredients->sum('estimated_value'),
            'ingredients'       => $deadIngredients,
        ];
    }

    public function getIncomeReport(string $startDate, string $endDate, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        $transactions = Transaction::with(['items'])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->get();

        $grouped = $transactions->groupBy(function ($item) use ($timezone) {
            return $item->created_at->setTimezone($timezone)->format('Y-m-d');
        });

        $reportData = [];
        foreach ($grouped as $date => $items) {
            $totalRevenue = (float) $items->sum('grand_total');
            
            // Get descriptions (e.g., "2 Coto, 1 Teh")
            $productCounts = [];
            foreach ($items as $transaction) {
                foreach ($transaction->items as $item) {
                    $name = $item->product_name;
                    $productCounts[$name] = ($productCounts[$name] ?? 0) + $item->quantity;
                }
            }
            
            $descriptions = [];
            foreach ($productCounts as $name => $qty) {
                $descriptions[] = "{$qty} {$name}";
            }
            
            $reportData[] = [
                'date' => $date,
                'total_revenue' => $totalRevenue,
                'description' => implode(', ', $descriptions)
            ];
        }

        // Sort by date ascending
        usort($reportData, fn($a, $b) => strcmp($a['date'], $b['date']));

        return [
            'data' => $reportData,
            'total_overall' => (float) array_sum(array_column($reportData, 'total_revenue')),
        ];
    }

    public function getExpenseReport(string $startDate, string $endDate, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        $expenses = \App\Models\Expense::with(['category', 'shift'])
            ->whereBetween('date', [$startDate, $endDate])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->get();

        $reportData = [];
        foreach ($expenses as $expense) {
            $reportData[] = [
                'category_name' => $expense->category->name ?? 'Uncategorized',
                'source' => $expense->shift_id ? 'Shift' : 'Admin',
                'notes' => $expense->notes ?: '-',
                'date' => $expense->created_at->toDateTimeString(),
                'shift_opened_at' => $expense->shift ? $expense->shift->opened_at->toDateTimeString() : null,
                'total_amount' => (float) $expense->amount,
            ];
        }

        // Include manual cash drawer logs (Cash Out) individually to show reasons
        $manualLogs = \App\Models\CashDrawerLog::with(['shift'])->where('type', 'out')
            ->whereNull('expense_id')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($outletId, function($q) use ($outletId) {
                return $q->whereHas('shift', fn($sq) => $sq->where('outlet_id', $outletId));
            })
            ->get();

        foreach ($manualLogs as $log) {
            $reportData[] = [
                'category_name' => 'Kas Keluar Manual',
                'source' => 'Shift',
                'notes' => $log->reason ?? '-',
                'date' => $log->created_at->toDateTimeString(),
                'shift_opened_at' => $log->shift ? $log->shift->opened_at->toDateTimeString() : null,
                'total_amount' => (float) $log->amount,
            ];
        }

        // Sort by date descending
        usort($reportData, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return [
            'data' => $reportData,
            'total_overall' => (float) array_sum(array_column($reportData, 'total_amount')),
        ];
    }

    public function getProfitLossSummary(string $startDate, string $endDate, ?string $outletId = null): array
    {
        $timezone = $this->timezone();
        $startUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay()->setTimezone('UTC');
        $endUtc = \Carbon\Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->endOfDay()->setTimezone('UTC');

        $totalRevenue = (float) Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('grand_total');

        $totalExpense = (float) \App\Models\Expense::whereBetween('date', [$startDate, $endDate])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('amount');

        // Add manual cash out logs
        $manualCashOut = (float) \App\Models\CashDrawerLog::where('type', 'out')
            ->whereNull('expense_id')
            ->whereBetween('created_at', [$startUtc, $endUtc])
            ->when($outletId, function($q) use ($outletId) {
                return $q->whereHas('shift', fn($sq) => $sq->where('outlet_id', $outletId));
            })
            ->sum('amount');

        $totalExpense += $manualCashOut;

        return [
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpense,
            'net_profit' => $totalRevenue - $totalExpense,
            'status' => ($totalRevenue - $totalExpense) >= 0 ? 'profit' : 'loss'
        ];
    }

    public function getTransactionsByDate(string $date, ?string $outletId = null): Collection
    {
        $timezone = $this->timezone();
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()->setTimezone('UTC');
        $end = \Carbon\Carbon::createFromFormat('Y-m-d', $date, $timezone)->endOfDay()->setTimezone('UTC');

        return Transaction::with(['user', 'customer', 'items'])
            ->whereBetween('created_at', [$start, $end])
            ->where('status', 'completed')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('created_at')
            ->get();
    }
}
