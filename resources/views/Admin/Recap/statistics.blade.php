@extends('Layout.Dashboard.master')

@section('title', 'Statistik Laporan Custom')

@section('content')
@php($formatNumber = fn ($value, $decimals = 4) => rtrim(rtrim(number_format((float) $value, $decimals, ',', '.'), '0'), ','))
<style>
    .stat-card {
        border: 0;
        box-shadow: 0 .25rem 1rem rgba(31, 78, 120, .08);
    }

    .stat-number {
        color: #1f4e78;
        font-size: 1.65rem;
        font-weight: 700;
    }

    .chart-row {
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 0;
    }

    .chart-track {
        background: #edf2f7;
        border-radius: 999px;
        height: 18px;
        overflow: hidden;
    }

    .chart-bar-current,
    .chart-bar-previous {
        border-radius: 999px;
        height: 100%;
        min-width: 2px;
    }

    .chart-bar-current { background: #1f78d1; }
    .chart-bar-previous { background: #9aa6b2; }

    .statistics-table thead th {
        background: #1f4e78;
        color: #fff;
        position: static;
    }
</style>

<div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
        <a class="small text-decoration-none" href="{{ route('recaps.index') }}">
            <i class="ti ti-arrow-left me-1"></i>Ubah pilihan
        </a>
        <h4 class="mt-2 mb-1">Statistik Laporan Custom</h4>
        <p class="text-muted mb-0">{{ implode(', ', $result['periods']) }} · diperbarui {{ $result['generated_at'] }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="{{ route('recaps.export') }}?{{ $query }}&format=reference">
            <i class="ti ti-file-spreadsheet me-1"></i>Unduh Laporan Baku
        </a>
        <a class="btn btn-outline-primary" href="{{ route('recaps.export') }}?{{ $query }}">
            <i class="ti ti-download me-1"></i>Unduh Excel Custom
        </a>
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('recaps.print') }}?{{ $query }}">
            <i class="ti ti-printer me-1"></i>Cetak / PDF
        </a>
    </div>
</div>

@if($result['incompatible'])
    <div class="alert alert-warning">
        Versi sumber berbeda sehingga tidak dapat digabungkan: {{ implode('; ', $result['incompatible']) }}.
    </div>
@endif

@if($result['missing'])
    <div class="alert alert-warning">
        <strong>Data belum lengkap:</strong> {{ count($result['missing']) }} penugasan belum memiliki laporan terverifikasi.
        <details class="mt-2">
            <summary>Lihat daftar yang belum tersedia</summary>
            <ul class="mb-0 mt-2">
                @foreach($result['missing'] as $missing)
                    <li>{{ $missing['unit'] }} — {{ $missing['period'] }} — {{ $missing['indicator'] }}</li>
                @endforeach
            </ul>
        </details>
    </div>
@endif

<section class="mb-4" aria-labelledby="summary-title">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <small class="text-primary fw-semibold">01 · RINGKASAN</small>
            <h5 id="summary-title" class="mb-0">Cakupan data pilihan</h5>
        </div>
    </div>
    <div class="row g-3">
        @foreach([
            ['label' => 'Periode', 'value' => $statistics['periods'], 'icon' => 'ti-calendar'],
            ['label' => 'Unit dengan data', 'value' => $statistics['units'], 'icon' => 'ti-building'],
            ['label' => 'Indikator', 'value' => $statistics['indicators'], 'icon' => 'ti-list-check'],
            ['label' => 'Laporan terverifikasi', 'value' => $statistics['sources'], 'icon' => 'ti-rosette-discount-check'],
            ['label' => 'Data belum lengkap', 'value' => $statistics['missing'], 'icon' => 'ti-alert-triangle'],
        ] as $item)
            <div class="col-6 col-lg">
                <div class="card stat-card h-100">
                    <div class="card-body">
                        <i class="ti {{ $item['icon'] }} text-primary fs-6"></i>
                        <div class="stat-number mt-2">{{ number_format($item['value'], 0, ',', '.') }}</div>
                        <small class="text-muted">{{ $item['label'] }}</small>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<div class="row g-4 mb-4">
    <section class="col-xl-8" aria-labelledby="comparison-chart-title">
        <div class="card stat-card h-100">
            <div class="card-body">
                <small class="text-primary fw-semibold">02 · GRAFIK PERBANDINGAN</small>
                <h5 id="comparison-chart-title">Nilai tahun berjalan dan tahun sebelumnya</h5>
                <p class="text-muted">Menampilkan maksimal 12 hasil angka terbesar dari pilihan Anda.</p>
                <div class="d-flex gap-3 small mb-2">
                    <span><span class="badge bg-primary">&nbsp;</span> Tahun berjalan</span>
                    <span><span class="badge bg-secondary">&nbsp;</span> Tahun sebelumnya</span>
                </div>

                @forelse($chartRows as $row)
                    @php($rowMaximum = max(1, abs((float) $row['value']), abs((float) ($row['previous_value'] ?? 0))))
                    @php($currentWidth = min(100, abs((float) $row['value']) / $rowMaximum * 100))
                    @php($previousWidth = min(100, abs((float) ($row['previous_value'] ?? 0)) / $rowMaximum * 100))
                    <div class="chart-row">
                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                            <strong>{{ $row['indicator'] }}</strong>
                            <small class="text-muted">{{ $row['unit'] }} · {{ $row['group'] }}</small>
                        </div>
                        <div class="row g-2 align-items-center small">
                            <div class="col-3 col-md-2">Berjalan</div>
                            <div class="col-6 col-md-8"><div class="chart-track"><div class="chart-bar-current" style="width: {{ $currentWidth }}%"></div></div></div>
                            <div class="col-3 col-md-2 text-end">{{ $formatNumber($row['value']) }}</div>
                            <div class="col-3 col-md-2">Sebelumnya</div>
                            <div class="col-6 col-md-8"><div class="chart-track"><div class="chart-bar-previous" style="width: {{ $previousWidth }}%"></div></div></div>
                            <div class="col-3 col-md-2 text-end">{{ $row['previous_value'] === null ? '—' : $formatNumber($row['previous_value']) }}</div>
                        </div>
                    </div>
                @empty
                    <div class="alert alert-info mb-0">Belum ada hasil angka yang dapat dibuat menjadi grafik.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="col-xl-4" aria-labelledby="condition-chart-title">
        <div class="card stat-card h-100">
            <div class="card-body">
                <small class="text-primary fw-semibold">03 · DISTRIBUSI</small>
                <h5 id="condition-chart-title">Kondisi dibanding tahun lalu</h5>
                <p class="text-muted">Jumlah hasil pada setiap kondisi.</p>
                @php($conditionTotal = max(1, $conditions->sum()))
                @foreach([
                    'NAIK' => ['label' => 'Naik', 'class' => 'bg-success'],
                    'SAMA' => ['label' => 'Sama', 'class' => 'bg-info'],
                    'TURUN' => ['label' => 'Turun', 'class' => 'bg-danger'],
                    'Tidak dapat dibandingkan' => ['label' => 'Belum dapat dibandingkan', 'class' => 'bg-secondary'],
                ] as $key => $condition)
                    @php($count = $conditions[$key])
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $condition['label'] }}</span>
                            <strong>{{ $count }}</strong>
                        </div>
                        <div class="progress" style="height:12px" role="progressbar" aria-valuenow="{{ $count }}" aria-valuemin="0" aria-valuemax="{{ $conditionTotal }}">
                            <div class="progress-bar {{ $condition['class'] }}" style="width: {{ $count / $conditionTotal * 100 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
</div>

<section class="card stat-card" aria-labelledby="data-table-title">
    <div class="card-body border-bottom">
        <small class="text-primary fw-semibold">04 · DATA LENGKAP</small>
        <h5 id="data-table-title" class="mb-1">Hasil berdasarkan pilihan</h5>
        <p class="text-muted mb-0">Pembanding memakai triwulan dan pola pengisian yang sama pada tahun sebelumnya.</p>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle statistics-table mb-0">
            <thead>
                <tr>
                    <th>Unit / indikator</th>
                    <th>Periode</th>
                    <th>Tahun sebelumnya</th>
                    <th>Tahun berjalan</th>
                    <th>Kondisi</th>
                    <th>Kinerja</th>
                    <th>Sumber</th>
                </tr>
            </thead>
            <tbody>
                @forelse($result['rows'] as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['indicator'] }}</strong>
                            <small class="d-block text-muted">{{ $row['unit'] }} · {{ $row['category'] }} · v{{ $row['version'] }}</small>
                        </td>
                        <td>{{ $row['group'] }}</td>
                        <td>{{ $row['previous_value'] === null ? 'Tidak tersedia' : $formatNumber($row['previous_value']) }}</td>
                        <td>{{ $row['value'] === null ? 'Lihat rincian' : $formatNumber($row['value']) }}</td>
                        <td>
                            <span class="badge {{ ['NAIK' => 'bg-success', 'SAMA' => 'bg-info', 'TURUN' => 'bg-danger'][$row['condition']] ?? 'bg-secondary' }}">
                                {{ $row['condition'] }}
                            </span>
                            @if($row['change_percent'] !== null)
                                <small class="d-block mt-1">{{ $formatNumber($row['change_percent'], 2) }}%</small>
                            @endif
                        </td>
                        <td>{{ $row['performance'] }}</td>
                        <td>
                            @foreach($row['sources'] as $source)
                                <a class="d-block" href="{{ route('reports.show', $source['id']) }}">{{ $source['period'] }}</a>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4 text-center text-muted">Belum ada laporan terverifikasi sesuai pilihan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
