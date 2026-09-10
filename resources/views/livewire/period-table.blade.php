<div class="building-manager">
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <section class="card building-table-card">
        <div class="building-table-heading">
            <h5>Periode Pelaporan</h5>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Periode Pelaporan</button>
        </div>
        <div class="building-toolbar">
            <label class="visually-hidden" for="period-search">Cari Periode Pelaporan</label>
            <input id="period-search" type="search" class="form-control" placeholder="Cari..." wire:model.live.debounce.300ms="search">
            <label for="period-per-page">Tampilkan</label>
            <select id="period-per-page" class="form-select w-auto" wire:model.live="perPage">
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
                        <th>
                            <button class="btn btn-link" wire:click="sortBy('year')">Periode</button>
                        </th>
                        <th>Batas pengisian</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="period-{{ $record->id }}">
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>{{ $record->label }}</td>
                            <td>{{ $record->deadline->format('d/m/Y') }}</td>
                            <td>{{ ['DRAFT'=>'Draf','OPEN'=>'Dibuka','CLOSED'=>'Ditutup','LOCKED'=>'Dikunci'][$record->status] }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        class="btn btn-sm btn-primary"
                                        href="{{ route('assignments.index', ['period' => $record->id, 'create' => 1]) }}"
                                    >
                                        <i class="ti ti-user-check me-1"></i>
                                        Tambah Penugasan
                                    </a>

                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('assignments.index', ['period' => $record->id]) }}"
                                    >
                                        Lihat Penugasan
                                    </a>

                                    <button
                                        class="btn btn-sm btn-outline-primary"
                                        wire:click="openEditModal({{ $record->id }})"
                                    >
                                        Ubah
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center p-4">Belum ada data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $records->links() }}</div>
    </section>
    <div class="modal fade" id="periodModal" tabindex="-1" aria-labelledby="periodModalTitle" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="periodModalTitle">{{ $editingId ? 'Ubah' : 'Tambah' }} Periode Pelaporan</h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="period-year">Tahun</label>
                            <input id="period-year" type="number" class="form-control" wire:model="form.year" >
                            @error('form.year')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="period-quarter">Triwulan</label>
                            <select id="period-quarter" class="form-select" wire:model="form.quarter">
                                <option value="1">I</option>
                                <option value="2">II</option>
                                <option value="3">III</option>
                                <option value="4">IV</option>
                            </select>
                            @error('form.quarter')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="period-deadline">Batas pengisian (peringatan keterlambatan)</label>
                            <input id="period-deadline" type="date" class="form-control" wire:model="form.deadline" >
                            @error('form.deadline')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="period-status">Status periode</label>
                            <select id="period-status" class="form-select" wire:model="form.status">
                                <option value="DRAFT">Draf</option>
                                <option value="OPEN">Dibuka</option>
                                <option value="CLOSED">Ditutup</option>
                                <option value="LOCKED">Dikunci</option>
                            </select>
                            @error('form.status')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="period-reason" class="form-label">Alasan perubahan status</label>
                            <textarea id="period-reason" class="form-control" wire:model="reason">
                            </textarea>
                            @error('reason')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary" type="submit" wire:loading.attr="disabled">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @script
        <script>
            $wire.on('open-period-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('periodModal')).show());
            $wire.on('close-period-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('periodModal')).hide());
        </script>
    @endscript
</div>
