<!doctype html>
<html lang="en"><body style="margin:0;background:#f5f7f3;padding:24px;font-family:Arial,sans-serif;color:#172033">
<div style="max-width:720px;margin:auto;background:#fff;border:1px solid #e1e6e9;border-radius:10px;padding:28px">
    <p style="margin:0 0 6px;color:#71800c;font-size:12px;font-weight:bold;letter-spacing:.08em">SCHEDULED DAILY REPORT</p>
    <h1 style="margin:0 0 8px;font-size:24px">{{ $scheduleName }}</h1>
    <p style="margin:0 0 24px;color:#64748b">Report date: {{ $reportDate }}. Detailed CSV files are attached.</p>
    @foreach($reports as $report)
        <section style="margin-top:20px"><h2 style="margin:0 0 6px;font-size:16px">{{ $report['label'] }}</h2><p style="margin:0;color:#475569">{{ $report['summary'] }}</p></section>
    @endforeach
    <p style="margin:28px 0 0;color:#7c8997;font-size:12px">This message was sent automatically by {{ config('app.name') }}.</p>
</div></body></html>
