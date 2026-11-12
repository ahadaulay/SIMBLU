<div @if($createOnLoad) wire:init="openContextCreateModal" @endif>
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif

    <section class="card building-table-card">
        <div class="building-table-heading">
            <div>
                <h5 class="mb-1">{{ $selectedPeriod ? 'Penugasan '.$selectedPeriod->label : 'Penugasan Unit' }}</h5>
                <p class="mb-0 text-muted">Setiap unit tampil satu kali. Indikator dan penanggung jawab dikelola melalui detail.</p>
            </div>
            <button class="btn btn-primary" wire:click="openCreateModal">
                <i class="ti ti-plus me-1"></i> Tambah Unit
            </button>
        </div>

        <div class="building-toolbar">
            <input class="form-control" type="search" aria-label="Cari unit" placeholder="Cari unit..." wire:model.live.debounce.300ms="search">
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Periode</th>
                        <th>Unit</th>
                        <th>Jumlah indikator</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="assignment-group-{{ $record->period_id }}-{{ $record->unit_id }}">
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>{{ $record->period->label }}</td>
                            <td><strong>{{ $record->unit->name }}</strong></td>
                            <td>{{ $record->indicators_count }} indikator</td>
                            <td>
                                <button class="btn btn-sm btn-primary" wire:click="openDetail({{ $record->period_id }}, {{ $record->unit_id }})">
                                    <i class="ti ti-list-details me-1"></i> Detail Indikator
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-center">Belum ada unit yang ditugaskan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $records->links() }}</div>
    </section>

    <div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignment-title" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="assignment-title">{{ $detailMode ? 'Indikator Unit' : 'Tambah Penugasan Unit' }}</h5>
                        @if($detailMode)
                            <p class="mb-0 text-muted">{{ $detailUnit?->name }} · {{ $detailPeriod?->label }}</p>
                        @endif
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    @if($errors->any())
                        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                    @endif

                    @if($detailMode)
                        <div class="table-responsive mb-4">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Kode</th><th>Indikator</th><th>Penanggung jawab</th><th>Kewajiban</th><th>Aksi</th></tr></thead>
                                <tbody>
                                    @foreach($detailAssignments as $assignment)
                                        <tr wire:key="assignment-detail-{{ $assignment->id }}">
                                            <td>{{ $assignment->indicator->code }}</td>
                                            <td>{{ $assignment->indicator->name }}</td>
                                            <td>{{ $assignment->responsible?->name ?? 'Belum ditentukan' }}</td>
                                            <td>{{ $assignment->required ? 'Wajib' : 'Opsional' }}</td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-outline-danger"
                                                    type="button"
                                                    wire:click="deleteIndicator({{ $assignment->id }})"
                                                    wire:confirm="Hapus indikator ini dari penugasan unit?"
                                                    wire:loading.attr="disabled"
                                                    wire:target="deleteIndicator({{ $assignment->id }})"
                                                >
                                                    <i class="ti ti-trash me-1"></i>
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <h6>Tambah Indikator</h6>
                    @endif

                    <form wire:submit="save">
                        @if(! $detailMode)
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="assignment-period">Periode</label>
                                    <input
                                        class="form-control select-search-input mb-2"
                                        type="search"
                                        placeholder="Cari periode..."
                                        aria-label="Cari pilihan periode"
                                        data-select-search="assignment-period"
                                        autocomplete="off"
                                    >
                                    <select id="assignment-period" class="form-select" wire:model="form.period_id">
                                        <option value="">Pilih periode</option>
                                        @foreach($periods as $period)<option value="{{ $period->id }}">{{ $period->label }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="assignment-unit">Unit</label>
                                    <input
                                        class="form-control select-search-input mb-2"
                                        type="search"
                                        placeholder="Cari unit..."
                                        aria-label="Cari pilihan unit"
                                        data-select-search="assignment-unit"
                                        autocomplete="off"
                                    >
                                    <select id="assignment-unit" class="form-select" wire:model.live="form.unit_id">
                                        <option value="">Pilih unit</option>
                                        @foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->name }}</option>@endforeach
                                    </select>
                                </div>
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="assignment-indicator">Indikator</label>
                                <input
                                    class="form-control select-search-input mb-2"
                                    type="search"
                                    placeholder="Cari kode atau nama indikator..."
                                    aria-label="Cari pilihan indikator"
                                    data-select-search="assignment-indicator"
                                    autocomplete="off"
                                >
                                <select id="assignment-indicator" class="form-select" wire:model="form.indicator_id">
                                    <option value="">Pilih indikator</option>
                                    @foreach($indicators as $indicator)<option value="{{ $indicator->id }}">{{ $indicator->code }} · {{ $indicator->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="assignment-responsible">Penanggung jawab</label>
                                <input
                                    class="form-control select-search-input mb-2"
                                    type="search"
                                    placeholder="Cari penanggung jawab..."
                                    aria-label="Cari pilihan penanggung jawab"
                                    data-select-search="assignment-responsible"
                                    autocomplete="off"
                                >
                                <select id="assignment-responsible" class="form-select" wire:model="form.responsible_user_id">
                                    <option value="">Pilih dari akun unit</option>
                                    @foreach($responsibleUsers as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                                </select>
                                @if($form['unit_id'] && $responsibleUsers->isEmpty())
                                    <small class="text-danger">Buat akun unit terlebih dahulu pada Master Unit Kerja.</small>
                                @endif
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input id="assignment-required" class="form-check-input" type="checkbox" wire:model="form.required">
                            <label class="form-check-label" for="assignment-required">Wajib diisi</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Tambah Indikator</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Tutup</button></div>
            </div>
        </div>
    </div>

    @script
        <script>
            $wire.on('open-assignment-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('assignmentModal')).show());
            $wire.on('close-assignment-modal', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('assignmentModal')).hide());
        </script>
    @endscript
</div>
