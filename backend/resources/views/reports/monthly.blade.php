<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body {
    font-family: DejaVu Sans, sans-serif;
    font-size: 8.5px;
    color: #1a2e2b;
    margin: 15px 20px;
  }
  .header-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
    border-bottom: 2px solid #005a4e;
    padding-bottom: 8px;
  }
  .header-table td {
    border: none;
    padding: 0;
    vertical-align: middle;
  }
  .header-logo {
    width: 50px;
  }
  .header-title-box {
    padding-left: 12px;
  }
  .mast {
    font-size: 9px;
    letter-spacing: 2px;
    font-weight: bold;
    color: #00796b;
    text-transform: uppercase;
    margin: 0 0 3px 0;
  }
  h1 {
    font-size: 17px;
    color: #004d40;
    margin: 0 0 4px 0;
    font-weight: 700;
  }
  .meta {
    font-size: 8.5px;
    color: #526e62;
    margin: 0;
  }
  table.data-table {
    border-collapse: collapse;
    width: 100%;
    margin-top: 10px;
  }
  table.data-table th, table.data-table td {
    padding: 5px 6px;
    border: 1px solid #cce2d8;
    text-align: left;
    word-wrap: break-word;
  }
  table.data-table th {
    background: #005a4e;
    color: #ffffff;
    font-weight: bold;
    font-size: 8.5px;
  }
  table.data-table tr:nth-child(even) {
    background: #f4f9f6;
  }
  table.data-table tr.total-row {
    background: #e2f0ea;
    font-weight: bold;
  }
  .note {
    font-size: 8px;
    color: #526e62;
    line-height: 1.5;
    margin-top: 14px;
    padding: 6px 8px;
    background: #f8faf9;
    border-left: 3px solid #005a4e;
  }
  footer {
    position: fixed;
    bottom: -10px;
    left: 0;
    right: 0;
    font-size: 7.5px;
    color: #6c847a;
    text-align: center;
    border-top: 1px solid #d8e6df;
    padding-top: 4px;
  }
</style>
</head>
<body>
<table class="header-table">
  <tr>
    @if(file_exists(public_path('emblem.png')))
    <td class="header-logo">
      <img src="{{ public_path('emblem.png') }}" style="width: 46px; height: auto;" alt="National Emblem">
    </td>
    @endif
    <td class="header-title-box">
      <p class="mast">Ministry of Public Administration &amp; Home Affairs · GALMS</p>
      <h1>{{ $reportTitle ?? ucwords(str_replace('-', ' ', $type)) }}</h1>
      <p class="meta">Reporting Period: <strong>{{ $date ?? $month }}</strong> · Scope: <strong>{{ $scope ?? 'All Active Employees' }}</strong> · Generated: {{ now('Asia/Colombo')->format('Y-m-d H:i') }} (Asia/Colombo) · Active Employees Only</p>
    </td>
  </tr>
</table>

<table class="data-table">
  <thead>
    <tr>
      @foreach($headers as $header)
      <th>{{ $header }}</th>
      @endforeach
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $row)
    <tr @if(isset($row[1]) && str_contains($row[1], 'Division total')) class="total-row" @endif>
      @foreach($row as $value)
      <td>{{ $value ?? '—' }}</td>
      @endforeach
    </tr>
    @empty
    <tr>
      <td colspan="{{ count($headers) }}" style="text-align: center; padding: 14px;">No matching records found for active employees in this reporting period.</td>
    </tr>
    @endforelse
  </tbody>
</table>

@if($penalties->count())
  <h3 style="font-size: 11px; color: #004d40; margin-top: 14px; margin-bottom: 4px;">Monthly Late-Penalty Ledger Entries</h3>
  <table class="data-table" style="width: 50%; margin-top: 4px;">
    <thead>
      <tr>
        <th>Employee Name</th>
        <th>Penalty Deduction</th>
      </tr>
    </thead>
    <tbody>
      @foreach($penalties as $penalty)
      <tr>
        <td>{{ $penalty->name }}</td>
        <td>{{ $penalty->amount }} casual day(s)</td>
      </tr>
      @endforeach
    </tbody>
  </table>
@endif

<p class="note"><strong>Scope &amp; Policy:</strong> {{ $notes }}</p>

<footer>Government Attendance &amp; Leave Management System (GALMS) · Official Document · Confidential</footer>
</body>
</html>
