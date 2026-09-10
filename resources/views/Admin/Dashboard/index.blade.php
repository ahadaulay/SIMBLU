@extends('Layout.Dashboard.master')

@section('title', 'Dashboard')

@section('content')
    <section class="dashboard-hero mb-4">
        <div class="dashboard-hero-content">
            <span class="dashboard-eyebrow">Sistem Informasi Gedung</span>
            <h2>Selamat datang, {{ auth()->user()->name }}</h2>
            <p>Pantau dan kelola informasi gedung dalam satu tempat yang ringkas.</p>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <a href="{{ route('gedungs.index') }}" class="btn btn-light dashboard-primary-action">
                    <i class="ti ti-building me-1"></i>Kelola Data Gedung
                </a>
                <a href="{{ route('profile.show') }}" class="btn dashboard-secondary-action">
                    <i class="ti ti-user me-1"></i>Lihat Profil
                </a>
            </div>
        </div>
        <div class="dashboard-hero-visual" aria-hidden="true">
            <div class="dashboard-building-icon"><i class="ti ti-building-community"></i></div>
            <span class="dashboard-orbit dashboard-orbit-one"></span>
            <span class="dashboard-orbit dashboard-orbit-two"></span>
        </div>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon stat-blue"><i class="ti ti-building"></i></div>
                    <p class="dashboard-stat-label">Total Gedung</p>
                    <div class="d-flex align-items-end justify-content-between gap-2">
                        <h3>{{ number_format($totalBuildings) }}</h3>
                        <span class="dashboard-stat-note">terdaftar</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon stat-green"><i class="ti ti-circle-check"></i></div>
                    <p class="dashboard-stat-label">Data Lengkap</p>
                    <div class="d-flex align-items-end justify-content-between gap-2">
                        <h3>{{ number_format($completeBuildings) }}</h3>
                        <span class="dashboard-stat-note">{{ $completionPercentage }}%</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon stat-orange"><i class="ti ti-alert-circle"></i></div>
                    <p class="dashboard-stat-label">Belum Ada Kode</p>
                    <div class="d-flex align-items-end justify-content-between gap-2">
                        <h3>{{ number_format($buildingsWithoutCode) }}</h3>
                        <span class="dashboard-stat-note">perlu dicek</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon stat-purple"><i class="ti ti-calendar-plus"></i></div>
                    <p class="dashboard-stat-label">Bulan Ini</p>
                    <div class="d-flex align-items-end justify-content-between gap-2">
                        <h3>{{ number_format($buildingsAddedThisMonth) }}</h3>
                        <span class="dashboard-stat-note">data baru</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card dashboard-panel h-100">
                <div class="card-body p-0">
                    <div class="dashboard-panel-header">
                        <div>
                            <h5>Gedung Terbaru</h5>
                            <p>Data yang terakhir ditambahkan atau diperbarui.</p>
                        </div>
                        <a href="{{ route('gedungs.index') }}" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>

                    @if ($recentBuildings->isEmpty())
                        <div class="dashboard-empty">
                            <span><i class="ti ti-building-plus"></i></span>
                            <h6>Belum ada data gedung</h6>
                            <p>Mulai tambahkan gedung pertama Anda.</p>
                            <a href="{{ route('gedungs.index') }}" class="btn btn-primary btn-sm">Tambah Gedung</a>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table dashboard-recent-table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Gedung</th>
                                        <th>Kode</th>
                                        <th>Diperbarui</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentBuildings as $building)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-3">
                                                    <span class="dashboard-row-icon"><i class="ti ti-building"></i></span>
                                                    <div>
                                                        <p class="fw-semibold text-dark mb-0">{{ $building->nama }}</p>
                                                        <small>{{ \Illuminate\Support\Str::limit($building->keterangan ?: 'Belum ada keterangan', 42) }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="building-code">{{ $building->kode ?: '—' }}</span></td>
                                            <td><span class="text-dark">{{ $building->updated_at?->diffForHumans() ?: '—' }}</span></td>
                                            <td class="text-end">
                                                <a href="{{ route('gedungs.show', $building) }}" class="btn btn-sm btn-light-primary text-primary fw-semibold">Detail</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card dashboard-panel mb-4">
                <div class="card-body">
                    <div class="dashboard-panel-header px-0 pt-0">
                        <div>
                            <h5>Kelengkapan Data</h5>
                            <p>Kode dan keterangan gedung.</p>
                        </div>
                    </div>
                    <div class="dashboard-progress-value">{{ $completionPercentage }}<small>%</small></div>
                    <div class="progress dashboard-progress" role="progressbar" aria-valuenow="{{ $completionPercentage }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: {{ $completionPercentage }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <span class="text-muted">{{ $completeBuildings }} lengkap</span>
                        <span class="text-dark fw-semibold">{{ max(0, $totalBuildings - $completeBuildings) }} perlu dilengkapi</span>
                    </div>
                </div>
            </div>

            <div class="card dashboard-quick-card">
                <div class="card-body">
                    <span class="dashboard-quick-icon"><i class="ti ti-bolt"></i></span>
                    <h5>Akses Cepat</h5>
                    <p>Kelola data utama tanpa langkah tambahan.</p>
                    <a href="{{ route('gedungs.index') }}" class="dashboard-quick-link">
                        <span><i class="ti ti-building me-2"></i>Data Gedung</span><i class="ti ti-chevron-right"></i>
                    </a>
                    <a href="{{ route('profile.show') }}" class="dashboard-quick-link">
                        <span><i class="ti ti-user me-2"></i>Profil Saya</span><i class="ti ti-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
