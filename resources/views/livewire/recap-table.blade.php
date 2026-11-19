<div>
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Pilihan belum dapat diproses.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <span class="rounded-circle bg-light-primary text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:46px;height:46px">
                    <i class="ti ti-chart-bar fs-6"></i>
                </span>
                <div>
                    <h5 class="mb-1">Buat laporan statistik</h5>
                    <p class="text-muted mb-0">Pilih periode dan cakupan data. Hasilnya akan dibuka di halaman statistik sebelum Anda mengunduh Excel atau PDF.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <small class="text-primary fw-semibold">01 · RENTANG WAKTU</small>
            <h5 class="mb-0">Pilih periode laporan</h5>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <label class="form-label" for="recap-start">Periode awal</label>
                    <input class="form-control select-search-input mb-2" type="search" placeholder="Cari periode awal..." aria-label="Cari pilihan periode awal" data-select-search="recap-start" autocomplete="off">
                    <select id="recap-start" class="form-select" wire:model="startPeriod">
                        <option value="">Pilih periode awal</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}">{{ $period->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="recap-end">Periode akhir</label>
                    <input class="form-control select-search-input mb-2" type="search" placeholder="Cari periode akhir..." aria-label="Cari pilihan periode akhir" data-select-search="recap-end" autocomplete="off">
                    <select id="recap-end" class="form-select" wire:model="endPeriod">
                        <option value="">Pilih periode akhir</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->id }}">{{ $period->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100" type="button" wire:click="selectRange">Gunakan rentang</button>
                </div>
            </div>

            <fieldset>
                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <legend class="form-label mb-0">Periode yang dimasukkan</legend>
                    <span class="badge bg-primary-subtle text-primary">{{ count($periodIds) }} dipilih</span>
                </div>
                <input class="form-control select-search-input mb-2" type="search" placeholder="Cari periode..." aria-label="Cari periode laporan" data-checkbox-search="recap-period-options" autocomplete="off" wire:ignore>
                <div id="recap-period-options" class="recap-checkbox-list recap-checkbox-list-wide" role="group" aria-label="Daftar periode laporan">
                    @foreach($periods as $period)
                        <label class="recap-checkbox-option" data-checkbox-option>
                            <input class="form-check-input" type="checkbox" value="{{ $period->id }}" wire:model.live="periodIds">
                            <span>{{ $period->label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <small class="text-primary fw-semibold">02 · CAKUPAN DATA</small>
            <h5 class="mb-0">Tentukan unit dan indikator</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <fieldset class="col-lg-6">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <legend class="form-label mb-0">Unit</legend>
                        <span class="badge bg-primary-subtle text-primary">{{ count($unitIds) ?: 'Semua unit' }}</span>
                    </div>
                    <input class="form-control select-search-input mb-2" type="search" placeholder="Cari unit..." aria-label="Cari unit" data-checkbox-search="recap-unit-options" autocomplete="off" wire:ignore>
                    <div id="recap-unit-options" class="recap-checkbox-list" role="group" aria-label="Daftar unit">
                        @foreach($units as $unit)
                            <label class="recap-checkbox-option" data-checkbox-option>
                                <input class="form-check-input" type="checkbox" value="{{ $unit->id }}" wire:model.live="unitIds">
                                <span>{{ $unit->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="text-muted">Tanpa pilihan berarti semua unit yang dapat Anda akses.</small>
                </fieldset>

                <fieldset class="col-lg-6">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <legend class="form-label mb-0">Indikator</legend>
                        <span class="badge bg-primary-subtle text-primary">{{ count($indicatorIds) ?: 'Semua indikator' }}</span>
                    </div>
                    <input class="form-control select-search-input mb-2" type="search" placeholder="Cari indikator..." aria-label="Cari indikator" data-checkbox-search="recap-indicator-options" autocomplete="off" wire:ignore>
                    <div id="recap-indicator-options" class="recap-checkbox-list" role="group" aria-label="Daftar indikator">
                        @foreach($indicators as $indicator)
                            <label class="recap-checkbox-option" data-checkbox-option>
                                <input class="form-check-input" type="checkbox" value="{{ $indicator->id }}" wire:model.live="indicatorIds">
                                <span>{{ $indicator->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <small class="text-muted">Tanpa pilihan berarti semua indikator.</small>
                </fieldset>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom">
            <small class="text-primary fw-semibold">03 · SUSUNAN</small>
            <h5 class="mb-0">Atur cara membaca hasil</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <fieldset class="col-lg-8">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                        <legend class="form-label mb-0">Pemetaan rekap institusi</legend>
                        <span class="badge bg-primary-subtle text-primary">{{ count($metricIds) ?: 'Formulir asli' }}</span>
                    </div>
                    <input class="form-control select-search-input mb-2" type="search" placeholder="Cari pemetaan rekap..." aria-label="Cari pemetaan rekap" data-checkbox-search="recap-metric-options" autocomplete="off" wire:ignore>
                    <div id="recap-metric-options" class="recap-checkbox-list recap-checkbox-list-wide" role="group" aria-label="Daftar pemetaan rekap institusi">
                        @forelse($metrics as $metric)
                            <label class="recap-checkbox-option" data-checkbox-option>
                                <input class="form-check-input" type="checkbox" value="{{ $metric->id }}" wire:model.live="metricIds">
                                <span>{{ $metric->name }}</span>
                            </label>
                        @empty
                            <p class="mb-0 p-2 text-muted">Belum ada pemetaan rekap aktif.</p>
                        @endforelse
                    </div>
                </fieldset>
                <div class="col-lg-4">
                    <label class="form-label" for="recap-group">Pengelompokan hasil</label>
                    <select id="recap-group" class="form-select" wire:model="grouping">
                        <option value="PERIOD">Per periode pengisian</option>
                        <option value="YEAR">Ringkasan per tahun</option>
                        <option value="TOTAL">Gabungan seluruh periode</option>
                    </select>
                    <p class="small text-muted mt-2">Pilih “Per periode” untuk melihat perbandingan setiap triwulan secara terpisah.</p>
                </div>
            </div>
        </div>
        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-3">
            <small class="text-muted">Hanya laporan terverifikasi yang menjadi sumber statistik.</small>
            <button class="btn btn-primary" type="button" wire:click="showStatistics" wire:loading.attr="disabled">
                <i class="ti ti-chart-bar me-1"></i>Tampilkan Statistik
            </button>
        </div>
    </div>

    <details class="card border-0 shadow-sm">
        <summary class="card-body fw-semibold">Ekspor latar belakang saya</summary>
        <div class="card-body border-top">
            <button class="btn btn-sm btn-light mb-3" type="button" wire:click="$refresh">Perbarui status</button>
            <ul class="mb-0">
                @forelse($exports as $export)
                    <li>
                        #{{ $export->id }} — {{ ['PENDING' => 'Menunggu giliran', 'PROCESSING' => 'Sedang membuat file', 'READY' => 'Siap diunduh', 'FAILED' => 'File rekap gagal dibuat. Coba buat ulang. Jika tetap gagal, sampaikan nomor ekspor ini ke admin SIBLU.'][$export->status] }}
                        @if($export->status === 'READY')
                            <a href="{{ route('recaps.download', $export) }}">Unduh Excel</a>
                        @endif
                    </li>
                @empty
                    <li>Belum ada permintaan ekspor.</li>
                @endforelse
            </ul>
        </div>
    </details>
</div>
