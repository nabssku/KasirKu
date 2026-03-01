<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function getContext(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id');
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        $tenantId = $user->tenant_id;

        // 1. All Outlets Summary (Crucial for Owner)
        $outlets = Outlet::where('tenant_id', $tenantId)->get();
        $totalOutlets = $outlets->count();
        $outletsList = $outlets->map(fn($o) => [
            'id' => $o->id,
            'name' => $o->name,
            'address' => $o->address,
            'phone' => $o->phone,
            'is_active' => $o->is_active,
        ]);

        // 2. Today's Performance Breakdown by Outlet
        $todayStr = now()->format('Y-m-d');
        $salesByOutletToday = Transaction::where('tenant_id', $tenantId)
            ->whereDate('created_at', $todayStr)
            ->where('status', 'completed')
            ->select('outlet_id', DB::raw('SUM(grand_total) as total_revenue'), DB::raw('COUNT(id) as transaction_count'))
            ->groupBy('outlet_id')
            ->get()
            ->map(fn($s) => [
                'outlet_name' => $outlets->firstWhere('id', $s->outlet_id)?->name ?? 'Unknown',
                'revenue' => (float)$s->total_revenue,
                'transactions' => (int)$s->transaction_count,
            ]);

        // 3. Basic Business Counts
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $totalStaff = User::where('tenant_id', $tenantId)->count();
        $totalProducts = Product::where('tenant_id', $tenantId)->count();
        $totalCategories = Category::where('tenant_id', $tenantId)->count();
        $categoriesList = Category::where('tenant_id', $tenantId)->pluck('name');

        // 4. Current Context Summary (Selected Outlet)
        $dailySales = $this->reportService->getDailySales($todayStr, $outletId);
        $todayExpenses = Expense::where('expenses.tenant_id', $tenantId)
            ->when($outletId, fn($q) => $q->where('expenses.outlet_id', $outletId))
            ->whereDate('date', $todayStr)
            ->sum('amount');

        // 5. Weekly Financials
        $startOfWeek = now()->startOfWeek()->format('Y-m-d');
        $thisWeekRevenue = Transaction::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startOfWeek . ' 00:00:00', now()->format('Y-m-d H:i:s')])
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->sum('grand_total');
            
        $thisWeekExpenses = Expense::where('expenses.tenant_id', $tenantId)
            ->when($outletId, fn($q) => $q->where('expenses.outlet_id', $outletId))
            ->whereBetween('date', [$startOfWeek, now()->format('Y-m-d')])
            ->sum('amount');

        // 6. Monthly Revenue Trend
        $monthlyTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthDate = now()->subMonths($i);
            $trend = $this->reportService->getMonthlyRevenue($monthDate->year, $monthDate->month, $outletId);
            $monthlyTrends[] = [
                'month' => $monthDate->format('M Y'),
                'revenue' => $trend['monthly_total'],
            ];
        }

        // 7. Recent Context Details
        $topProducts = $this->reportService->getTopSellingProducts(5, $outletId);
        $lowStock = Ingredient::where('tenant_id', $tenantId)
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->get(['name', 'current_stock', 'unit', 'min_stock']);

        $recentTransactions = Transaction::with('customer')
            ->where('tenant_id', $tenantId)
            ->when($outletId, fn($q) => $q->where('outlet_id', $outletId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn($t) => [
                'invoice' => $t->invoice_number,
                'customer' => $t->customer?->name ?? 'N/A',
                'total' => (float)$t->grand_total,
                'status' => $t->status,
                'time' => $t->created_at->diffForHumans(),
            ]);

        // 8. Tables for Current Outlet
        $currentOutlet = $outletId ? $outlets->firstWhere('id', $outletId) : $outlets->first();
        $tables = [];
        if ($currentOutlet) {
            $tables = RestaurantTable::where('outlet_id', $currentOutlet->id)
                ->get(['name', 'capacity', 'status', 'floor']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'business_name' => 'JagoKasir POS',
                'outlets_overview' => [
                    'total_outlets' => $totalOutlets,
                    'list' => $outletsList,
                    'today_sales_breakdown' => $salesByOutletToday,
                ],
                'summary' => [
                    'total_customers' => $totalCustomers,
                    'total_staff' => $totalStaff,
                    'total_products' => $totalProducts,
                    'total_categories' => $totalCategories,
                    'category_names' => $categoriesList,
                ],
                'selected_outlet' => [
                    'name' => $currentOutlet?->name ?? 'Unknown',
                    'address' => $currentOutlet?->address ?? 'N/A',
                    'today' => [
                        'revenue' => (float)$dailySales['total_revenue'],
                        'expenses' => (float)$todayExpenses,
                        'transactions' => $dailySales['total_sales'],
                    ],
                    'tables' => [
                        'total' => $tables->count(),
                        'list' => $tables,
                    ],
                ],
                'financials' => [
                    'this_week_revenue' => (float)$thisWeekRevenue,
                    'this_week_expenses' => (float)$thisWeekExpenses,
                    'monthly_trends' => $monthlyTrends,
                ],
                'inventory' => [
                    'top_products' => $topProducts,
                    'low_stock_alerts' => $lowStock,
                ],
                'recent_transactions' => $recentTransactions,
                'currency' => 'IDR',
            ]
        ]);
    }
}
