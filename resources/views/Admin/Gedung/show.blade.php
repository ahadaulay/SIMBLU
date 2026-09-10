@extends('Layout.Dashboard.master')
@section('title', $building->nama)
@section('content')
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div><h4 class="fw-semibold mb-1">{{ $building->nama }}</h4><p class="text-muted mb-0">Detail data gedung</p></div>
        <div class="d-flex gap-2">
            <a class="btn btn-light" href="{{ route('gedungs.index') }}"><i class="ti ti-arrow-left me-1"></i>Kembali</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row mb-4"><div class="col-md-3 text-muted">Nama Gedung</div><div class="col-md-9 fw-semibold">{{ $building->nama }}</div></div>
            <div class="row mb-4"><div class="col-md-3 text-muted">Kode Gedung</div><div class="col-md-9">{{ $building->kode ?: '—' }}</div></div>
            <div class="row"><div class="col-md-3 text-muted">Keterangan</div><div class="col-md-9" style="white-space:pre-wrap">{{ $building->keterangan ?: '—' }}</div></div>
        </div>
    </div>
@endsection
