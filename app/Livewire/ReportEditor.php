<?php

namespace App\Livewire;

use App\Models\UnitReport;
use App\Services\ReportWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ReportEditor extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $reportId;

    #[Locked]
    public int $version;

    public array $sections = [];

    public array $uploads = [];

    public string $note = '';

    public ?int $noteEntryId = null;

    public function boot(): void
    {
        Gate::authorize('reporting-access');
    }

    public function mount(int $reportId): void
    {
        $this->reportId = $reportId;
        $this->reload();
    }

    public function reload(): void
    {
        $report = UnitReport::with('entries')->findOrFail($this->reportId);
        Gate::authorize('reporting-view', $report);
        $this->version = $report->version;
        $this->sections = $report->entries->mapWithKeys(fn ($entry) => [$entry->id => ['rows' => $entry->rows, 'no_activity' => $entry->no_activity, 'explanation' => $entry->explanation ?? '', 'responsible_name' => $entry->responsible_name ?? '']])->all();
        $this->resetValidation();
    }

    public function addRow(int $entryId, ReportWorkflow $workflow): void
    {
        $report = UnitReport::findOrFail($this->reportId);
        $workflow->assertEditable($report);
        $entry = $report->entries()->findOrFail($entryId);
        abort_unless($entry->definition['form_type'] === 'DYNAMIC', 403);
        if (count($this->sections[$entryId]['rows']) < 500) {
            $this->sections[$entryId]['no_activity'] = false;
            $this->sections[$entryId]['rows'][] = ['key' => (string) Str::uuid(), 'label' => '', 'values' => array_fill_keys(array_column($entry->definition['fields'], 'code'), null)];
        }
    }

    public function removeRow(int $entryId, int $index, ReportWorkflow $workflow): void
    {
        $report = UnitReport::findOrFail($this->reportId);
        $workflow->assertEditable($report);
        abort_unless($report->entries()->findOrFail($entryId)->definition['form_type'] === 'DYNAMIC', 403);
        unset($this->sections[$entryId]['rows'][$index]);
        $this->sections[$entryId]['rows'] = array_values($this->sections[$entryId]['rows']);
    }

    public function save(ReportWorkflow $workflow): void
    {
        $workflow->save($this->reportId, $this->version, $this->sections, false);
        $this->reload();
        session()->flash('success', 'Draf tersimpan. Nilai perhitungan sudah diperbarui.');
    }

    public function submit(ReportWorkflow $workflow): void
    {
        $workflow->save($this->reportId, $this->version, $this->sections, true);
        $this->reload();
        session()->flash('success', 'Laporan diajukan untuk verifikasi.');
    }

    public function transition(string $action, ReportWorkflow $workflow): void
    {
        $workflow->transition($this->reportId, $this->version, $action, $this->note);
        $this->note = '';
        $this->reload();
        session()->flash('success', 'Status laporan diperbarui.');
    }

    public function addNote(ReportWorkflow $workflow): void
    {
        Gate::authorize('reporting-admin');
        $this->validate(['note' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($workflow): void {
            $report = $workflow->locked($this->reportId, $this->version);
            abort_unless($report->status === 'SUBMITTED' && $report->period->status !== 'LOCKED', 403);
            if ($this->noteEntryId) {
                $report->entries()->findOrFail($this->noteEntryId);
            }
            $report->notes()->create(['entry_id' => $this->noteEntryId, 'user_id' => auth()->id(), 'note' => $this->note]);
            $report->increment('version');
            $workflow->history($report, $report->status, 'Catatan verifikasi: '.$this->note);
        });
        $this->note = '';
        $this->reload();
    }

    public function resolveNote(int $id, ReportWorkflow $workflow): void
    {
        Gate::authorize('reporting-admin');
        DB::transaction(function () use ($id, $workflow): void {
            $report = $workflow->locked($this->reportId, $this->version);
            abort_unless(in_array($report->status, ['SUBMITTED', 'REVISION']) && $report->period->status !== 'LOCKED', 403);
            $report->notes()->findOrFail($id)->update(['resolved_at' => now()]);
            $report->increment('version');
            $workflow->history($report, $report->status, 'Catatan #'.$id.' diselesaikan.');
        });
        $this->reload();
    }

    public function updatedUploads(mixed $value, string $key): void
    {
        $entryId = (int) $key;
        if ($entryId > 0 && isset($this->uploads[$entryId])) {
            $this->upload($entryId, app(ReportWorkflow::class));
        }
    }

    public function upload(int $entryId, ReportWorkflow $workflow): void
    {
        $this->validate(['uploads.'.$entryId => ['required', 'file', 'mimes:'.implode(',', config('reporting.attachment_types')), 'max:'.config('reporting.attachment_max_kb')]]);
        $file = $this->uploads[$entryId];
        $path = null;
        try {
            DB::transaction(function () use ($entryId, $file, $workflow, &$path): void {
                $report = $workflow->locked($this->reportId, $this->version);
                $workflow->assertEditable($report);
                $report->entries()->findOrFail($entryId);
                $path = $file->store('reporting/'.$report->id, 'local');
                if (! $path) {
                    throw new \RuntimeException('Lampiran gagal disimpan.');
                }
                $report->attachments()->create(['entry_id' => $entryId, 'uploaded_by' => auth()->id(), 'name' => Str::limit(basename($file->getClientOriginalName()), 240, ''), 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
                $report->increment('version');
                $workflow->history($report, $report->status, 'Lampiran ditambahkan.');
                $this->version = $report->version;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
        unset($this->uploads[$entryId]);
        session()->flash('success', 'Lampiran tersimpan. Isian yang belum disimpan tetap tersedia.');
    }

    public function render(): View
    {
        $report = UnitReport::with(['unit', 'period', 'entries', 'attachments', 'notes.user'])->findOrFail($this->reportId);
        Gate::authorize('reporting-view', $report);
        $editable = auth()->user()->hasReportingRole(['UNIT']) && in_array($report->status, ['DRAFT', 'REVISION']) && $report->period->status === 'OPEN';

        return view('livewire.report-editor', ['report' => $report, 'editable' => $editable, 'histories' => $report->histories()->with('user')->latest('id')->limit(30)->get(), 'completion' => app(ReportWorkflow::class)->completion($report)]);
    }
}
