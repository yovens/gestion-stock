<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function index(Request $request): View
    {
        $p = $this->dashboard->period((string) $request->query('periode', 'today'));
        $canSeeProfit = $request->user()->can('reports.profit');

        $daily = $this->dashboard->dailySeries(30);
        $byCategory = $this->dashboard->revenueByCategory($p);
        $byMethod = $this->dashboard->revenueByPaymentMethod($p);

        return view('dashboard.index', [
            'period' => $p,
            'periods' => DashboardService::PERIODS,
            'canSeeProfit' => $canSeeProfit,
            'kpis' => $this->dashboard->kpis($p),
            'topProducts' => $this->dashboard->topProducts($p),
            'sleeping' => $this->dashboard->sleepingProducts(),
            'alerts' => $this->dashboard->stockAlerts(),
            'debtors' => $this->dashboard->topDebtors(),
            'sessions' => $this->dashboard->openSessions(),
            'cashiers' => $this->dashboard->cashierPerformance($p),
            'recent' => $this->dashboard->recentSales(),
            'tables' => compact('daily', 'byCategory', 'byMethod'),
            'charts' => [
                'daily' => $canSeeProfit ? $daily : array_merge($daily, ['profit' => null]),
                'byCategory' => $byCategory,
                'byMethod' => $byMethod,
            ],
        ]);
    }
}
