<div class="building-manager">
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">
            {{ session('success') }}
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['1', 'Buat Periode Tahun', 'Pilih bulanan, triwulanan, atau semesteran.', 'ti-calendar-plus', 'primary'],
            ['2', 'Atur Penugasan', 'Pilih unit, indikator, dan penanggung jawab.', 'ti-user-check', 'info'],
            ['3', 'Isi & Verifikasi', 'Unit mengisi lalu admin memverifikasi laporan.', 'ti-clipboard-check', 'warning'],
            ['4', 'Unduh Laporan', 'Hasilkan Excel baku atau laporan custom.', 'ti-file-spreadsheet', 'success'],
        ] as [$number, $title, $description, $icon, $color])
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex gap-3">
                        <span class="rounded-circle bg-light-{{ $color }} text-{{ $color }} d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width: 42px; height: 42px">
                            <i class="ti {{ $icon }} fs-6"></i>
                        </span>
                        <div>
                            <small class="text-muted">Langkah {{ $number }}</small>
                            <h6 class="mb-1">{{ $title }}</h6>
                            <p class="small text-muted mb-0">{{ $description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="card building-table-card">
        <div class="building-table-heading">
            <div>
                <h5 class="mb-1">Periode Tahun</h5>
                <p class="mb-0 text-muted">
                    Buat tahun sekali, lalu sistem menyiapkan seluruh jadwal pengisian.
                </p>
            </div>

            <button class="btn btn-primary" wire:click="openCreateModal">
                <i class="ti ti-calendar-plus me-1"></i>
                Buat Periode Tahun
            </button>
        </div>

        <div class="building-toolbar">
            <label class="visually-hidden" for="cycle-search">Cari tahun</label>
            <input
                id="cycle-search"
                class="form-control"
                type="search"
                placeholder="Cari tahun..."
                wire:model.live.debounce.300ms="search"
            >

            <label for="cycle-per-page">Tampilkan</label>
            <select id="cycle-per-page" class="form-select w-auto" wire:model.live="perPage">
                <option>5</option>
                <option>10</option>
                <option>25</option>
            </select>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tahun</th>
                        <th>Pola pengisian</th>
                        <th>Jadwal</th>
                        <th>Status aktif</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cycles as $cycle)
                        <tr wire:key="cycle-{{ $cycle->id }}">
                            <td>{{ $cycles->firstItem() + $loop->index }}</td>
                            <td><strong>{{ $cycle->year }}</strong></td>
                            <td>{{ $cycle->frequency_label }}</td>
                            <td>{{ $cycle->periods_count }} periode pengisian</td>
                            <td>{{ $cycle->open_periods_count }} sedang dibuka</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        class="btn btn-sm btn-primary"
                                        wire:click="openDetail({{ $cycle->id }})"
                                    >
                                        <i class="ti ti-settings me-1"></i>
                                        Kelola Alur
                                    </button>

                                    @php
                                        $reportQuery = http_build_query([
                                            'periods' => $cycle->periods->pluck('id')->all(),
                                            'units' => [],
                                            'indicators' => [],
                                            'metrics' => [],
                                            'grouping' => 'PERIOD',
                                            'format' => 'reference',
                                        ]);
                                    @endphp

                                    <a
                                        class="btn btn-sm btn-outline-success"
                                        href="{{ route('recaps.export') }}?{{ $reportQuery }}"
                                    >
                                        <i class="ti ti-file-spreadsheet me-1"></i>
                                        Laporan Baku
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-4 text-center">
                                Belum ada periode tahun. Buat periode untuk memulai pelaporan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3">{{ $cycles->links() }}</div>
    </section>

    <div class="modal fade" id="cycleModal" tabindex="-1" aria-labelledby="cycle-title" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cycle-title">Buat Periode Tahun</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            Jadwal dan batas pengisian dibuat otomatis sampai akhir setiap bulan, triwulan, atau semester.
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="cycle-year">Tahun pelaporan</label>
                            <input id="cycle-year" class="form-control" type="number" wire:model="form.year">
                            @error('form.year')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="cycle-frequency">Pola pengisian</label>
                            <select id="cycle-frequency" class="form-select" wire:model="form.frequency">
                                <option value="MONTHLY">Setiap bulan (12 kali)</option>
                                <option value="QUARTERLY">Setiap triwulan (4 kali)</option>
                                <option value="SEMESTERLY">Setiap semester (2 kali)</option>
                            </select>
                            @error('form.frequency')<div class="text-danger">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                            Buat Periode
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cycleDetailModal" tabindex="-1" aria-labelledby="cycle-detail-title" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="cycle-detail-title">
                            Alur Pelaporan {{ $detailCycle?->year }}
                        </h5>
                        <p class="mb-0 text-muted">
                            {{ $detailCycle?->frequency_label }} · atur status, penugasan, lalu pantau laporan.
                        </p>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                @if($detailCycle)
                    <form wire:submit="saveStatuses">
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Periode pengisian</th>
                                            <th>Batas pengisian</th>
                                            <th>Status</th>
                                            <th>Langkah berikutnya</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($detailCycle->periods as $period)
                                            <tr wire:key="cycle-period-{{ $period->id }}">
                                                <td><strong>{{ $period->label }}</strong></td>
                                                <td>{{ $period->deadline->format('d/m/Y') }}</td>
                                                <td style="min-width: 170px">
                                                    <select class="form-select" wire:model="segmentStatuses.{{ $period->id }}">
                                                        <option value="DRAFT">Draf</option>
                                                        <option value="OPEN">Dibuka</option>
                                                        <option value="CLOSED">Ditutup</option>
                                                        <option value="LOCKED">Dikunci</option>
                                                    </select>
                                                    @error('segmentStatuses.'.$period->id)
                                                        <div class="text-danger">{{ $message }}</div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <a
                                                            class="btn btn-sm btn-outline-primary"
                                                            href="{{ route('assignments.index', ['period' => $period->id]) }}"
                                                        >
                                                            Atur Unit & Indikator
                                                        </a>
                                                        <a
                                                            class="btn btn-sm btn-outline-secondary"
                                                            href="{{ route('reports.index') }}"
                                                        >
                                                            Lihat Isian
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-light" type="button" data-bs-dismiss="modal">Tutup</button>
                            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">
                                Simpan Status
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @script
        <script>
            $wire.on('open-cycle-modal', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('cycleModal')).show();
            });

            $wire.on('close-cycle-modal', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('cycleModal')).hide();
            });

            $wire.on('open-cycle-detail-modal', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('cycleDetailModal')).show();
            });

            $wire.on('close-cycle-detail-modal', () => {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('cycleDetailModal')).hide();
            });
        </script>
    @endscript
</div>
