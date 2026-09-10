<div @if($createOnLoad) wire:init="openContextCreateModal" @endif>
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card p-3">
        <div class="d-flex justify-content-between mb-3">
            <div>
                <h5 class="mb-1">{{ $selectedPeriod ? 'Penugasan '.$selectedPeriod->label : 'Daftar Penugasan' }}</h5>

                @if($selectedPeriod)
                    <a class="small" href="{{ route('periods.index') }}">
                        <i class="ti ti-arrow-left"></i>
                        Kembali ke Periode Pelaporan
                    </a>
                @endif
            </div>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Penugasan</button>
        </div>
        <label for="assignment-search">Cari unit</label>
        <input id="assignment-search" class="form-control mb-3" wire:model.live.debounce.300ms="search">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Unit</th>
                        <th>Indikator</th>
                        <th>Kewajiban</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="assignment-{{ $record->id }}">
                            <td>{{ $record->period->label }}</td>
                            <td>{{ $record->unit->name }}</td>
                            <td>{{ $record->indicator->name }}</td>
                            <td>{{ $record->required ? 'Wajib' : 'Opsional' }}</td>
                            <td>
                                <button class="btn btn-outline-primary btn-sm" wire:click="openEditModal({{ $record->id }})">Ubah</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Belum ada penugasan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>{{ $records->links() }}</div>
        <div class="modal fade" id="assignmentModal" tabindex="-1" aria-labelledby="assignment-title" wire:ignore.self>
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="assignment-title">Penugasan Indikator</h5>
                        <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                        </button>
                    </div>
                    <form wire:submit="save">
                        <div class="modal-body">
                            @if($errors->any())
                                <div class="alert alert-danger">
                                    @foreach($errors->all() as $error)
                                        <p>{{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif
                            <label for="assignment-period">Periode</label>
                            <select id="assignment-period" class="form-select mb-3" wire:model="form.period_id">
                                <option value="">Pilih</option>
                                @foreach($periods as $period)
                                    <option value="{{ $period->id }}">{{ $period->label }}</option>
                                @endforeach
                            </select>
                            <label for="assignment-unit">Unit</label>
                            <select id="assignment-unit" class="form-select mb-3" wire:model="form.unit_id">
                                <option value="">Pilih</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            <label for="assignment-indicator">Indikator</label>
                            <select id="assignment-indicator" class="form-select mb-3" wire:model="form.indicator_id">
                                <option value="">Pilih</option>
                                @foreach($indicators as $indicator)
                                    <option value="{{ $indicator->id }}">{{ $indicator->name }}</option>
                                @endforeach
                            </select>
                            <label>
                                <input type="checkbox" wire:model="form.required"> Wajib diisi</label>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button>
                                <button class="btn btn-primary" wire:loading.attr="disabled">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            @script
                <script>
                    $wire.on('open-assignment-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('assignmentModal')).show());
                    $wire.on('close-assignment-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('assignmentModal')).hide());
                </script>
            @endscript
        </div>
