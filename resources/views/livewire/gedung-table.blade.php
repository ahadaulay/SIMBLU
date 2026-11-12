<div class="building-manager">
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show building-alert" role="alert">
            <span class="building-alert-icon"><i class="ti ti-circle-check"></i></span>
            <span>{{ session('success') }}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <section class="card building-table-card" aria-labelledby="building-table-title">
        <div class="building-table-heading">
            <div class="d-flex align-items-center gap-3">
                <span class="building-heading-icon"><i class="ti ti-building-skyscraper"></i></span>
                <div>
                    <h5 id="building-table-title" class="mb-1">Daftar Gedung</h5>
                    <p class="mb-0">{{ number_format($buildings->total()) }} gedung tersimpan dalam sistem</p>
                </div>
            </div>
            <button class="btn btn-primary building-add-button" type="button" wire:click="openCreateModal">
                <i class="ti ti-plus me-1"></i>Tambah Gedung
            </button>
        </div>

        <div class="building-toolbar">
            <div class="position-relative building-search">
                <i class="ti ti-search building-search-icon" aria-hidden="true"></i>
                <label class="visually-hidden" for="building-search">Cari gedung</label>
                <input
                    id="building-search"
                    type="search"
                    class="form-control"
                    placeholder="Cari nama, kode, atau keterangan..."
                    wire:model.live.debounce.300ms="search"
                    autocomplete="off"
                >
                @if ($search !== '')
                    <button class="building-search-clear" type="button" wire:click="$set('search', '')" aria-label="Hapus pencarian">
                        <i class="ti ti-x"></i>
                    </button>
                @endif
            </div>

            <div class="d-flex align-items-center gap-2 building-page-control">
                <label for="building-page-size">Tampilkan</label>
                <select id="building-page-size" class="form-select" wire:model.live="perPage">
                    <option value="5">5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                </select>
                <span>baris</span>
            </div>
        </div>

        @php
            $sortIcon = function (string $field) use ($sortField, $sortDirection): string {
                if ($sortField !== $field) {
                    return 'ti-selector';
                }

                return $sortDirection === 'asc' ? 'ti-chevron-up' : 'ti-chevron-down';
            };
        @endphp

        <div class="building-table-wrap position-relative">
            <div class="building-loading" wire:loading.flex wire:target="search,perPage,sortBy,deleteBuilding">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Memuat data...</span>
            </div>

            <div class="table-responsive">
                <table class="table building-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="building-number-column">No.</th>
                            <th class="building-info-column">
                                <button class="building-sort-button" type="button" wire:click="sortBy('nama')">
                                    Informasi Gedung <i class="ti {{ $sortIcon('nama') }}"></i>
                                </button>
                            </th>
                            <th class="building-code-column">
                                <button class="building-sort-button" type="button" wire:click="sortBy('kode')">
                                    Kode <i class="ti {{ $sortIcon('kode') }}"></i>
                                </button>
                            </th>
                            <th class="building-status-column">Status Data</th>
                            <th class="building-description-column">Keterangan</th>
                            <th class="text-end building-actions-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($buildings as $index => $building)
                            @php($isComplete = filled($building->kode) && filled($building->keterangan))
                            <tr wire:key="building-{{ $building->id }}">
                                <td class="building-row-number">{{ $buildings->firstItem() + $index }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-3 building-identity">
                                        <span class="building-row-icon"><i class="ti ti-building"></i></span>
                                        <div class="min-w-0">
                                            <p class="building-name mb-1">{{ $building->nama }}</p>
                                            <small>ID #{{ str_pad((string) $building->id, 4, '0', STR_PAD_LEFT) }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @if ($building->kode)
                                        <span class="building-code">{{ $building->kode }}</span>
                                    @else
                                        <span class="building-code building-code-empty">Belum tersedia</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="building-status {{ $isComplete ? 'is-complete' : 'is-incomplete' }}">
                                        <i class="ti {{ $isComplete ? 'ti-circle-check-filled' : 'ti-alert-circle-filled' }}"></i>
                                        {{ $isComplete ? 'Lengkap' : 'Perlu dilengkapi' }}
                                    </span>
                                </td>
                                <td class="building-description building-description-column">
                                    {{ $building->keterangan ? \Illuminate\Support\Str::limit($building->keterangan, 68) : 'Belum ada keterangan.' }}
                                </td>
                                <td class="text-end">
                                    <div class="building-actions">
                                        <a class="building-action-button action-detail" href="{{ route('gedungs.show', $building) }}" aria-label="Lihat detail {{ $building->nama }}" title="Detail">
                                            <i class="ti ti-eye"></i>
                                        </a>
                                        <button class="building-action-button action-edit" type="button" wire:click="openEditModal({{ $building->id }})" aria-label="Ubah {{ $building->nama }}" title="Ubah">
                                            <i class="ti ti-pencil"></i>
                                        </button>
                                        <button class="building-action-button action-delete" type="button" wire:click="deleteBuilding({{ $building->id }})" wire:confirm="Hapus gedung {{ $building->nama }}? Data yang dihapus tidak dapat dikembalikan." aria-label="Hapus {{ $building->nama }}" title="Hapus">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="building-empty-state">
                                        <span><i class="ti {{ $search !== '' ? 'ti-search-off' : 'ti-building-off' }}"></i></span>
                                        <h6>{{ $search !== '' ? 'Gedung tidak ditemukan' : 'Belum ada data gedung' }}</h6>
                                        <p>{{ $search !== '' ? 'Coba gunakan kata kunci lain atau hapus pencarian.' : 'Tambahkan gedung pertama agar data mulai ditampilkan.' }}</p>
                                        @if ($search !== '')
                                            <button type="button" class="btn btn-light-primary text-primary" wire:click="$set('search', '')">Hapus Pencarian</button>
                                        @else
                                            <button type="button" class="btn btn-primary" wire:click="openCreateModal">Tambah Gedung</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="building-table-footer">
            <p>
                @if ($buildings->total() > 0)
                    Menampilkan <strong>{{ $buildings->firstItem() }}–{{ $buildings->lastItem() }}</strong> dari <strong>{{ $buildings->total() }}</strong> data
                @else
                    Tidak ada data ditampilkan
                @endif
            </p>
            @if ($buildings->hasPages())
                {{ $buildings->onEachSide(1)->links() }}
            @endif
        </div>
    </section>

    <div class="modal fade" id="buildingModal" tabindex="-1" aria-labelledby="building-modal-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content building-modal">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <span class="building-modal-icon"><i class="ti ti-building-plus"></i></span>
                        <div>
                            <h5 class="modal-title" id="building-modal-title">{{ $editingBuildingId ? 'Ubah Data Gedung' : 'Tambah Gedung Baru' }}</h5>
                            <p class="mb-0">Lengkapi informasi gedung dengan benar.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" wire:click="resetForm" aria-label="Tutup"></button>
                </div>
                <form wire:submit="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="building-name" class="form-label">Nama Gedung <span class="text-danger">*</span></label>
                            <input id="building-name" type="text" class="form-control @error('name') is-invalid @enderror" wire:model="name" placeholder="Contoh: Gedung Rektorat" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label for="building-code" class="form-label">Kode Gedung</label>
                            <input id="building-code" type="text" class="form-control @error('code') is-invalid @enderror" wire:model="code" placeholder="Contoh: GR-01" maxlength="50">
                            @error('code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="building-description" class="form-label">Keterangan</label>
                            <textarea id="building-description" class="form-control @error('description') is-invalid @enderror" rows="4" wire:model="description" placeholder="Tuliskan fungsi atau informasi singkat gedung"></textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" type="button" data-bs-dismiss="modal" wire:click="resetForm">Batal</button>
                        <button class="btn btn-primary px-4" type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i>Simpan</span>
                            <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @script
    <script>
        $wire.on('open-gedung-modal', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('buildingModal')).show();
        });

        $wire.on('close-gedung-modal', () => {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('buildingModal')).hide();
        });
    </script>
    @endscript
</div>
