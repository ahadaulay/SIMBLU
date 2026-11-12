<?php

namespace App\Services;

use App\Models\IndicatorAssignment;
use App\Models\ReportingPeriod;
use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportWorkflow
{
    public function __construct(private IndicatorCalculator $calculator) {}

    public function start(int $periodId, User $user): UnitReport
    {
        abort_unless($user->hasReportingRole(['UNIT']), 403);

        return DB::transaction(function () use ($periodId, $user): UnitReport {
            $period = ReportingPeriod::lockForUpdate()->findOrFail($periodId);
            $existing = UnitReport::where('unit_id', $user->unit_id)
                ->where('period_id', $periodId)
                ->first();

            if ($existing) {
                return $existing;
            }

            abort_unless($period->status === 'OPEN', 403, 'Periode belum dibuka.');

            return $this->createReport($periodId, $user->unit_id, $user->id);
        });
    }

    private function createReport(int $periodId, int $unitId, int $creatorId): UnitReport
    {
        $assignments = IndicatorAssignment::with(['indicator.category', 'responsible'])
            ->where('period_id', $periodId)
            ->where('unit_id', $unitId)
            ->get();

        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages([
                'periodId' => 'Belum ada indikator yang ditugaskan untuk unit ini.',
            ]);
        }

        $report = UnitReport::create([
            'unit_id' => $unitId,
            'period_id' => $periodId,
            'created_by' => $creatorId,
        ]);

        foreach ($assignments as $assignment) {
            $definition = $assignment->indicator->definition();
            $items = match ($definition['form_type']) {
                'SINGLE' => [['code' => 'single', 'label' => $definition['name']]],
                'FIXED' => $definition['items'],
                default => [],
            };
            $rows = array_map(
                fn ($item) => [
                    'key' => $item['code'],
                    'label' => $item['label'],
                    'values' => array_fill_keys(
                        array_column($definition['fields'], 'code'),
                        null,
                    ),
                    'details' => [],
                ],
                $items,
            );

            // Definisi indikator disalin agar laporan lama tidak berubah saat master diedit.
            $report->entries()->create([
                'indicator_id' => $assignment->indicator_id,
                'definition' => $definition,
                'required' => $assignment->required,
                'rows' => $rows,
                'responsible_name' => $assignment->responsible?->name,
            ]);
        }

        $this->history($report, null, 'Laporan dibuat.');

        return $report;
    }

    public function import(int $periodId, int $unitId, int $indicatorId, array $rows, string $source): UnitReport
    {
        Gate::authorize('reporting-admin');

        return DB::transaction(function () use ($periodId, $unitId, $indicatorId, $rows, $source): UnitReport {
            $period = ReportingPeriod::lockForUpdate()->findOrFail($periodId);
            abort_unless($period->status === 'OPEN', 403, 'Buka periode sebelum mengimpor data.');
            $report = UnitReport::where('period_id', $periodId)
                ->where('unit_id', $unitId)
                ->lockForUpdate()
                ->first();

            $report ??= $this->createReport($periodId, $unitId, auth()->id());
            abort_unless($report->status === 'DRAFT', 403, 'Impor hanya boleh ke draf.');

            $entry = $report->entries()->where('indicator_id', $indicatorId)->firstOrFail();
            $alreadyFilled = collect($entry->rows)->contains(
                fn ($row) => collect($row['values'])->contains(
                    fn ($value) => $value !== null && $value !== ''
                )
            );

            if ($alreadyFilled || $entry->no_activity) {
                throw ValidationException::withMessages([
                    'file' => 'Indikator sudah memiliki data. Impor tidak boleh menimpa isian.',
                ]);
            }

            $normalized = $this->calculator->normalize($entry->definition, $rows, false);
            $entry->update([
                'rows' => $normalized,
                'explanation' => 'Impor historis: '.$source,
            ]);
            $report->increment('version');
            $this->history($report, $report->status, 'Impor historis: '.$source);

            return $report;
        });
    }

    public function assertEditable(UnitReport $report): void
    {
        Gate::authorize('reporting-view', $report);
        $isUnitUser = auth()->user()->hasReportingRole(['UNIT']);
        $hasEditableStatus = in_array($report->status, ['DRAFT', 'REVISION'], true);
        $isPeriodOpen = $report->period->status === 'OPEN';

        abort_unless(
            $isUnitUser && $hasEditableStatus && $isPeriodOpen,
            403,
            'Laporan tidak dapat diubah.'
        );
    }

    public function locked(int $id, int $version): UnitReport
    {
        $periodId = UnitReport::findOrFail($id)->period_id;
        ReportingPeriod::lockForUpdate()->findOrFail($periodId);
        $report = UnitReport::with(['period', 'entries', 'attachments'])->lockForUpdate()->findOrFail($id);
        Gate::authorize('reporting-view', $report);
        if ($report->version !== $version) {
            throw ValidationException::withMessages([
                'report' => 'Laporan berubah di tab atau akun lain. Muat ulang untuk mendapatkan data terbaru.',
            ]);
        }

        return $report;
    }

    public function save(int $id, int $version, array $sections, bool $submit): UnitReport
    {
        return DB::transaction(function () use ($id, $version, $sections, $submit): UnitReport {
            $report = $this->locked($id, $version);
            $this->assertEditable($report);
            $this->validateSections($report, $sections, $submit);
            $from = $report->status;
            if ($submit) {
                $report->status = 'SUBMITTED';
                $report->submitted_at = now();
            }
            $report->version++;
            $report->save();
            $this->history($report, $from, $submit ? 'Laporan diajukan.' : 'Draf disimpan.');

            return $report;
        });
    }

    private function validateSections(UnitReport $report, array $sections, bool $complete): void
    {
        $entryIds = $report->entries->pluck('id')->all();
        $hasDifferentEntries = array_diff($entryIds, array_keys($sections))
            || count($sections) !== $report->entries->count();

        if ($hasDifferentEntries) {
            throw ValidationException::withMessages([
                'report' => 'Struktur laporan tidak cocok. Muat ulang laporan.',
            ]);
        }

        foreach ($report->entries as $entry) {
            $section = $sections[$entry->id];
            $prefix = 'sections.'.$entry->id.'.';
            Validator::make(['sections' => [$entry->id => $section]], [
                $prefix.'rows' => ['present', 'array'],
                $prefix.'no_activity' => ['required', 'boolean'],
                $prefix.'explanation' => ['nullable', 'string', 'max:10000'],
                $prefix.'responsible_name' => ['nullable', 'string', 'max:150'],
            ])->validate();
            $hasValues = collect($section['rows'])->contains(
                fn ($row) => collect($row['values'] ?? [])->contains(
                    fn ($value) => $value !== null && $value !== ''
                )
            );
            $needed = $complete && ($entry->required || $hasValues || $section['no_activity']);

            try {
                $rows = $this->calculator->normalize(
                    $entry->definition,
                    $section['rows'],
                    $needed,
                    (bool) $section['no_activity'],
                );
            } catch (ValidationException $e) {
                $messages = [];
                foreach ($e->errors() as $key => $errors) {
                    $messages[$prefix.$key] = $errors;
                }
                throw ValidationException::withMessages($messages);
            }
            if ($needed && ($entry->definition['requires_explanation'] || $section['no_activity']) && blank($section['explanation'])) {
                throw ValidationException::withMessages([$prefix.'explanation' => 'Isi penjelasan, termasuk alasan tidak ada kegiatan.']);
            }
            if ($needed && $entry->definition['requires_attachment'] && ! $report->attachments->contains('entry_id', $entry->id)) {
                throw ValidationException::withMessages([$prefix.'attachment' => 'Unggah bukti untuk indikator ini.']);
            }
            $entry->update([
                'rows' => $rows,
                'no_activity' => $section['no_activity'],
                'explanation' => $section['explanation'],
                // Penanggung jawab berasal dari master penugasan dan tidak dapat diganti saat mengisi laporan.
                'responsible_name' => $entry->responsible_name,
            ]);
        }
    }

    public function transition(int $id, int $version, string $action, string $note): UnitReport
    {
        Gate::authorize('reporting-admin');

        return DB::transaction(function () use ($id, $version, $action, $note): UnitReport {
            $report = $this->locked($id, $version);
            abort_if($report->period->status === 'LOCKED', 403, 'Buka periode terlebih dahulu.');
            $from = $report->status;
            $isAllowedTransition = $from === 'SUBMITTED'
                && in_array($action, ['REVISION', 'VERIFIED'], true)
                || $from === 'VERIFIED'
                && $action === 'REVISION';

            abort_unless($isAllowedTransition, 403);

            Validator::make(
                ['note' => $note],
                ['note' => [$action === 'REVISION' ? 'required' : 'nullable', 'string', 'max:10000']]
            )->validate();
            if ($action === 'VERIFIED') {
                if ($report->notes()->whereNull('resolved_at')->exists()) {
                    throw ValidationException::withMessages(['note' => 'Selesaikan catatan verifikasi terbuka terlebih dahulu.']);
                }
                $sections = $report->entries
                    ->mapWithKeys(fn ($entry) => [
                        $entry->id => $entry->only([
                            'rows',
                            'no_activity',
                            'explanation',
                            'responsible_name',
                        ]),
                    ])
                    ->all();
                $this->validateSections($report, $sections, true);
            } else {
                $report->notes()->create(['user_id' => auth()->id(), 'note' => $note]);
            }
            $report->status = $action;
            $report->verified_by = $action === 'VERIFIED' ? auth()->id() : null;
            $report->verified_at = $action === 'VERIFIED' ? now() : null;
            $report->version++;
            $report->save();
            $this->history($report, $from, $note);

            return $report;
        });
    }

    public function history(UnitReport $report, ?string $from, ?string $note): void
    {
        $report->histories()->create([
            'user_id' => auth()->id(),
            'from_status' => $from,
            'to_status' => $report->status,
            'note' => $note,
            // Snapshot mempertahankan isi versi lama untuk kebutuhan audit.
            'snapshot' => [
                'version' => $report->version,
                'unit' => $report->unit->only(['id', 'code', 'name']),
                'entries' => $report->entries()->get()->toArray(),
                'attachments' => $report->attachments()->get()->toArray(),
            ],
            'created_at' => now(),
        ]);
    }

    public function completion(UnitReport $report): int
    {
        $entries = $report->entries->where('required', true);
        if ($entries->isEmpty()) {
            return 100;
        }
        $valid = 0;
        foreach ($entries as $entry) {
            try {
                $this->calculator->normalize($entry->definition, $entry->rows, true, $entry->no_activity);
                if (($entry->definition['requires_explanation'] || $entry->no_activity) && blank($entry->explanation)) {
                    continue;
                }
                if ($entry->definition['requires_attachment'] && ! $report->attachments->contains('entry_id', $entry->id)) {
                    continue;
                }
                $valid++;
            } catch (ValidationException) {
                continue;
            }
        }

        return (int) round(100 * $valid / $entries->count());
    }
}
