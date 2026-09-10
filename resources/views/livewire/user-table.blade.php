<div class="building-manager" @if($createOnLoad) wire:init="openContextCreateModal" @endif>
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <section class="card building-table-card">
        <div class="building-table-heading">
            <div>
                <h5 class="mb-1">{{ $selectedUnit ? 'Akun '.$selectedUnit->name : 'Pengguna' }}</h5>

                @if($selectedUnit)
                    <a class="small" href="{{ route('units.index') }}">
                        <i class="ti ti-arrow-left"></i>
                        Kembali ke Unit Kerja
                    </a>
                @endif
            </div>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Pengguna</button>
        </div>
        <div class="building-toolbar">
            <label class="visually-hidden" for="user-search">Cari Pengguna</label>
            <input id="user-search" type="search" class="form-control" placeholder="Cari..." wire:model.live.debounce.300ms="search">
            <label for="user-per-page">Tampilkan</label>
            <select id="user-per-page" class="form-select w-auto" wire:model.live="perPage">
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
                        <th>Role / Email</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="user-{{ $record->id }}">
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->role ?? 'Belum ditetapkan' }} / {{ $record->email }}</td>
                            <td>{{ $record->active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" wire:click="openEditModal({{ $record->id }})">Ubah</button>
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
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="userModalTitle">{{ $editingId ? 'Ubah' : 'Tambah' }} Pengguna</h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="user-name">Nama</label>
                            <input id="user-name" type="text" class="form-control" wire:model="form.name" >
                            @error('form.name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-username">Username</label>
                            <input id="user-username" type="text" class="form-control" wire:model="form.username" >
                            @error('form.username')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-email">Email</label>
                            <input id="user-email" type="email" class="form-control" wire:model="form.email" >
                            @error('form.email')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-password">Password (kosongkan saat edit jika tetap)</label>
                            <input id="user-password" type="password" class="form-control" wire:model="form.password" autocomplete="new-password">
                            @error('form.password')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-role">Role</label>
                            <select id="user-role" class="form-select" wire:model="form.role">
                                <option value="ADMIN">Admin</option>
                                <option value="UNIT">Unit</option>
                                <option value="PIMPINAN">Pimpinan</option>
                            </select>
                            @error('form.role')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-unit_id">Unit</label>
                            <input
                                class="form-control select-search-input mb-2"
                                type="search"
                                placeholder="Cari unit..."
                                aria-label="Cari pilihan unit pengguna"
                                data-select-search="user-unit_id"
                                autocomplete="off"
                            >
                            <select id="user-unit_id" class="form-select" wire:model="form.unit_id">
                                <option value="">Tanpa unit</option>
                                @foreach($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                            @error('form.unit_id')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-active">Aktif</label>
                            <select id="user-active" class="form-select" wire:model="form.active">
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
            $wire.on('open-user-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).show());
            $wire.on('close-user-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('userModal')).hide());
        </script>
    @endscript
</div>
