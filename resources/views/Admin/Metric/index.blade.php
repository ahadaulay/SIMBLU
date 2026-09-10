@extends('Layout.Dashboard.master')
@section('title','Pemetaan Rekap')
@section('content')
<h4>Pemetaan Rekap Institusi</h4><p class="text-muted">Satu formulir dapat menghasilkan beberapa ukuran laporan. Misalnya mahasiswa berprestasi nasional/internasional atau prodi berstatus Unggul.</p><livewire:metric-table />
@endsection
