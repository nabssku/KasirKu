<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    private function tenantId(): string
    {
        return auth()->user()->tenant_id;
    }

    public function getDailySales(string $date, ?string $outletId = null): array
    {
        $transactions = Transaction::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->get();

        return [
            'date'                => $date,
            'total_sales'         => $transactions->count(),
            'total_revenue'       => (float) $transactions->sum('grand_total'),
            'total_discount'      => (float) $transactions->sum('discount'),
            'average_transaction' => (float) ($transactions->avg('grand_total') ?? 0),
        ];
    }

    public function getMonthlyRevenue(int $year, int $month, ?string $outletId = null): array
    {
        $sales = Transaction::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('status', 'completed')
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(grand_total) as revenue'),
                DB::raw('COUNT(id) as transaction_count')
            )
            ->groupBy('date')
            ->get();

        return [
            'year'            => $year,
            'month'           => $month,
            'monthly_total'   => (float) $sales->sum('revenue'),
            'daily_breakdown' => $sales,
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

    /**
     * Profit report: revenue - COGS (cost_price × quantity) per product
     */
    public function getProfitReport(string $startDate, string $endDate, ?string $outletId = null): array
    {
        $query = Transaction::join('transaction_items', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->join('products', 'transaction_items.product_id', '=', 'products.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
            ->whereBetween('transactions.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
        return Transaction::join('users', 'transactions.user_id', '=', 'users.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
        return Transaction::join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
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
}
