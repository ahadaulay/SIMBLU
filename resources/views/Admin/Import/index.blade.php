@extends('Layout.Dashboard.master')
@section('title','Impor Excel Historis')
@section('content')
<h4>Impor Excel Historis</h4><p class="text-muted">Petakan satu blok data ke satu indikator dan satu periode. Impor membuat draf dan tidak menimpa data yang sudah diisi.</p><livewire:import-table />
@endsection
