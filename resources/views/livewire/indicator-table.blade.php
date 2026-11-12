<div class="building-manager" @if($createOnLoad) wire:init="openContextCreateModal" @endif>
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card">
        <div class="building-table-heading">
            <div>
                <h5 class="mb-1">{{ $selectedCategory ? 'Formulir '.$selectedCategory->name : 'Daftar Indikator' }}</h5>

                @if($selectedCategory)
                    <a class="small" href="{{ route('categories.index') }}">
                        <i class="ti ti-arrow-left"></i>
                        Kembali ke Kategori Indikator
                    </a>
                @endif
            </div>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Indikator</button>
        </div>
        <div class="building-toolbar">
            <label class="visually-hidden" for="indicator-search">Cari indikator</label>
            <input id="indicator-search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="Cari kode atau nama">
            <label for="indicator-page">Tampilkan</label>
            <select id="indicator-page" class="form-select w-auto" wire:model.live="perPage">
                <option>5</option>
                <option>10</option>
                <option>25</option>
            </select>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>
                            <button class="btn btn-link" wire:click="sortBy">Indikator</button>
                        </th>
                        <th>Kategori</th>
                        <th>Rekap</th>
                        <th>Versi</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr wire:key="indicator-{{ $record->id }}">
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->category->name }}</td>
                            <td>{{ config('reporting.aggregations')[$record->aggregation] }}</td>
                            <td>{{ $record->version }}</td>
                            <td>{{ $record->active ? 'Aktif' : 'Nonaktif' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="{{ route('metrics.index', ['indicator' => $record->id, 'create' => 1]) }}"
                                    >
                                        <i class="ti ti-calculator me-1"></i>
                                        Tambah Pemetaan
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
                            <td colspan="7" class="p-4 text-center">Buat kategori, lalu tambahkan indikator pertama.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $records->links() }}</div>
    </div>
    <div class="modal fade" id="indicatorModal" tabindex="-1" aria-labelledby="indicator-title" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="indicator-title">{{ $editingId ? 'Ubah' : 'Tambah' }} Indikator</h5>
                    <button class="btn-close" data-bs-dismiss="modal" aria-label="Tutup">
                    </button>
                </div>
                <form wire:submit="save" class="overflow-auto">
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-category_id">Kategori</label>
                                <input
                                    class="form-control select-search-input mb-2"
                                    type="search"
                                    placeholder="Cari kategori..."
                                    aria-label="Cari pilihan kategori indikator"
                                    data-select-search="i-category_id"
                                    autocomplete="off"
                                >
                                <select class="form-select" id="i-category_id" wire:model="form.category_id">
                                    <option value="">Pilih kategori</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-code">Kode</label>
                                <input class="form-control" id="i-code" wire:model="form.code">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-name">Nama indikator</label>
                                <input class="form-control" id="i-name" wire:model="form.name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-description">Petunjuk pengisian</label>
                                <input class="form-control" id="i-description" wire:model="form.description">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-form_type">Bentuk formulir</label>
                                <select class="form-select" id="i-form_type" wire:model.live="form.form_type">
                                    <option value="SINGLE">Tunggal</option>
                                    <option value="FIXED">Tabel tetap</option>
                                    <option value="DYNAMIC">Tabel dinamis</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-aggregation">Metode rekap</label>
                                <select class="form-select" id="i-aggregation" wire:model.live="form.aggregation">
                                    @foreach(config('reporting.aggregations') as $value=>$label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-direction">Arah kinerja</label>
                                <select class="form-select" id="i-direction" wire:model.live="form.direction">
                                    <option value="NEUTRAL">Netral</option>
                                    <option value="UP_GOOD">Naik lebih baik</option>
                                    <option value="DOWN_GOOD">Turun lebih baik</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-ratio_scale">Skala rasio</label>
                                <select class="form-select" id="i-ratio_scale" wire:model.live="form.ratio_scale">
                                    <option value="1">Rasio</option>
                                    <option value="100">Persentase</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-result_field">Field hasil rekap</label>
                                <select class="form-select" id="i-result_field" wire:model="form.result_field">
                                    <option value="">Pilih field</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['code'] }}">{{ $field['label'] }} ({{ $field['code'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="i-denominator_field">Field penyebut (untuk rasio)</label>
                                <select class="form-select" id="i-denominator_field" wire:model="form.denominator_field">
                                    <option value="">Pilih field</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['code'] }}">{{ $field['label'] }} ({{ $field['code'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <label>
                                <input type="checkbox" wire:model="form.active"> Aktif</label>
                                <label>
                                    <input type="checkbox" wire:model="form.requires_attachment"> Wajib lampiran per indikator</label>
                                    <label>
                                        <input type="checkbox" wire:model="form.requires_explanation"> Wajib penjelasan</label>
                                    </div>
                                    <h6>Kolom formulir</h6>
                                    <p class="text-muted">Kode field menggunakan huruf kecil dan garis bawah. Rumus hanya mengacu pada field angka sebelumnya.</p>
                                    @foreach($fields as $index=>$field)
                                        <div class="border rounded p-3 mb-3" wire:key="field-{{ $index }}">
                                            <div class="row g-2">
                                                <div class="col-md-4">
                                                    <label for="f-code-{{ $index }}">Kode</label>
                                                    <input type="text"  id="f-code-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.code">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-label-{{ $index }}">Label</label>
                                                    <input type="text"  id="f-label-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.label">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-unit-{{ $index }}">Satuan</label>
                                                    <input type="text"  id="f-unit-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.unit">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-decimals-{{ $index }}">Desimal (0–6)</label>
                                                    <input type="number" step="any" id="f-decimals-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.decimals">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-min-{{ $index }}">Minimum</label>
                                                    <input type="number" step="any" id="f-min-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.min">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-max-{{ $index }}">Maksimum</label>
                                                    <input type="number" step="any" id="f-max-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.max">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="f-type-{{ $index }}">Tipe</label>
                                                    <select id="f-type-{{ $index }}" class="form-select" wire:model.live="fields.{{ $index }}.type">
                                                        <option value="NUMBER">Angka</option>
                                                        <option value="TEXT">Teks / identitas</option>
                                                        <option value="DATE">Tanggal</option>
                                                        <option value="CHOICE">Pilihan</option>
                                                        <option value="BOOLEAN">Ya / Tidak</option>
                                                        <option value="CALCULATED">Hasil perhitungan</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4 d-flex align-items-end">
                                                    <label>
                                                        <input type="checkbox" wire:model="fields.{{ $index }}.required"> Wajib diisi</label>
                                                    </div>
                                                    @if($field['type'] === 'CHOICE')
                                                        <div class="col-12">
                                                            <label for="f-options-{{ $index }}">Pilihan (satu per baris)</label>
                                                            <textarea id="f-options-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.options">
                                                            </textarea>
                                                        </div>
                                                    @endif
                                                    @if($field['type'] === 'CALCULATED')
                                                        <div class="col-md-4">
                                                            <label for="f-operation-{{ $index }}">Operasi</label>
                                                            <select id="f-operation-{{ $index }}" class="form-select" wire:model="fields.{{ $index }}.operation">
                                                                <option value="SUM">Jumlah</option>
                                                                <option value="DIFFERENCE">Selisih</option>
                                                                <option value="PRODUCT">Perkalian</option>
                                                                <option value="RATIO">Pembagian</option>
                                                                <option value="PERCENT">Persentase</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-8">
                                                            <label for="f-operands-{{ $index }}">Kode sumber dipisahkan koma (misalnya: accepted,applicants)</label>
                                                            <input id="f-operands-{{ $index }}" class="form-control" wire:model="fields.{{ $index }}.operands">
                                                        </div>
                                                    @endif
                                                </div>
                                                <button class="btn btn-sm btn-outline-danger mt-2" type="button" wire:click="removeField({{ $index }})">Hapus kolom</button>
                                            </div>
                                        @endforeach
                                        <button type="button" class="btn btn-outline-primary mb-4" wire:click="addField">Tambah kolom</button>
                                        @if($form['form_type'] === 'FIXED')
                                            <h6>Butir / baris tetap</h6>
                                            @foreach($items as $index=>$item)
                                                <div class="row g-2 mb-2" wire:key="item-{{ $index }}">
                                                    <div class="col-md-4">
                                                        <label for="item-code-{{ $index }}">Kode butir</label>
                                                        <input id="item-code-{{ $index }}" class="form-control" wire:model="items.{{ $index }}.code">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label for="item-label-{{ $index }}">Nama butir</label>
                                                        <input id="item-label-{{ $index }}" class="form-control" wire:model="items.{{ $index }}.label">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <button type="button" class="btn btn-outline-danger mt-4" wire:click="removeItem({{ $index }})">Hapus</button>
                                                    </div>
                                                </div>
                                            @endforeach
                                            <button type="button" class="btn btn-outline-primary" wire:click="addItem">Tambah butir</button>
                                        @endif
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Simpan indikator</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @script
                        <script>
                            $wire.on('open-indicator-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('indicatorModal')).show());
                            $wire.on('close-indicator-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('indicatorModal')).hide());
                        </script>
                    @endscript
                </div>
