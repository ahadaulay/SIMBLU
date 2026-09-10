<?php

namespace App\Jobs;

use App\Models\ReportingExport;
use App\Models\User;
use App\Services\ReportRecap;
use App\Services\SpreadsheetExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BuildReportingExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $exportId)
    {
        $this->onConnection('reporting');
    }

    public function handle(ReportRecap $recap, SpreadsheetExport $spreadsheet): void
    {
        $export = ReportingExport::findOrFail($this->exportId);
        if ($export->status === 'READY') {
            return;
        }
        $export->update(['status' => 'PROCESSING']);
        $path = null;
        $destination = null;
        try {
            $user = User::findOrFail($export->user_id);
            Auth::setUser($user);
            $result = $recap->build($export->filters);
            $path = $spreadsheet->create($result);
            $relative = 'reporting-exports/export-'.$export->id.'-'.Str::uuid().'.xlsx';
            $disk = Storage::disk('local');
            $disk->makeDirectory('reporting-exports');
            $destination = $disk->path($relative);
            if (! File::move($path, $destination)) {
                throw new \RuntimeException('Export could not be stored.');
            }
            $export->update(['status' => 'READY', 'path' => $relative]);
        } catch (\Throwable $exception) {
            if ($path && is_file($path)) {
                unlink($path);
            }
            if ($destination && is_file($destination)) {
                unlink($destination);
            }
            $export->update(['status' => 'FAILED']);
        } finally {
            Auth::forgetGuards();
        }
    }

    public function failed(?\Throwable $exception): void
    {
        ReportingExport::whereKey($this->exportId)->update(['status' => 'FAILED']);
    }
}
