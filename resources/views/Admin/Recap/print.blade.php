<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Laporan Kinerja SIBLU</title>
<style>body{font-family:Arial,sans-serif;font-size:11px;color:#17202a}table{border-collapse:collapse;width:100%;margin-bottom:20px}th,td{border:1px solid #bbb;padding:7px;text-align:left}thead{display:table-header-group}tr{break-inside:avoid}h1{font-size:20px}@page{size:A4 landscape;margin:15mm}@media print{button{display:none}}</style>
</head>
<body>
<button onclick="window.print()">Cetak / pilih Simpan sebagai PDF</button>
<h1>Laporan Kinerja SIBLU</h1>
<p>Periode: {{ implode(', ',$result['periods']) }}</p>
<p>Dibuat {{ $result['generated_at'] }} · Sumber: laporan terverifikasi · {{ $result['missing'] || $result['incompatible'] ? 'Sementara, cakupan belum lengkap / versi berbeda' : 'Seluruh penugasan pilihan terverifikasi' }}</p>
@if($result['incompatible'])<p>Versi berbeda dan dikeluarkan dari rekap: {{ implode('; ',$result['incompatible']) }}.</p>@endif
<h2>Perbandingan triwulan yang sama tahun sebelumnya</h2>
<table>
<thead>
<tr>
<th>Unit / indikator</th>
<th>Periode</th>
<th>Tahun sebelumnya</th>
<th>Perubahan</th>
<th>Kinerja</th>
</tr>
</thead>
<tbody>@foreach($result['rows'] as $row)<tr>
<td>{{ $row['unit'] }} / {{ $row['indicator'] }}</td>
<td>{{ $row['group'] }}</td>
<td>{{ $row['previous_value'] ?? 'Tidak tersedia' }}</td>
<td>{{ $row['condition'] }} {{ $row['change_percent'] === null ? '' : number_format($row['change_percent'],2).'%' }}</td>
<td>{{ $row['performance'] }}</td>
</tr>@endforeach
</tbody>
</table>
<table>
<thead>
<tr>
<th>Unit</th>
<th>Indikator</th>
<th>Versi</th>
<th>Periode</th>
<th>Metode</th>
<th>Hasil</th>
</tr>
</thead>
<tbody>@foreach($result['rows'] as $row)<tr>
<td>{{ $row['unit'] }}</td>
<td>{{ $row['indicator'] }}</td>
<td>{{ $row['version'] }}</td>
<td>{{ $row['group'] }}</td>
<td>{{ config('reporting.aggregations')[$row['definition']['aggregation']] }}</td>
<td>{{ $row['value'] === null ? 'Tidak tersedia / rincian' : number_format($row['value'],4,',','.') }}</td>
</tr>@endforeach
</tbody>
</table>
@foreach($result['rows'] as $row)<h2>{{ $row['unit'] }} — {{ $row['indicator'] }} / v{{ $row['version'] }}</h2>@foreach($row['sources'] as $source)<h3>{{ $source['period'] }}</h3>
<p>{{ $source['explanation'] }} {{ $source['no_activity'] ? '(Tidak ada kegiatan)' : '' }}</p>
<table>
<thead>
<tr>
<th>Uraian</th>@foreach($row['definition']['fields'] as $field)<th>{{ $field['label'] }} ({{ $field['unit'] }})</th>@endforeach
</tr>
</thead>
<tbody>@foreach($source['rows'] as $detail)<tr>
<td>{{ $detail['label'] }}</td>@foreach($row['definition']['fields'] as $field)<td>{{ $detail['values'][$field['code']] ?? '—' }}</td>@endforeach
</tr>@endforeach
</tbody>
</table>@endforeach
 @endforeach
@if($result['missing'])<h2>Data belum terverifikasi</h2>
<ul>@foreach($result['missing'] as $missing)<li>{{ $missing['unit'] }} — {{ $missing['period'] }} — {{ $missing['indicator'] }}</li>@endforeach
</ul>@endif
</body>
</html>
