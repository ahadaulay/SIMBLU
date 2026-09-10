@extends('Layout.Dashboard.master')
@section('title', 'Periode Tahun')
@section('content')
<h4>Periode Tahun</h4>
<p class="text-muted mb-4">Pusat pengaturan seluruh alur pelaporan kinerja.</p>
<livewire:reporting-cycle-table />
@endsection
