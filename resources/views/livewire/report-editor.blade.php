<div class="report-editor">
    <style>
        /* Darker gray lines, text, and form borders so the indicator form is easier to read. */
        .report-editor .report-entry {
            border: 2px solid #6c757d;
            margin-bottom: 1.5rem;
            color: #343a40;
        }

        .report-editor .report-entry-title {
            border-bottom: 2px solid #6c757d;
            padding-bottom: .5rem;
            color: #212529;
        }

        .report-editor .report-entry .text-muted,
        .report-editor .report-entry label,
        .report-editor .report-entry small {
            color: #495057 !important;
        }

        .report-editor .report-entry .table,
        .report-editor .report-entry .table th,
        .report-editor .report-entry .table td {
            border-color: #6c757d !important;
            color: #343a40;
        }

        .report-editor .report-entry .table thead th {
            background-color: #e9ecef;
            color: #212529;
        }

        .report-editor .report-entry .form-control,
        .report-editor .report-entry .form-select {
            border-color: #6c757d;
            color: #212529;
        }
    </style>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div>
            <h4>{{ $report->unit->name }} — {{ $report->period->label }}</h4>
            <p>{{ config('reporting.statuses')[$report->status] }} · Versi {{ $report->version }} · Kelengkapan tersimpan {{ $completion }}%</p>
            <p class="mb-0"><strong>Jumlah indikator yang harus diisi: {{ $report->entries->count() }}</strong></p>
        </div>
        <button class="btn btn-outline-secondary align-self-start" wire:click="reload" wire:confirm="Muat ulang dan lepaskan isian yang belum disimpan?">Muat ulang</button>
    </div>
    @if($report->period->deadline->endOfDay()->isPast() && $report->status !== 'VERIFIED')
        <div class="alert alert-warning">Batas pengisian {{ $report->period->deadline->format('d/m/Y') }} telah lewat. Pengisian hanya tersedia selama periode dibuka.</div>
    @endif
    @if(session()->has('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div id="report-validation-summary" class="alert alert-danger" role="alert" tabindex="-1">
            <strong>Laporan belum dapat diproses.</strong>
            <p class="mb-2">Perbaiki bagian berikut, kemudian simpan atau ajukan kembali:</p>
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @foreach($report->entries as $entry)
        @php($definition = $entry->definition)
        @php($detailField = app(\App\Services\IndicatorCalculator::class)->detailField($definition))
        <section class="card p-3 report-entry" wire:key="entry-{{ $entry->id }}">
            <h5 class="report-entry-title d-flex justify-content-between align-items-baseline gap-3">
                <span>{{ $loop->iteration }}. {{ $definition['name'] }}</span>
                <small class="text-muted text-nowrap">{{ $entry->required ? 'Wajib' : 'Opsional' }} · v{{ $definition['version'] }}</small>
            </h5>
            <p>{{ $definition['description'] }}</p>
            <fieldset @disabled(!$editable)>
                @if($definition['form_type'] === 'DYNAMIC')
                    <label class="mb-3">
                        <input type="checkbox" wire:model="sections.{{ $entry->id }}.no_activity"> Tidak ada kegiatan (hapus baris kosong dan isi alasannya)</label>
                    @endif
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Butir / uraian</th>
                                    @foreach($definition['fields'] as $field)
                                        <th>{{ $field['label'] }} {{ $field['required'] ? '*' : '' }}<small class="d-block">{{ $field['unit'] }}</small>
                                        </th>
                                    @endforeach
                                    @if($detailField)
                                        <th>Detail <small class="d-block">Opsional</small></th>
                                    @endif
                                    @if($editable && $definition['form_type'] === 'DYNAMIC')
                                        <th>Aksi</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sections[$entry->id]['rows'] as $index=>$row)
                                    <tr wire:key="row-{{ $entry->id }}-{{ $row['key'] }}">
                                        <td style="min-width:180px">
                                            @if($definition['form_type'] === 'DYNAMIC')
                                                @php($labelPath = 'sections.'.$entry->id.'.rows.'.$index.'.label')
                                                <label class="visually-hidden" for="row-{{ $entry->id }}-{{ $index }}">Uraian baris</label>
                                                <input id="row-{{ $entry->id }}-{{ $index }}" class="form-control @error($labelPath) is-invalid @enderror" wire:model="{{ $labelPath }}">
                                                @error($labelPath)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        </td>
                                        @foreach($definition['fields'] as $field)
                                            @php($path = 'sections.'.$entry->id.'.rows.'.$index.'.values.'.$field['code'])
                                            @php($inputId = 'value-'.$entry->id.'-'.$index.'-'.$field['code'])
                                            <td style="min-width:160px">
                                                <label class="visually-hidden" for="{{ $inputId }}">{{ $field['label'] }} — {{ $row['label'] }}</label>
                                                @if($field['type'] === 'CALCULATED')
                                                    <output id="{{ $inputId }}">{{ app(\App\Services\IndicatorCalculator::class)->fieldValue($field, $row['values']) ?? '—' }}</output>
                                                    <small class="text-muted d-block">Dihitung saat simpan</small>
                                                @elseif($field['type'] === 'CHOICE')
                                                    <select id="{{ $inputId }}" class="form-select @error($path) is-invalid @enderror" wire:model="{{ $path }}">
                                                        <option value="">Pilih</option>
                                                        @foreach(app(\App\Services\IndicatorCalculator::class)->options($field) as $option)
                                                            <option value="{{ $option }}">{{ $option }}</option>
                                                        @endforeach
                                                    </select>
                                                @elseif($field['type'] === 'BOOLEAN')
                                                    <select id="{{ $inputId }}" class="form-select @error($path) is-invalid @enderror" wire:model="{{ $path }}">
                                                        <option value="">Pilih</option>
                                                        <option value="1">Ya</option>
                                                        <option value="0">Tidak</option>
                                                    </select>
                                                @else
                                                    <input id="{{ $inputId }}" class="form-control @error($path) is-invalid @enderror" type="{{ $field['type'] === 'NUMBER' ? 'number' : ($field['type'] === 'DATE' ? 'date' : 'text') }}" step="any" wire:model="{{ $path }}">
                                                @endif
                                                @error($path)
                                                    <div class="text-danger">{{ $message }}</div>
                                                @enderror
                                            </td>
                                        @endforeach
                                        @if($detailField)
                                            <td style="min-width:130px">
                                                <a href="#" class="btn btn-sm btn-outline-primary" role="button" wire:click.prevent="openDetails({{ $entry->id }},{{ $index }})">
                                                    Detail{{ count($row['details'] ?? []) ? ' ('.count($row['details']).')' : '' }}
                                                </a>
                                                @error('sections.'.$entry->id.'.rows.'.$index.'.details')
                                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                                @enderror
                                            </td>
                                        @endif
                                        @if($editable && $definition['form_type'] === 'DYNAMIC')
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeRow({{ $entry->id }},{{ $index }})" wire:confirm="Hapus baris dari draf ini?">Hapus</button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($editable && $definition['form_type'] === 'DYNAMIC')
                        <button type="button" class="btn btn-outline-primary mb-3" wire:click="addRow({{ $entry->id }})">Tambah baris</button>
                    @endif
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="explanation-{{ $entry->id }}">Penjelasan / alasan kondisi</label>
                            <textarea id="explanation-{{ $entry->id }}" class="form-control @error('sections.'.$entry->id.'.explanation') is-invalid @enderror" wire:model="sections.{{ $entry->id }}.explanation">
                            </textarea>
                            @error('sections.'.$entry->id.'.explanation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="responsible-{{ $entry->id }}">Nama ASN penanggung jawab</label>
                            <input
                                id="responsible-{{ $entry->id }}"
                                class="form-control bg-light"
                                wire:model="sections.{{ $entry->id }}.responsible_name"
                                readonly
                            >
                            <small class="text-muted">
                                Diambil otomatis dari penanggung jawab pada master penugasan.
                            </small>
                        </div>
                    </div>
                </fieldset>
                <div class="mt-3">
                    <h6>Lampiran {{ $definition['requires_attachment'] ? '(wajib)' : '(opsional)' }}</h6>
                    @foreach($report->attachments->where('entry_id',$entry->id) as $attachment)
                        <a class="d-block mb-2" href="{{ route('reports.attachment',$attachment) }}">{{ $attachment->name }}</a>
                    @endforeach
                    @if($editable)
                        <label for="upload-{{ $entry->id }}">Bukti pendukung (maks. {{ config('reporting.attachment_max_kb') / 1024 }} MB)</label>
                        <input id="upload-{{ $entry->id }}" type="file" class="form-control mb-2" wire:model="uploads.{{ $entry->id }}">
                        <div wire:loading wire:target="uploads.{{ $entry->id }},upload" class="text-muted" role="status">Mengunggah...</div>
                        @error('uploads.'.$entry->id)
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                        @error('sections.'.$entry->id.'.attachment')
                            <div class="text-danger">{{ $message }}</div>
                        @enderror
                    @endif
                </div>
            </section>
        @endforeach
        <div class="modal fade" id="reportDetailModal" tabindex="-1" aria-labelledby="report-detail-title" data-bs-backdrop="static" data-bs-keyboard="false" wire:ignore.self>
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="report-detail-title">Detail {{ $selectedDetail['label'] ?? '' }}</h5>
                            <small class="text-muted">Opsional. Jika diisi, total detail harus sama dengan nilai utama saat laporan diajukan.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        @if($selectedDetail)
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>Jumlah</th>
                                            @if($editable)<th>Aksi</th>@endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($selectedDetail['details'] as $detailIndex => $detail)
                                            <tr wire:key="detail-{{ $selectedDetail['entry_id'] }}-{{ $selectedDetail['row_index'] }}-{{ $detailIndex }}">
                                                <td>
                                                    <label class="visually-hidden" for="detail-name-{{ $detailIndex }}">Nama detail</label>
                                                    <input id="detail-name-{{ $detailIndex }}" class="form-control" type="text" wire:model="detailDraft.{{ $detailIndex }}.name" @disabled(!$editable)>
                                                    @error('detailDraft.'.$detailIndex.'.name')<div class="text-danger small">{{ $message }}</div>@enderror
                                                </td>
                                                <td>
                                                    <label class="visually-hidden" for="detail-amount-{{ $detailIndex }}">Jumlah detail</label>
                                                    <input id="detail-amount-{{ $detailIndex }}" class="form-control" type="number" min="0" step="any" wire:model.live.debounce.300ms="detailDraft.{{ $detailIndex }}.amount" @disabled(!$editable)>
                                                    @error('detailDraft.'.$detailIndex.'.amount')<div class="text-danger small">{{ $message }}</div>@enderror
                                                </td>
                                                @if($editable)
                                                    <td><button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeDetailRow({{ $detailIndex }})">Hapus</button></td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ $editable ? 3 : 2 }}" class="text-center text-muted">Belum ada detail.</td></tr>
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total detail</th>
                                            <th>{{ number_format((float) $selectedDetail['total'], (int) $selectedDetail['field']['decimals'], ',', '.') }}</th>
                                            @if($editable)<th></th>@endif
                                        </tr>
                                        <tr>
                                            <th>Nilai utama</th>
                                            <th>{{ is_numeric($selectedDetail['target']) ? number_format((float) $selectedDetail['target'], (int) $selectedDetail['field']['decimals'], ',', '.') : 'Belum diisi' }}</th>
                                            @if($editable)<th></th>@endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            @if($selectedDetail['details'] !== [] && !$selectedDetail['can_finish'])
                                <div class="alert alert-warning" role="alert">
                                    {{ $selectedDetail['message'] }}
                                </div>
                            @endif
                            @error('detailDraft')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                            @if($editable)
                                <button type="button" class="btn btn-outline-primary" wire:click="addDetailRow">Tambah baris</button>
                            @endif
                        @endif
                    </div>
                    <div class="modal-footer">
                        @if($editable)
                            <button
                                id="finish-report-detail"
                                type="button"
                                class="btn btn-primary"
                                aria-disabled="{{ ($selectedDetail['can_finish'] ?? true) ? 'false' : 'true' }}"
                                @disabled(!($selectedDetail['can_finish'] ?? true))
                                wire:click="saveDetails"
                            >Simpan</button>
                        @else
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if($editable)
            <div class="d-flex gap-3 mb-4">
                <button class="btn btn-outline-primary" wire:click="save" wire:loading.attr="disabled">Simpan draf</button>
                <button class="btn btn-primary" wire:click="submit" wire:confirm="Ajukan laporan? Isian akan dikunci sampai admin mengembalikan untuk revisi." wire:loading.attr="disabled">Ajukan laporan</button>
                <span wire:loading role="status">Memproses...</span>
            </div>
        @endif
        <section class="card p-3">
            <h5>Catatan verifikasi</h5>
            @forelse($report->notes as $item)
                <div class="border-bottom py-2">
                    <p>{{ $item->note }}</p>
                    <small>{{ $item->user->name }} · {{ $item->entry_id ? 'Indikator: '.($report->entries->firstWhere('id',$item->entry_id)?->definition['name'] ?? '') : 'Seluruh laporan' }} · {{ $item->resolved_at ? 'Diselesaikan' : 'Terbuka' }}</small>
                    @can('reporting-admin')
                        @if(!$item->resolved_at && in_array($report->status,['SUBMITTED','REVISION']) && $report->period->status !== 'LOCKED')
                            <button class="btn btn-sm btn-outline-success" wire:click="resolveNote({{ $item->id }})">Tandai selesai</button>
                        @endif
                    @endcan
                </div>
            @empty
                <p>Belum ada catatan.</p>
            @endforelse
            @can('reporting-admin')
                @if(in_array($report->status,['SUBMITTED','VERIFIED']) && $report->period->status !== 'LOCKED')
                    <label class="mt-3" for="verification-note">Catatan / alasan revisi atau pembukaan kembali</label>
                    <textarea id="verification-note" class="form-control mb-3" wire:model="note">
                    </textarea>
                    @if($report->status === 'SUBMITTED')
                        <label for="note-entry">Tujuan catatan</label>
                        <input
                            class="form-control select-search-input mb-2"
                            type="search"
                            placeholder="Cari indikator tujuan catatan..."
                            aria-label="Cari pilihan indikator tujuan catatan"
                            data-select-search="note-entry"
                            autocomplete="off"
                        >
                        <select id="note-entry" class="form-select mb-3" wire:model="noteEntryId">
                            <option value="">Seluruh laporan</option>
                            @foreach($report->entries as $entry)
                                <option value="{{ $entry->id }}">{{ $entry->definition['name'] }}</option>
                            @endforeach
                        </select>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-primary" wire:click="addNote" wire:loading.attr="disabled">Tambah catatan</button>
                            <button class="btn btn-warning" wire:click="transition('REVISION')" wire:loading.attr="disabled">Kembalikan untuk revisi</button>
                            <button class="btn btn-success" wire:click="transition('VERIFIED')" wire:confirm="Setujui dan kunci laporan sebagai sumber rekap resmi?" wire:loading.attr="disabled">Verifikasi dan kunci</button>
                        </div>
                    @else
                        <p class="text-muted">Pembukaan kembali mengeluarkan laporan ini dari rekap resmi sampai diverifikasi ulang. Arsip versi lama tetap tersedia.</p>
                        <button class="btn btn-warning" wire:click="transition('REVISION')" wire:confirm="Buka kembali laporan dan keluarkan sementara dari rekap resmi?" wire:loading.attr="disabled">Buka kembali untuk revisi</button>
                    @endif
                @endif
            @endcan
        </section>
        <section class="card p-3">
            <h5>Riwayat perubahan (30 terakhir)</h5>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                            <th>Status</th>
                            <th>Catatan</th>
                            <th>Arsip isi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($histories as $history)
                            <tr>
                                <td>{{ $history->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $history->user->name }}</td>
                                <td>{{ config('reporting.statuses')[$history->to_status] }}</td>
                                <td>{{ $history->note }}</td>
                                <td>
                                    <a href="{{ route('reports.history',$history) }}">Versi {{ $history->snapshot['version'] }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @script
            <script>
                $wire.on('open-report-detail-modal', () => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('reportDetailModal')).show();
                });

                $wire.on('close-report-detail-modal', () => {
                    bootstrap.Modal.getOrCreateInstance(document.getElementById('reportDetailModal')).hide();
                });

                $wire.on('report-validation-failed', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    setTimeout(() => document.getElementById('report-validation-summary')?.focus({ preventScroll: true }), 300);
                });
            </script>
        @endscript
    </div>
