<div class="building-manager">
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <section class="card building-table-card">
        <div class="building-table-heading">
            <h5>Unit Kerja</h5>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Unit Kerja</button>
        </div>
        <div class="building-toolbar">
            <label class="visually-hidden" for="unit-search">Cari Unit Kerja</label>
            <input id="unit-search" type="search" class="form-control" placeholder="Cari..." wire:model.live.debounce.300ms="search">
            <label for="unit-per-page">Tampilkan</label>
            <select id="unit-per-page" class="form-select w-auto" wire:model.live="perPage">
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
                            <button class="btn btn-link" wire:click="sortBy('name')">Nama</button>
                        </th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="unit-{{ $record->id }}">
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        class="btn btn-sm btn-primary"
                                        href="{{ route('users.index', ['unit' => $record->id, 'create' => 1]) }}"
                                    >
                                        <i class="ti ti-user-plus me-1"></i>
                                        Tambah Akun
                                    </a>

                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('users.index', ['unit' => $record->id]) }}"
                                    >
                                        <i class="ti ti-users me-1"></i>
                                        Lihat Akun
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
    <div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalTitle" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="unitModalTitle">{{ $editingId ? 'Ubah' : 'Tambah' }} Unit Kerja</h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="unit-code">Kode</label>
                            <input id="unit-code" type="text" class="form-control" wire:model="form.code" >
                            @error('form.code')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="unit-name">Nama unit</label>
                            <input id="unit-name" type="text" class="form-control" wire:model="form.name" >
                            @error('form.name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="unit-parent_id">Unit induk</label>
                            <input
                                class="form-control select-search-input mb-2"
                                type="search"
                                placeholder="Cari unit induk..."
                                aria-label="Cari pilihan unit induk"
                                data-select-search="unit-parent_id"
                                autocomplete="off"
                            >
                            <select id="unit-parent_id" class="form-select" wire:model="form.parent_id">
                                <option value="">Tanpa unit</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            @error('form.parent_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="unit-active">Aktif</label>
                            <select id="unit-active" class="form-select" wire:model="form.active">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select>
                            @error('form.active')
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
            $wire.on('open-unit-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('unitModal')).show());
            $wire.on('close-unit-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('unitModal')).hide());
        </script>
    @endscript
</div>
