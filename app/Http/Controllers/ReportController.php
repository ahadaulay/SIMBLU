<?php

namespace App\Http\Controllers;

use App\Models\ReportAttachment;
use App\Models\ReportHistory;
use App\Models\UnitReport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('Unit.Report.index');
    }

    public function show(UnitReport $report): View
    {
        Gate::authorize('reporting-view', $report);

        return view('Unit.Report.show', compact('report'));
    }

    public function attachment(ReportAttachment $attachment): StreamedResponse
    {
        Gate::authorize('reporting-view', $attachment->report);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->name, ['Content-Type' => $attachment->mime_type, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function history(ReportHistory $history): View
    {
        $report = UnitReport::with(['unit', 'period'])->findOrFail($history->report_id);
        Gate::authorize('reporting-view', $report);

        return view('Unit.Report.history', compact('report', 'history'));
    }
}
