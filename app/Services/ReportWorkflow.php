<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\IndicatorAssignment;
use App\Models\ReportEntry;
use App\Models\ReportingPeriod;
use App\Models\UnitReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
                'periodId' => 'Unit ini belum memiliki indikator pada periode yang dipilih. Hubungi admin SIBLU untuk mengatur penugasan.',
            ]);
        }

        $report = UnitReport::create([
            'unit_id' => $unitId,
            'period_id' => $periodId,
            'created_by' => $creatorId,
        ]);

        foreach ($assignments as $assignment) {
            $definition = $assignment->definition ?? $assignment->indicator->definition();
            // Definisi indikator disalin agar laporan lama tidak berubah saat master diedit.
            $report->entries()->create([
                'indicator_id' => $assignment->indicator_id,
                'definition' => $definition,
                'required' => $assignment->required,
                'rows' => $this->blankRows($definition),
                'responsible_name' => $assignment->responsible?->name,
            ]);
        }

        $this->history($report, null, 'Laporan dibuat.');

        return $report;
    }

    private function blankRows(array $definition): array
    {
        $items = match ($definition['form_type']) {
            'SINGLE' => [['code' => 'single', 'label' => $definition['name']]],
            'FIXED' => $definition['items'],
            default => [],
        };

        return array_map(fn ($item) => [
            'key' => $item['code'],
            'label' => $item['label'],
            'values' => array_fill_keys(array_column($definition['fields'], 'code'), null),
            'details' => [],
        ], $items);
    }

    public function updateAssignedIndicator(int $periodId, int $unitId, int $assignmentId, ?string $confirmation = null): ?array
    {
        Gate::authorize('reporting-admin');

        return DB::transaction(function () use ($periodId, $unitId, $assignmentId, $confirmation): ?array {
            $period = ReportingPeriod::lockForUpdate()->findOrFail($periodId);
            $assignment = IndicatorAssignment::with('indicator.category')
                ->where('period_id', $periodId)->where('unit_id', $unitId)
                ->lockForUpdate()->find($assignmentId);
            if (! $assignment) {
                throw ValidationException::withMessages([
                    'updateIndicator' => 'Indikator tidak ditemukan pada penugasan unit dan periode ini.',
                ]);
            }

            $report = UnitReport::where('period_id', $periodId)->where('unit_id', $unitId)->lockForUpdate()->first();

            if (! in_array($period->status, ['DRAFT', 'OPEN'], true)
                || ($report && ! in_array($report->status, ['DRAFT', 'REVISION'], true))) {
                throw ValidationException::withMessages([
                    'updateIndicator' => 'Indikator hanya dapat diperbarui sebelum periode ditutup, pada laporan draf atau revisi.',
                ]);
            }

            $definition = $assignment->indicator->definition();
            if ($report) {
                $entry = $report->entries()->where('indicator_id', $assignment->indicator_id)->firstOrFail();
                $fingerprint = hash('sha256', serialize([$entry->id, $report->version, $definition]));
                if ($confirmation !== null && ! hash_equals($fingerprint, $confirmation)) {
                    throw ValidationException::withMessages([
                        'updateIndicator' => 'Laporan atau definisi berubah. Klik Update lagi untuk memeriksa perubahan terbaru.',
                    ]);
                }
                if ($entry->definition != $definition) {
                    $overwrite = false;
                    try {
                        $rows = $this->rowsForUpdatedIndicator($entry, $definition);
                    } catch (ValidationException $exception) {
                        if ($confirmation === null) {
                            return ['confirmation' => $fingerprint, 'message' => $exception->getMessage()];
                        }

                        $this->history($report, $report->status, 'Salinan laporan sebelum isian indikator '.$entry->definition['code'].' ditimpa.');
                        $rows = $this->blankRows($definition);
                        $overwrite = true;
                    }
                    $changes = $this->indicatorChanges($entry->definition, $definition);
                    $entry->update([
                        'definition' => $definition,
                        'rows' => $rows,
                        'source_entry_id' => null,
                        'no_activity' => ! $overwrite && $entry->effectiveNoActivity(),
                    ]);
                    $report->increment('version');
                    $this->history($report, $report->status, 'Definisi indikator '.$definition['code'].' diperbarui dari master. '.$changes);
                }
            }

            $assignment->update(['definition' => $definition]);

            return null;
        });
    }

    private function rowsForUpdatedIndicator(ReportEntry $entry, array $definition): array
    {
        $old = $entry->definition;
        $rows = $entry->effectiveRows();
        $hasData = $entry->effectiveNoActivity() || collect($rows)->contains(
            fn (array $row) => ($row['details'] ?? []) !== []
                || ($old['form_type'] === 'DYNAMIC' && filled($row['label'] ?? null))
                || collect($row['values'])->contains(fn ($value) => $value !== null && $value !== '')
        );

        if (! $hasData) {
            return $this->blankRows($definition);
        }

        $expandingSingle = $old['form_type'] === 'SINGLE' && $definition['form_type'] === 'FIXED';
        if ($old['form_type'] !== $definition['form_type'] && ! $expandingSingle) {
            throw ValidationException::withMessages([
                'updateIndicator' => 'Struktur baru tidak kompatibel dengan isian indikator yang sudah tersimpan.',
            ]);
        }

        if ($definition['form_type'] === 'FIXED') {
            $oldItems = collect($old['items'])->keyBy(Indicator::partIdentity(...));
            $existing = collect($rows)->keyBy('key');
            if ($expandingSingle) {
                $items = collect($definition['items']);
                $matches = $items->whereStrict('label', $old['name']);
                $target = $items->firstWhere('code', 'single') ?? ($matches->count() === 1 ? $matches->first() : null);
                if (! $target) {
                    throw ValidationException::withMessages([
                        'updateIndicator' => 'Untuk membawa isian dari Satu Baris ke Tabel Tetap, gunakan kode single pada butir yang akan menerima isian lama.',
                    ]);
                }
                $oldItems = collect([Indicator::partIdentity($target) => ['code' => 'single']]);
            }
            $rows = array_map(function (array $row, array $item) use ($oldItems, $existing): array {
                $oldItem = $oldItems->get(Indicator::partIdentity($item));
                $saved = $oldItem ? $existing->get($oldItem['code']) : null;

                return $saved ? array_replace($saved, ['key' => $row['key'], 'label' => $row['label']]) : $row;
            }, $this->blankRows($definition), $definition['items']);
        }

        $oldFields = collect($old['fields'])->keyBy(Indicator::partIdentity(...));
        foreach ($rows as &$row) {
            $values = [];
            foreach ($definition['fields'] as $field) {
                $previous = $oldFields->get(Indicator::partIdentity($field));
                $value = $previous ? ($row['values'][$previous['code']] ?? null) : null;
                if ($value !== null && $value !== '' && $previous['type'] !== $field['type']) {
                    throw ValidationException::withMessages([
                        'updateIndicator' => 'Tipe kolom '.$field['code'].' berubah dan kolom sudah berisi data. Buat kolom baru jika isinya harus diganti.',
                    ]);
                }
                $values[$field['code']] = $value;
            }
            $row['values'] = $values;
        }
        unset($row);

        try {
            $normalized = $this->calculator->normalize($definition, $rows, false, $entry->effectiveNoActivity());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'updateIndicator' => 'Isian indikator tidak memenuhi definisi baru. '.$exception->getMessage(),
            ]);
        }
        foreach ($rows as $index => $row) {
            foreach ($definition['fields'] as $field) {
                $code = $field['code'];
                $value = $row['values'][$code] ?? null;
                $newValue = $normalized[$index]['values'][$code] ?? null;
                if ($field['type'] !== 'CALCULATED' && $value !== null && $value !== ''
                    && ($newValue === null || $value != $newValue)) {
                    throw ValidationException::withMessages([
                        'updateIndicator' => 'Definisi baru akan mengubah nilai isian yang sudah tersimpan.',
                    ]);
                }
            }
        }

        return $normalized;
    }

    private function indicatorChanges(array $old, array $definition): string
    {
        $changes = [];
        foreach (['items' => 'Baris', 'fields' => 'Kolom'] as $key => $label) {
            $before = collect($old[$key])->keyBy(Indicator::partIdentity(...));
            $after = collect($definition[$key])->keyBy(Indicator::partIdentity(...));

            foreach ($before as $identity => $part) {
                $current = $after->get($identity);
                if (! $current) {
                    $changes[] = "$label dihapus: {$part['code']}";
                } elseif ($part['code'] !== $current['code']) {
                    $changes[] = "$label diubah: {$part['code']} → {$current['code']}";
                } elseif (array_diff_key($part, ['identity' => true]) != array_diff_key($current, ['identity' => true])) {
                    $changes[] = "$label diubah: {$part['code']}";
                }
            }

            foreach ($after->diffKeys($before) as $part) {
                $changes[] = "$label ditambah: {$part['code']}";
            }
        }

        return implode("\n", $changes);
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
            $effectiveRows = $entry->effectiveRows();
            $alreadyFilled = collect($effectiveRows)->contains(
                fn ($row) => collect($row['values'])->contains(
                    fn ($value) => $value !== null && $value !== ''
                )
            );

            if ($entry->source_entry_id || $alreadyFilled || $entry->effectiveNoActivity()) {
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

        abort_unless($isUnitUser, 403);

        if (! $hasEditableStatus) {
            throw ValidationException::withMessages([
                'report' => 'Laporan tidak dapat diubah karena statusnya sudah '.strtolower(config('reporting.statuses')[$report->status] ?? $report->status).'.',
            ]);
        }

        if (! $isPeriodOpen) {
            throw ValidationException::withMessages([
                'report' => 'Laporan tidak dapat diubah karena periode pengisian belum dibuka atau sudah ditutup.',
            ]);
        }
    }

    public function previousContext(UnitReport $report): array
    {
        $report->loadMissing('period.cycle');

        if ($report->period->cycle?->frequency !== 'QUARTERLY') {
            return ['period' => null, 'entries' => collect()];
        }

        $quarter = $report->period->quarter === 1 ? 4 : $report->period->quarter - 1;
        $year = $report->period->quarter === 1 ? $report->period->year - 1 : $report->period->year;
        $period = ReportingPeriod::with('cycle')
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->whereHas('cycle', fn ($query) => $query->where('frequency', 'QUARTERLY'))
            ->first();

        if (! $period) {
            return ['period' => null, 'entries' => collect()];
        }

        $previousReport = UnitReport::with('entries.sourceEntry')
            ->where('unit_id', $report->unit_id)
            ->where('period_id', $period->id)
            ->first();

        return [
            'period' => $period,
            'entries' => $previousReport?->entries->keyBy('indicator_id') ?? collect(),
        ];
    }

    public function canUsePrevious(ReportEntry $entry, ?ReportEntry $previousEntry): bool
    {
        // The definition version pins the same indicator items, so only compatible filled data may be copied.
        if (! $previousEntry || $previousEntry->definition['version'] !== $entry->definition['version']) {
            return false;
        }

        return collect($previousEntry->effectiveRows())->contains(
            fn ($row) => collect($row['values'] ?? [])->contains(
                fn ($value) => $value !== null && $value !== ''
            )
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

        $previousEntries = $this->previousContext($report)['entries'];

        foreach ($report->entries as $entry) {
            $section = $sections[$entry->id];
            $prefix = 'sections.'.$entry->id.'.';

            try {
                Validator::make($section, [
                    'rows' => ['present', 'array'],
                    'no_activity' => ['required', 'boolean'],
                    'use_previous' => ['required', 'boolean'],
                    'explanation' => ['nullable', 'string', 'max:10000'],
                    'responsible_name' => ['nullable', 'string', 'max:150'],
                ], [], [
                    'rows' => 'daftar butir',
                    'no_activity' => 'pilihan tidak ada kegiatan',
                    'use_previous' => 'pilihan data triwulan sebelumnya',
                    'explanation' => 'penjelasan',
                    'responsible_name' => 'nama penanggung jawab',
                ])->validate();
                $sourceEntry = null;
                $rowsToValidate = $section['rows'];
                $noActivity = (bool) $section['no_activity'];

                if ($section['use_previous']) {
                    $sourceEntry = $previousEntries->get($entry->indicator_id);
                    $isExistingCopy = $sourceEntry && $entry->source_entry_id === $sourceEntry->id;

                    if ((! $isExistingCopy && ! $this->canUsePrevious($entry, $sourceEntry))
                        || $sourceEntry?->definition['version'] !== $entry->definition['version']) {
                        throw ValidationException::withMessages([
                            'use_previous' => 'Data indikator pada triwulan sebelumnya belum diisi, tidak tersedia, atau versinya berbeda.',
                        ]);
                    }
                }

                $hasValues = collect($rowsToValidate)->contains(
                    fn ($row) => collect($row['values'] ?? [])->contains(
                        fn ($value) => $value !== null && $value !== ''
                    )
                );
                $needed = $complete && ($entry->required || $hasValues || $noActivity);
                $rows = $this->calculator->normalize(
                    $entry->definition,
                    $rowsToValidate,
                    $needed,
                    $noActivity,
                );
            } catch (ValidationException $e) {
                throw $this->contextualValidation($entry, $section, $prefix, $e);
            }
            if ($needed && ($entry->definition['requires_explanation'] || $noActivity) && blank($section['explanation'])) {
                throw ValidationException::withMessages([
                    $prefix.'explanation' => $this->entryContext($entry, $section).' Isi penjelasan, termasuk alasan tidak ada kegiatan.',
                ]);
            }
            if ($needed && $entry->definition['requires_attachment'] && ! $report->attachments->contains('entry_id', $entry->id)) {
                throw ValidationException::withMessages([
                    $prefix.'attachment' => $this->entryContext($entry, $section).' Unggah bukti pendukung untuk indikator ini.',
                ]);
            }
            $entry->update([
                'source_entry_id' => $sourceEntry?->id,
                'rows' => $rows,
                'no_activity' => $noActivity,
                'explanation' => $section['explanation'],
                // Penanggung jawab berasal dari master penugasan dan tidak dapat diganti saat mengisi laporan.
                'responsible_name' => $entry->responsible_name,
            ]);
        }
    }

    private function contextualValidation(ReportEntry $entry, array $section, string $prefix, ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $key => $errors) {
            preg_match('/^rows\.(\d+)/', $key, $matches);
            $rowIndex = isset($matches[1]) ? (int) $matches[1] : null;
            $context = $this->entryContext($entry, $section, $rowIndex);
            $messages[$prefix.$key] = array_map(
                fn (string $message): string => $context.' '.$message,
                $errors,
            );
        }

        return ValidationException::withMessages($messages);
    }

    private function entryContext(ReportEntry $entry, array $section, ?int $rowIndex = null): string
    {
        $context = 'Indikator “'.$entry->definition['name'].'”';

        if ($rowIndex !== null) {
            $label = trim((string) ($section['rows'][$rowIndex]['label'] ?? ''));
            $context .= ', butir “'.($label !== '' ? $label : 'baris ke-'.($rowIndex + 1)).'”:';
        } else {
            $context .= ':';
        }

        return $context;
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
                        $entry->id => [
                            'rows' => $entry->effectiveRows(),
                            'no_activity' => $entry->effectiveNoActivity(),
                            'use_previous' => $entry->source_entry_id !== null,
                            'explanation' => $entry->explanation,
                            'responsible_name' => $entry->responsible_name,
                        ],
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

    public function resetReport(int $id, int $version): UnitReport
    {
        Gate::authorize('reporting-admin');
        $attachmentPaths = [];

        $report = DB::transaction(function () use ($id, $version, &$attachmentPaths): UnitReport {
            $report = $this->locked($id, $version);
            abort_if($report->period->status === 'LOCKED', 403, 'Buka periode terlebih dahulu sebelum mereset laporan.');
            abort_unless(
                in_array($report->status, ['SUBMITTED', 'VERIFIED'], true),
                403,
                'Hanya laporan yang diajukan atau sudah diverifikasi yang dapat direset.',
            );

            $from = $report->status;
            $attachmentPaths = $report->attachments->pluck('path')->all();

            foreach ($report->entries as $entry) {
                $entry->update([
                    'source_entry_id' => null,
                    'rows' => $this->blankRows($entry->definition),
                    'no_activity' => false,
                    'explanation' => null,
                ]);
            }

            $report->attachments()->delete();
            $report->notes()->delete();
            $report->update([
                'status' => 'DRAFT',
                'submitted_at' => null,
                'verified_by' => null,
                'verified_at' => null,
                'version' => $report->version + 1,
            ]);
            $this->history($report, $from, 'Laporan direset oleh admin dan dikembalikan menjadi draf kosong.');

            return $report;
        });

        if ($attachmentPaths !== []) {
            Storage::disk('local')->delete($attachmentPaths);
        }

        return $report;
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
                'entries' => $report->entries()->with('sourceEntry')->get()->map(function (ReportEntry $entry): array {
                    $snapshot = $entry->toArray();
                    $snapshot['rows'] = $entry->effectiveRows();
                    $snapshot['no_activity'] = $entry->effectiveNoActivity();

                    return $snapshot;
                })->all(),
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
                $noActivity = $entry->effectiveNoActivity();
                $this->calculator->normalize($entry->definition, $entry->effectiveRows(), true, $noActivity);
                if (($entry->definition['requires_explanation'] || $noActivity) && blank($entry->explanation)) {
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
