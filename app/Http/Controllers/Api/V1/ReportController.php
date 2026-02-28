<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function daily(Request $request): JsonResponse
    {
        $date     = $request->query('date', now()->format('Y-m-d'));
        $outletId = $request->query('outlet_id');
        $data     = $this->reportService->getDailySales($date, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function monthly(Request $request): JsonResponse
    {
        $year     = $request->query('year', now()->year);
        $month    = $request->query('month', now()->month);
        $outletId = $request->query('outlet_id');
        $data     = $this->reportService->getMonthlyRevenue($year, $month, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $limit    = (int) $request->query('limit', 10);
        $outletId = $request->query('outlet_id');
        $data     = $this->reportService->getTopSellingProducts($limit, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->query('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate   = $request->query('end_date',   now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $csv      = $this->reportService->exportTransactionsToCsv($startDate, $endDate, $outletId);
        $filename = "transactions-{$startDate}-to-{$endDate}.csv";

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function profit(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date',   now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getProfitReport($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function byStaff(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date',   now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getSalesByStaff($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function byOutlet(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date',   now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getSalesByOutlet($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function deadStock(Request $request): JsonResponse
    {
        $outletId = $request->query('outlet_id');
        $data     = $this->reportService->getDeadStockReport($outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
