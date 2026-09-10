<div class="reporting-dashboard">
    @php
        $periodStatuses = [
            'DRAFT' => 'Draf',
            'OPEN' => 'Dibuka',
            'CLOSED' => 'Ditutup',
            'LOCKED' => 'Dikunci',
        ];

        $statusCards = [
            ['key' => 'MISSING', 'label' => 'Belum Melapor', 'icon' => 'ti-alert-triangle', 'tone' => 'red'],
            ['key' => 'DRAFT', 'label' => 'Draf', 'icon' => 'ti-pencil', 'tone' => 'blue'],
            ['key' => 'SUBMITTED', 'label' => 'Diajukan', 'icon' => 'ti-send', 'tone' => 'orange'],
            ['key' => 'REVISION', 'label' => 'Perlu Revisi', 'icon' => 'ti-refresh', 'tone' => 'purple'],
            ['key' => 'VERIFIED', 'label' => 'Terverifikasi', 'icon' => 'ti-circle-check', 'tone' => 'green'],
        ];
    @endphp

    <section class="reporting-hero mb-4">
        <div class="reporting-hero-content">
            <span class="reporting-hero-label">Sistem Pelaporan Kinerja Unit</span>
            <h2>Selamat datang, {{ auth()->user()->name }}</h2>
            <p>
                @if(auth()->user()->role === 'UNIT')
                    Isi laporan, pantau proses verifikasi, dan lihat rekap kinerja unit Anda.
                @else
                    Pantau pengisian, verifikasi laporan unit, dan susun rekap lintas periode.
                @endif
            </p>

            <div class="reporting-period-control mt-4">
                <label for="dashboard-period">Periode yang dipantau</label>
                <input
                    class="form-control select-search-input mb-2"
                    type="search"
                    placeholder="Cari periode..."
                    aria-label="Cari periode yang dipantau"
                    data-select-search="dashboard-period"
                    autocomplete="off"
                >
                <select id="dashboard-period" class="form-select" wire:model.live="periodId">
                    <option value="">Pilih periode</option>
                    @foreach($periods as $item)
                        <option value="{{ $item->id }}">{{ $item->label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="reporting-hero-visual" aria-hidden="true">
            <span class="reporting-hero-icon"><i class="ti ti-chart-dots-3"></i></span>
            <span class="reporting-decoration reporting-decoration-one"></span>
            <span class="reporting-decoration reporting-decoration-two"></span>
        </div>
    </section>

    @if($period)
        <div class="reporting-period-summary mb-4">
            <div>
                <span class="reporting-period-icon"><i class="ti ti-calendar-event"></i></span>
                <div>
                    <small>Periode aktif</small>
                    <strong>{{ $period->label }}</strong>
                </div>
            </div>
            <div>
                <small>Batas pengisian</small>
                <strong>{{ $period->deadline->format('d/m/Y') }}</strong>
            </div>
            <span class="reporting-period-badge status-{{ strtolower($period->status) }}">
                {{ $periodStatuses[$period->status] }}
            </span>
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach($statusCards as $card)
            @php
                $total = $card['key'] === 'MISSING'
                    ? $missing->count()
                    : ($counts[$card['key']] ?? 0);
            @endphp

            <div class="col-sm-6 col-xl">
                <div class="card reporting-stat-card reporting-stat-{{ $card['tone'] }} h-100">
                    <div class="card-body">
                        <span class="reporting-stat-icon">
                            <i class="ti {{ $card['icon'] }}"></i>
                        </span>
                        <div>
                            <strong>{{ number_format($total) }}</strong>
                            <p>{{ $card['label'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if($missing->isNotEmpty())
        <div class="alert reporting-alert" role="alert">
            <i class="ti ti-bell-ringing"></i>
            <div>
                <strong>Unit yang belum membuat laporan</strong>
                <p>{{ $missing->pluck('name')->join(', ') }}.</p>
            </div>
        </div>
    @endif

    <section class="card reporting-progress-card">
        <div class="reporting-card-header">
            <div>
                <span class="reporting-card-eyebrow">PROGRES PELAPORAN</span>
                <h5>Status laporan unit</h5>
                <p>Pantau kelengkapan data dan tindak lanjut setiap laporan.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.index') }}" class="btn btn-primary">
                    <i class="ti ti-clipboard-text me-1"></i>Daftar Laporan
                </a>
                <a href="{{ route('recaps.index') }}" class="btn btn-light-primary text-primary">
                    <i class="ti ti-file-analytics me-1"></i>Laporan Custom
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table reporting-progress-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Kelengkapan</th>
                        <th>Tenggat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        @php
                            $completion = app(\App\Services\ReportWorkflow::class)->completion($record);
                            $isLate = $period
                                && $period->deadline->copy()->endOfDay()->isPast()
                                && $record->status !== 'VERIFIED';
                        @endphp

                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <span class="reporting-unit-icon"><i class="ti ti-building"></i></span>
                                    <strong class="text-dark">{{ $record->unit->name }}</strong>
                                </div>
                            </td>
                            <td>
                                <span class="reporting-status status-{{ strtolower($record->status) }}">
                                    {{ config('reporting.statuses')[$record->status] }}
                                </span>
                            </td>
                            <td>
                                <div class="reporting-completion">
                                    <div class="progress">
                                        <div class="progress-bar" style="width: {{ $completion }}%"></div>
                                    </div>
                                    <strong>{{ $completion }}%</strong>
                                </div>
                            </td>
                            <td>
                                <span class="{{ $isLate ? 'text-danger fw-semibold' : 'text-muted' }}">
                                    {{ $isLate ? 'Melewati tenggat' : 'Sesuai periode' }}
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('reports.show', $record) }}" class="btn btn-sm btn-outline-primary">
                                    Buka
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="reporting-empty">
                                <i class="ti ti-clipboard-off"></i>
                                <strong>Belum ada laporan pada periode ini.</strong>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->hasPages())
            <div class="reporting-pagination">
                {{ $records->links() }}
            </div>
        @endif
    </section>
</div>
