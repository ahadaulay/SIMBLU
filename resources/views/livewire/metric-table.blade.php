<div @if($createOnLoad) wire:init="openContextCreateModal" @endif>
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div class="card p-3">
        <div class="d-flex justify-content-between">
            <div>
                <h5 class="mb-1">{{ $selectedIndicator ? 'Pemetaan '.$selectedIndicator->name : 'Pemetaan Hasil' }}</h5>

                @if($selectedIndicator)
                    <a class="small" href="{{ route('indicators.index', ['category' => $selectedIndicator->category_id]) }}">
                        <i class="ti ti-arrow-left"></i>
                        Kembali ke Formulir dan Indikator
                    </a>
                @endif
            </div>
            <button class="btn btn-primary" wire:click="openCreateModal">Tambah Pemetaan</button>
        </div>
        <label for="metric-search">Cari pemetaan</label>
        <input id="metric-search" class="form-control my-2" wire:model.live.debounce.300ms="search">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Sumber</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $record->code }}</td>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->indicator->name }} v{{ $record->indicator_version }}</td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" wire:click="openEditModal({{ $record->id }})">Ubah</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Belum ada pemetaan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>{{ $records->links() }}</div>
        <div class="modal fade" id="metricModal" tabindex="-1" aria-labelledby="metric-title" wire:ignore.self>
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 id="metric-title">Pemetaan Rekap</h5>
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
                            <label for="metric-source">Formulir sumber</label>
                            <input
                                class="form-control select-search-input mb-2"
                                type="search"
                                placeholder="Cari formulir sumber..."
                                aria-label="Cari pilihan formulir sumber"
                                data-select-search="metric-source"
                                autocomplete="off"
                            >
                            <select id="metric-source" class="form-select mb-3" wire:model.live="form.indicator_id">
                                <option value="">Pilih</option>
                                @foreach($indicators as $indicator)
                                    <option value="{{ $indicator->id }}">{{ $indicator->name }} v{{ $indicator->version }}</option>
                                @endforeach
                            </select>
                            <label for="metric-code">Kode pemetaan</label>
                            <input id="metric-code" class="form-control mb-3" wire:model="form.code">
                            <label for="metric-name">Nama pada laporan akhir</label>
                            <input id="metric-name" class="form-control mb-3" wire:model="form.name">
                            @foreach(['result_field'=>'Field yang dihitung','denominator_field'=>'Penyebut (khusus rasio)','filter_field'=>'Field penyaring (opsional)'] as $key=>$label)
                                <label for="metric-{{ $key }}">{{ $label }}</label>
                                <select id="metric-{{ $key }}" class="form-select mb-3" wire:model="form.{{ $key }}">
                                    <option value="">Pilih</option>
                                    @foreach($source?->fields ?? [] as $field)
                                        <option value="{{ $field['code'] }}">{{ $field['label'] }}</option>
                                    @endforeach
                                </select>
                            @endforeach
                            <label for="metric-values">Nilai yang dimasukkan (satu per baris; contoh Nasional dan Internasional)</label>
                            <textarea id="metric-values" class="form-control mb-3" wire:model="filterValues">
                            </textarea>
                            <label for="metric-method">Metode</label>
                            <select id="metric-method" class="form-select mb-3" wire:model="form.aggregation">
                                @foreach(config('reporting.aggregations') as $key=>$label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <label for="metric-scale">Skala rasio</label>
                            <select id="metric-scale" class="form-select mb-3" wire:model="form.ratio_scale">
                                <option value="1">Rasio</option>
                                <option value="100">Persentase</option>
                            </select>
                            <label class="d-block">
                                <input type="checkbox" wire:model="form.combine_units"> Gabungkan unit sumber terpilih menjadi rekap institusi</label>
                                <p class="text-muted">Aktifkan hanya jika cakupan unit tidak saling tumpang tindih. Untuk jumlah orang, gunakan identitas unik.</p>
                                <label>
                                    <input type="checkbox" wire:model="form.active"> Aktif</label>
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
                        $wire.on('open-metric-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('metricModal')).show());
                        $wire.on('close-metric-modal',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('metricModal')).hide());
                    </script>
                @endscript
            </div>
