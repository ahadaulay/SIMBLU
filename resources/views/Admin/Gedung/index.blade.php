@extends('Layout.Dashboard.master')

@section('title', 'Data Gedung')

@section('content')
    <div class="mb-4">
        <div>
            <h4 class="fw-semibold mb-1">Data Gedung</h4>
            <p class="text-muted mb-0">Kelola seluruh data gedung secara interaktif.</p>
        </div>
    </div>
    <livewire:gedung-table />
@endsection
