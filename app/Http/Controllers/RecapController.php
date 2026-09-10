<?php

namespace App\Http\Controllers;

use App\Models\ReportingExport;
use App\Services\ReferenceSpreadsheetExport;
use App\Services\ReportRecap;
use App\Services\SpreadsheetExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecapController extends Controller
{
    public function referenceWorkbook(): BinaryFileResponse
    {
        $template = DB::table('reporting_workbook_templates')
            ->where('code', 'DATA-KINERJA-2025-2026')
            ->where('active', true)
            ->firstOrFail();
        $path = base_path($template->source_path);

        abort_unless(
            is_file($path) && hash_file('sha256', $path) === $template->sha256,
            409,
            'Workbook referensi berubah atau tidak lengkap.',
        );

        return response()->download(
            $path,
            'DATA KINERJA LAYANAN UNIT 2025-2026.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function download(ReportingExport $export): StreamedResponse
    {
        abort_unless($export->user_id === auth()->id() && $export->status === 'READY', 403);
        abort_unless(($export->filters['access_role'] ?? null) === auth()->user()->role && ($export->filters['access_unit'] ?? null) === auth()->user()->unit_id, 403);
        abort_unless(Storage::disk('local')->exists($export->path), 404);

        return Storage::disk('local')->download($export->path, 'rekap-siblu-'.$export->id.'.xlsx');
    }

    public function index(): View
    {
        return view('Admin.Recap.index');
    }

    public function export(
        Request $request,
        ReportRecap $recap,
        SpreadsheetExport $customExport,
        ReferenceSpreadsheetExport $referenceExport,
    ): BinaryFileResponse {
        $filters = $request->only(['periods', 'units', 'indicators', 'metrics', 'grouping']) + [
            'units' => [],
            'indicators' => [],
        ];
        $isReferenceFormat = $request->string('format')->toString() === 'reference';

        if ($isReferenceFormat) {
            $filters['grouping'] = 'PERIOD';
        }

        $result = $recap->build($filters);
        $path = $isReferenceFormat
            ? $referenceExport->create($result)
            : $customExport->create($result);
        $prefix = $isReferenceFormat ? 'laporan-kinerja-baku-' : 'rekap-custom-';

        return response()->download(
            $path,
            $prefix.now()->format('Ymd-His').'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend(true);
    }

    public function print(Request $request, ReportRecap $recap): View
    {
        $result = $recap->build($request->only(['periods', 'units', 'indicators', 'metrics', 'grouping']) + ['units' => [], 'indicators' => []]);

        return view('Admin.Recap.print', compact('result'));
    }
}
