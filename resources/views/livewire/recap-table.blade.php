<div>
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="alert alert-light border d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <strong>Contoh lengkap 2025–2026</strong>
            <p class="mb-0 text-muted">Workbook asli dengan seluruh sheet, baris, kolom, nilai, merge, formula, dan format.</p>
        </div>
        <a class="btn btn-success" href="{{ route('recaps.reference-workbook') }}">
            <i class="ti ti-file-spreadsheet me-1"></i>
            Unduh Contoh Persis
        </a>
    </div>
    <details class="card p-3">
        <summary>Ekspor latar belakang saya</summary>
        <button class="btn btn-sm btn-light align-self-start" wire:click="$refresh">Perbarui status</button>
        <ul>
            @forelse($exports as $export)
                <li>#{{ $export->id }} — {{ ['PENDING'=>'Menunggu worker','PROCESSING'=>'Diproses','READY'=>'Siap diunduh','FAILED'=>'Gagal, periksa cakupan dan coba lagi'][$export->status] }}
                    @if($export->status === 'READY')
                        <a href="{{ route('recaps.download',$export) }}">Unduh Excel</a>
                    @endif
                </li>
            @empty
                <li>Belum ada permintaan ekspor.</li>
            @endforelse
        </ul>
    </details>
    <div class="card p-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="recap-start">Awal rentang</label>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari periode awal..."
                    aria-label="Cari pilihan periode awal"
                    data-select-search="recap-start"
                    autocomplete="off"
                >
                <select id="recap-start" class="form-select" wire:model="startPeriod">
                    <option value="">Pilih</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label for="recap-end">Akhir rentang</label>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari periode akhir..."
                    aria-label="Cari pilihan periode akhir"
                    data-select-search="recap-end"
                    autocomplete="off"
                >
                <select id="recap-end" class="form-select" wire:model="endPeriod">
                    <option value="">Pilih</option>
                    @foreach($periods as $period)
                        <option value="{{ $period->id }}">{{ $period->label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-outline-primary" wire:click="selectRange">Pilih seluruh rentang</button>
            </div>
        </div>
        @if($errors->any())
            <div class="alert alert-danger mt-3">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        <div class="row g-3 mt-1">
            <fieldset class="col-md-4">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <legend class="form-label mb-0">Periode pilihan</legend>
                    <span class="badge bg-primary-subtle text-primary">{{ count($periodIds) }} dipilih</span>
                </div>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari periode..."
                    aria-label="Cari pilihan periode rekap"
                    data-checkbox-search="recap-period-options"
                    autocomplete="off"
                    wire:ignore
                >
                <div
                    id="recap-period-options"
                    class="recap-checkbox-list"
                    role="group"
                    aria-label="Daftar periode laporan"
                >
                    @foreach($periods as $period)
                        <label class="recap-checkbox-option" data-checkbox-option>
                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="{{ $period->id }}"
                                wire:model.live="periodIds"
                            >
                            <span>{{ $period->label }}</span>
                        </label>
                    @endforeach
                </div>
                <small class="text-muted">Boleh memilih periode yang tidak berurutan.</small>
            </fieldset>
            <fieldset class="col-md-4">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <legend class="form-label mb-0">Unit</legend>
                    <span class="badge bg-primary-subtle text-primary">
                        {{ count($unitIds) ?: 'Semua' }}
                    </span>
                </div>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari unit..."
                    aria-label="Cari pilihan unit rekap"
                    data-checkbox-search="recap-unit-options"
                    autocomplete="off"
                    wire:ignore
                >
                <div
                    id="recap-unit-options"
                    class="recap-checkbox-list"
                    role="group"
                    aria-label="Daftar unit"
                >
                    @foreach($units as $unit)
                        <label class="recap-checkbox-option" data-checkbox-option>
                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="{{ $unit->id }}"
                                wire:model.live="unitIds"
                            >
                            <span>{{ $unit->name }}</span>
                        </label>
                    @endforeach
                </div>
                <small class="text-muted">Tanpa centang berarti semua unit.</small>
            </fieldset>
            <fieldset class="col-md-4">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <legend class="form-label mb-0">Indikator</legend>
                    <span class="badge bg-primary-subtle text-primary">
                        {{ count($indicatorIds) ?: 'Semua' }}
                    </span>
                </div>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari indikator..."
                    aria-label="Cari pilihan indikator rekap"
                    data-checkbox-search="recap-indicator-options"
                    autocomplete="off"
                    wire:ignore
                >
                <div
                    id="recap-indicator-options"
                    class="recap-checkbox-list"
                    role="group"
                    aria-label="Daftar indikator"
                >
                    @foreach($indicators as $indicator)
                        <label class="recap-checkbox-option" data-checkbox-option>
                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="{{ $indicator->id }}"
                                wire:model.live="indicatorIds"
                            >
                            <span>{{ $indicator->name }}</span>
                        </label>
                    @endforeach
                </div>
                <small class="text-muted">Tanpa centang berarti semua indikator.</small>
            </fieldset>
        </div>
        <p class="text-muted mt-3">Centang setiap data yang ingin dimasukkan. Pilihan yang sudah dicentang tetap tersimpan saat daftar dicari.</p>
        <fieldset class="mb-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <legend class="form-label mb-0">Pemetaan rekap institusi</legend>
                <span class="badge bg-primary-subtle text-primary">
                    {{ count($metricIds) ?: 'Formulir asli' }}
                </span>
            </div>
            <input
                class="form-control select-search-input mb-2"
                type="search"
                placeholder="Cari pemetaan rekap..."
                aria-label="Cari pilihan pemetaan rekap"
                data-checkbox-search="recap-metric-options"
                autocomplete="off"
                wire:ignore
            >
            <div
                id="recap-metric-options"
                class="recap-checkbox-list recap-checkbox-list-wide"
                role="group"
                aria-label="Daftar pemetaan rekap institusi"
            >
                @forelse($metrics as $metric)
                    <label class="recap-checkbox-option" data-checkbox-option>
                        <input
                            class="form-check-input"
                            type="checkbox"
                            value="{{ $metric->id }}"
                            wire:model.live="metricIds"
                        >
                        <span>{{ $metric->name }}</span>
                    </label>
                @empty
                    <p class="mb-0 p-2 text-muted">Belum ada pemetaan rekap aktif.</p>
                @endforelse
            </div>
            <small class="text-muted">Tanpa centang menggunakan hasil formulir asli.</small>
        </fieldset>
        <label for="recap-group">Susunan laporan</label>
        <select id="recap-group" class="form-select" wire:model.live="grouping">
            <option value="PERIOD">Per periode pengisian</option>
            <option value="YEAR">Ringkasan per tahun</option>
            <option value="TOTAL">Gabungan seluruh periode pilihan</option>
        </select>
    </div>
    @if($result)
        <button class="btn btn-outline-primary mb-3" wire:click="queueExport" wire:loading.attr="disabled">Siapkan Excel di latar belakang</button>
        @if($result['incompatible'])
            <div class="alert alert-warning">Versi sumber berbeda dengan pemetaan sehingga tidak digabungkan: {{ implode('; ',$result['incompatible']) }}.</div>
        @endif
        <div class="d-flex flex-wrap gap-2 mb-3">
            <a class="btn btn-primary" href="{{ route('recaps.export') }}?{{ $query }}&format=reference">
                <i class="ti ti-file-spreadsheet me-1"></i>
                Unduh Laporan Baku
            </a>
            <a class="btn btn-outline-secondary" href="{{ route('recaps.export') }}?{{ $query }}">
                Unduh Excel Custom
            </a>
            <a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route('recaps.print') }}?{{ $query }}">Cetak / Simpan PDF</a>
        </div>
        <p>Periode: {{ implode(', ',$result['periods']) }} · Dibuat {{ $result['generated_at'] }}</p>
        <p class="text-muted">Pembanding menggunakan periode dan pola pengisian yang sama pada tahun sebelumnya. Definisi indikator harus sama. Pembanding nol tidak menghasilkan persentase perubahan.</p>
        <details class="card p-3">
            <summary>Perbandingan tahun sebelumnya</summary>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Unit / indikator</th>
                            <th>Periode</th>
                            <th>Tahun sebelumnya</th>
                            <th>Perubahan</th>
                            <th>Kinerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($result['rows'] as $row)
                            <tr>
                                <td>{{ $row['unit'] }} / {{ $row['indicator'] }} v{{ $row['version'] }}</td>
                                <td>{{ $row['group'] }}</td>
                                <td>{{ $row['previous_value'] === null ? 'Tidak tersedia' : number_format($row['previous_value'],4,',','.') }}</td>
                                <td>{{ $row['condition'] }} {{ $row['change_percent'] === null ? '' : '('.number_format($row['change_percent'],2).'%)' }}</td>
                                <td>{{ $row['performance'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
        @if($result['missing'])
            <div class="alert alert-warning">
                <strong>Rekap sementara:</strong> {{ count($result['missing']) }} penugasan belum memiliki laporan terverifikasi. Nilai yang belum tersedia tidak dianggap nol.<details>
                    <summary>Lihat cakupan yang belum tersedia</summary>
                    <ul>
                        @foreach($result['missing'] as $missing)
                            <li>{{ $missing['unit'] }} — {{ $missing['period'] }} — {{ $missing['indicator'] }}</li>
                        @endforeach
                    </ul>
                </details>
            </div>
        @endif
        <div class="card table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Indikator / versi</th>
                        <th>Periode</th>
                        <th>Metode</th>
                        <th>Hasil</th>
                        <th>Sumber</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($result['rows'] as $row)
                        <tr>
                            <td>{{ $row['unit'] }}</td>
                            <td>{{ $row['indicator'] }} / v{{ $row['version'] }}<small class="d-block">{{ $row['category'] }}</small>
                            </td>
                            <td>{{ $row['group'] }}<small class="d-block">{{ $row['period_count'] }} periode terverifikasi</small>
                            </td>
                            <td>{{ config('reporting.aggregations')[$row['definition']['aggregation']] }}
                                @if($row['definition']['aggregation'] === 'LAST')
                                    <small class="d-block">Posisi {{ $row['latest_period'] }}</small>
                                @endif
                            </td>
                            <td>{{ $row['value'] === null ? 'Tidak tersedia / lihat rincian' : number_format($row['value'], 4, ',', '.') }}</td>
                            <td>
                                <details>
                                    <summary>Lihat sumber</summary>
                                    @foreach($row['sources'] as $source)
                                        <a class="d-block" href="{{ route('reports.show',$source['id']) }}">{{ $source['period'] }}</a>
                                    @endforeach
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4">Belum ada data terverifikasi sesuai pilihan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="alert alert-info">Pilih periode untuk menampilkan rekap.</div>
    @endif
</div>
