<div class="card p-3">
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <label for="report-period">Periode</label>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari periode..."
                aria-label="Cari pilihan periode laporan"
                data-select-search="report-period"
                autocomplete="off"
            >
            <select id="report-period" class="form-select" wire:model.live="periodId">
                <option value="">Semua periode</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->label }} — {{ ['DRAFT'=>'Draf','OPEN'=>'Dibuka','CLOSED'=>'Ditutup','LOCKED'=>'Dikunci'][$period->status] }}</option>
                @endforeach
            </select>
            @error('periodId')
                <div class="text-danger">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-md-4">
            <label for="report-status">Status</label>
            <select id="report-status" class="form-select" wire:model.live="status">
                <option value="">Semua status</option>
                @foreach(config('reporting.statuses') as $value=>$label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="report-search">Cari unit</label>
            <input id="report-search" class="form-control" wire:model.live.debounce.300ms="search">
        </div>
    </div>
    @if(auth()->user()->role === 'UNIT')
        <button class="btn btn-primary mb-3 align-self-start" wire:click="start" wire:loading.attr="disabled">Buat / lanjutkan laporan periode terpilih</button>
    @endif
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Unit</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th>Kelengkapan indikator wajib</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr wire:key="report-{{ $record->id }}">
                        <td>{{ $record->unit->name }}</td>
                        <td>{{ $record->period->label }}</td>
                        <td>{{ config('reporting.statuses')[$record->status] }}</td>
                        <td>{{ app(\App\Services\ReportWorkflow::class)->completion($record) }}%</td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show',$record) }}">Buka laporan</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada laporan sesuai pilihan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>{{ $records->links() }}
</div>
