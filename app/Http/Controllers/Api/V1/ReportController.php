<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

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

    public function income(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getIncomeReport($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function expense(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getExpenseReport($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function profitLossSummary(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');

        $data = $this->reportService->getProfitLossSummary($startDate, $endDate, $outletId);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function exportIncome(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');
        $format    = $request->query('format', 'pdf');

        $data = $this->reportService->getIncomeReport($startDate, $endDate, $outletId);
        $data['start_date'] = $startDate;
        $data['end_date']   = $endDate;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.income', $data);
            return $pdf->download("income-report-{$startDate}-to-{$endDate}.pdf");
        }

        // CSV/Excel fallback
        $headers = ['Date', 'Total Revenue', 'Description'];
        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data['data'] as $row) {
                fputcsv($file, [$row['date'], $row['total_revenue'], $row['description']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=income-report-{$startDate}-to-{$endDate}.csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ]);
    }

    public function exportExpense(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');
        $format    = $request->query('format', 'pdf');

        $data = $this->reportService->getExpenseReport($startDate, $endDate, $outletId);
        $data['start_date'] = $startDate;
        $data['end_date']   = $endDate;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('reports.expense', $data);
            return $pdf->download("expense-report-{$startDate}-to-{$endDate}.pdf");
        }

        $headers = ['Category', 'Amount'];
        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data['data'] as $row) {
                fputcsv($file, [$row['category_name'], $row['total_amount']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=expense-report-{$startDate}-to-{$endDate}.csv",
        ]);
    }

    public function exportProfitLoss(Request $request)
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->query('end_date', now()->format('Y-m-d'));
        $outletId  = $request->query('outlet_id');
        
        $data = $this->reportService->getProfitLossSummary($startDate, $endDate, $outletId);
        $data['start_date'] = $startDate;
        $data['end_date']   = $endDate;

        $pdf = Pdf::loadView('reports.profit_loss', $data);
        return $pdf->download("profit-loss-report-{$startDate}-to-{$endDate}.pdf");
    }
}
