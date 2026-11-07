<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => collect(ReportService::REPORTS)->filter(fn ($r) => $request->user()->can($r[2])),
        ]);
    }

    public function show(Request $request, string $key): View|StreamedResponse
    {
        abort_unless(isset(ReportService::REPORTS[$key]), 404);
        abort_unless($request->user()->can(ReportService::REPORTS[$key][2]), 403);

        $from = CarbonImmutable::parse($request->query('du', now()->startOfMonth()->toDateString()));
        $to = CarbonImmutable::parse($request->query('au', today()->toDateString()));
        $report = $this->reports->run($key, $from, $to);

        // Colonnes de coût/bénéfice masquées sans la permission
        if (! $request->user()->can('reports.profit') && ! empty($report['profit'])) {
            $report['columns'] = array_diff_key($report['columns'], array_flip($report['profit']));
        }

        if ($request->query('format') === 'csv') {
            return $this->csv($report, $key, $from, $to);
        }

        return view('reports.show', [
            'key' => $key, 'report' => $report, 'from' => $from, 'to' => $to,
            'store' => Setting::get('store_name', config('app.name')),
        ]);
    }

    /** CSV au format Excel français : séparateur « ; », virgule décimale, UTF-8 avec BOM. */
    private function csv(array $report, string $key, CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        $name = Str::slug($report['title']).($report['period'] ? '_'.$from->format('Ymd').'-'.$to->format('Ymd') : '_'.today()->format('Ymd')).'.csv';
        $cols = $report['columns'];
        $fmt = fn ($v, $k) => is_float($v) || is_int($v) ? str_replace('.', ',', (string) $v) : (string) $v;

        return response()->streamDownload(function () use ($report, $cols, $fmt) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($cols), ';');
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => $fmt($row[$k] ?? '', $k), array_keys($cols)), ';');
            }
            if (! empty($report['totals'])) {
                fputcsv($out, array_map(fn ($k) => $fmt($report['totals'][$k] ?? '', $k), array_keys($cols)), ';');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
