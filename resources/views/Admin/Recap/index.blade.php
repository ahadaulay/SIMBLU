@extends('Layout.Dashboard.master')
@section('title','Laporan Custom')
@section('content')
<h4>Laporan Custom</h4><p class="text-muted">Pilih kombinasi triwulan dan tahun. Hanya laporan terverifikasi menjadi sumber angka resmi.</p><livewire:recap-table />
@endsection
