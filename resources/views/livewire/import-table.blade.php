<div class="card p-3">
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif
    <label for="import-file">Workbook .xlsx (maksimal 10 MB)</label>
    <input id="import-file" type="file" class="form-control mb-3" wire:model="file" accept=".xlsx">
    <div class="row g-3">
        <div class="col-md-4">
            <label for="import-unit">Unit tujuan</label>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari unit..."
                aria-label="Cari pilihan unit tujuan"
                data-select-search="import-unit"
                autocomplete="off"
            >
            <select id="import-unit" class="form-select" wire:model.live="unitId">
                <option value="">Pilih</option>
                @foreach($units as $unit)
                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="import-period">Periode tujuan</label>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari periode..."
                aria-label="Cari pilihan periode tujuan"
                data-select-search="import-period"
                autocomplete="off"
            >
            <select id="import-period" class="form-select" wire:model.live="periodId">
                <option value="">Pilih</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}">{{ $period->label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="import-indicator">Indikator tujuan (harus sudah ditugaskan)</label>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari indikator..."
                aria-label="Cari pilihan indikator tujuan"
                data-select-search="import-indicator"
                autocomplete="off"
            >
            <select id="import-indicator" class="form-select" wire:model.live="indicatorId">
                <option value="">Pilih</option>
                @foreach($indicators as $indicator)
                    <option value="{{ $indicator->id }}">{{ $indicator->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="import-sheet">Sheet sumber</label>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari sheet..."
                aria-label="Cari pilihan sheet sumber"
                data-select-search="import-sheet"
                autocomplete="off"
            >
            <select id="import-sheet" class="form-select" wire:model="sheetName">
                <option value="">Pilih</option>
                @foreach($sheetNames as $name)
                    <option value="{{ $name }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label for="import-first">Baris pertama data</label>
            <input id="import-first" type="number" class="form-control" wire:model="firstRow">
        </div>
        <div class="col-md-4">
            <label for="import-last">Baris terakhir data</label>
            <input id="import-last" type="number" class="form-control" wire:model="lastRow">
        </div>
        <div class="col-md-4">
            <label for="import-label">Kolom uraian (contoh B)</label>
            <input id="import-label" class="form-control" wire:model="labelColumn">
        </div>
        @foreach($definition['fields'] ?? [] as $field)
            @if($field['type'] !== 'CALCULATED')
                <div class="col-md-4">
                    <label for="import-column-{{ $field['code'] }}">Kolom Excel untuk {{ $field['label'] }}</label>
                    <input id="import-column-{{ $field['code'] }}" class="form-control" placeholder="Contoh: C" wire:model="columns.{{ $field['code'] }}">
                </div>
            @endif
        @endforeach
    </div>
    <p class="text-muted mt-3">Untuk tabel tetap, jumlah dan urutan baris sumber harus sesuai butir formulir. Jangan sertakan header/subtotal. Nol tetap nol; sel kosong tetap belum diisi. Tanda “-” pada field angka harus ditinjau terlebih dahulu.</p>
    <button class="btn btn-outline-primary align-self-start mb-3" wire:click="preview" wire:loading.attr="disabled">Pratinjau dan validasi</button>
    @if($previewRows)
        <h5>Pratinjau {{ count($previewRows) }} baris</h5>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Uraian</th>
                        @foreach($definition['fields'] as $field)
                            <th>{{ $field['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($previewRows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            @foreach($definition['fields'] as $field)
                                <td>{{ $row['values'][$field['code']] ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary align-self-start" wire:click="save" wire:confirm="Impor hasil pratinjau sebagai draf?" wire:loading.attr="disabled">Impor draf</button>
    @endif
</div>
