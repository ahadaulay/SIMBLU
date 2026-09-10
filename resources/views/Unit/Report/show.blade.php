@extends('Layout.Dashboard.master')
@section('title','Detail Laporan')
@section('content')
<a href="{{ route('reports.index') }}" class="btn btn-light mb-3">Kembali ke daftar laporan</a>
<livewire:report-editor :report-id="$report->id" />
@endsection
