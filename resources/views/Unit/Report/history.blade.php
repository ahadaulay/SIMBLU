@extends('Layout.Dashboard.master')
@section('title','Riwayat Isi Laporan')
@section('content')
<a class="btn btn-light mb-3" href="{{ route('reports.show',$report) }}">Kembali</a>
<h4>Arsip versi {{ $history->snapshot['version'] }} — {{ $history->snapshot['unit']['name'] }}</h4>
<p>{{ $report->period->label }} · {{ $history->created_at->format('d/m/Y H:i') }} · {{ $history->note }}</p>
@foreach($history->snapshot['entries'] as $entry)
<div class="card p-3">
<h5>{{ $entry['definition']['name'] }}</h5>
<p>{{ $entry['explanation'] }}</p>
<div class="table-responsive">
<table class="table">
<thead>
<tr>
<th>Butir</th>@foreach($entry['definition']['fields'] as $field)<th>{{ $field['label'] }}</th>@endforeach
</tr>
</thead>
<tbody>@foreach($entry['rows'] as $row)<tr>
<td>{{ $row['label'] }}</td>@foreach($entry['definition']['fields'] as $field)<td>{{ $row['values'][$field['code']] ?? '—' }}</td>@endforeach
</tr>@endforeach
</tbody>
</table>
</div>
</div>
@endforeach
@endsection
