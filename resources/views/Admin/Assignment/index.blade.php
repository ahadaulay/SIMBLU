@extends('Layout.Dashboard.master')
@section('title','Penugasan Indikator')
@section('content')
<h4>Penugasan Indikator</h4><p class="text-muted">Tentukan kewajiban setiap unit per periode sebelum unit mulai membuat laporan.</p>
<livewire:assignment-table />
@endsection
