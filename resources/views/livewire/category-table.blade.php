<div class="building-manager">
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <section class="card building-table-card">
        <div class="building-table-heading">
            <h5>Kategori Indikator</h5>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Kategori Indikator</button>
        </div>
        <div class="building-toolbar">
            <label class="visually-hidden" for="category-search">Cari Kategori Indikator</label>
            <input id="category-search" type="search" class="form-control" placeholder="Cari..." wire:model.live.debounce.300ms="search">
            <label for="category-per-page">Tampilkan</label>
            <select id="category-per-page" class="form-select w-auto" wire:model.live="perPage">
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
                        <tr wire:key="category-{{ $record->id }}">
                            <td>{{ $records->firstItem() + $loop->index }}</td>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        class="btn btn-sm btn-primary"
                                        href="{{ route('indicators.index', ['category' => $record->id, 'create' => 1]) }}"
                                    >
                                        <i class="ti ti-file-plus me-1"></i>
                                        Tambah Formulir
                                    </a>

                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('indicators.index', ['category' => $record->id]) }}"
                                    >
                                        Lihat Formulir
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
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="categoryModalTitle">{{ $editingId ? 'Ubah' : 'Tambah' }} Kategori Indikator</h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                    </button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="category-code">Kode</label>
                            <input id="category-code" type="text" class="form-control" wire:model="form.code" >
                            @error('form.code')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category-name">Nama kategori</label>
                            <input id="category-name" type="text" class="form-control" wire:model="form.name" >
                            @error('form.name')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="category-active">Aktif</label>
                            <select id="category-active" class="form-select" wire:model="form.active">
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
            $wire.on('open-category-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('categoryModal')).show());
            $wire.on('close-category-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('categoryModal')).hide());
        </script>
    @endscript
</div>
