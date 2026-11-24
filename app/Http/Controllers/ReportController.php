<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\Branch;
use App\Services\ActivityLogService;
use App\Services\ReportService;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports, ActivityLogService $audit)
    {
        $user = $request->user();
        $type = array_key_exists($request->input('type'), ReportService::TYPES) ? $request->input('type') : 'sales_daily';
        $range = DateRange::fromRequest($request, '30d');

        // Kasir (jika diberi izin reports.view) otomatis dibatasi ke cabangnya.
        $branchId = $user->isOwner() ? ($request->filled('branch_id') ? $request->input('branch_id') : null) : $user->branch_id;

        $report = $reports->build($type, $range, $branchId);

        if ($format = $request->input('export')) {
            return $this->export($format, $type, $report, $audit);
        }

        return view('reports.index', [
            'report' => $report, 'type' => $type, 'types' => ReportService::TYPES,
            'periode' => $range->preset, 'presets' => DateRange::PRESETS, 'rangeLabel' => $range->label(),
            'dateFrom' => $range->start->format('Y-m-d'), 'dateTo' => $range->end->format('Y-m-d'),
            'branches' => $user->isOwner() ? Branch::orderBy('nama_cabang')->get(['id_cabang', 'nama_cabang']) : collect(),
            'limit' => 500,
        ]);
    }

    private function export(string $format, string $type, array $report, ActivityLogService $audit)
    {
        $name = 'bogarasa-' . $type . '-' . now()->format('Ymd-His');

        if ($format === 'xlsx') {
            if (! class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
                return back()->with('error', 'Ekspor Excel membutuhkan paket maatwebsite/excel. Jalankan: composer require maatwebsite/excel');
            }
            $audit->log('report', 'export', "Ekspor {$report['title']} (xlsx)");

            return \Maatwebsite\Excel\Facades\Excel::download(new ArrayExport($report['headings'], $report['rows']), $name . '.xlsx');
        }

        $audit->log('report', 'export', "Ekspor {$report['title']} (csv)");

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8
            fputcsv($out, $report['headings']);
            foreach ($report['rows'] as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row));
            }
            fclose($out);
        }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah CSV/formula injection di Excel. */
    private function safeCell(mixed $v): mixed
    {
        return (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) ? "'" . $v : $v;
    }
}
