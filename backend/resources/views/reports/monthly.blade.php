<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#173d3b}h1{font-size:21px;color:#064e48;margin-bottom:4px}.mast{font-size:10px;letter-spacing:2px;color:#0c8174}table{border-collapse:collapse;width:100%;margin-top:18px}th,td{padding:7px;border:1px solid #d8e6df;text-align:left;word-wrap:break-word}th{background:#e9f4ed}tr:nth-child(even){background:#f7faf8}.note{font-size:9px;color:#526e62;line-height:1.6;margin-top:16px}footer{position:fixed;bottom:-15px;font-size:8px;color:#526e62}
</style></head><body><p class="mast">GALMS · HOME AFFAIRS DIVISION</p><h1>{{ ucwords(str_replace('-', ' ', $type)) }}</h1><p>Reporting month: {{ $month }} · Generated {{ now('Asia/Colombo')->format('Y-m-d H:i') }} (Asia/Colombo)</p>
<table><thead><tr>
@foreach($headers as $header)<th>{{ $header }}</th>
@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr>
@foreach($row as $value)<td>{{ $value ?? '—' }}</td>
@endforeach</tr>
@empty<tr><td colspan="{{ count($headers) }}">No matching records. This is not evidence of full attendance or zero leave usage.</td></tr>
@endforelse</tbody></table>

@if($penalties->count())<h2>Monthly late-penalty ledger entries</h2>
@foreach($penalties as $penalty)<p>{{ $penalty->name }}: {{ $penalty->amount }} casual days</p>
@endforeach
@endif
<p class="note">{{ $notes }}</p><footer>Government Attendance &amp; Leave Management System · Authorized administrative use</footer></body></html>
