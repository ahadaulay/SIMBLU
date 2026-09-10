@extends('Layout.Dashboard.master')
@section('title', 'Formulir dan Indikator')
@section('content')
<h4>Formulir dan Indikator</h4>
<p class="text-muted">Susun kolom, butir, dan perhitungan. Perubahan hanya berlaku untuk laporan baru.</p>
<livewire:indicator-table />
@endsection
