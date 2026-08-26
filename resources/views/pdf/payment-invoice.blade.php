<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 38px; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .header { padding: 22px 24px; background: #171a18; color: #fff; }
        .header h1 { margin: 0 0 5px; font-size: 24px; }
        .header p { margin: 0; color: #c8e063; font-weight: bold; }
        .paid { float: right; margin-top: -42px; padding: 8px 16px; background: #c8e063; color: #172033; border-radius: 14px; font-weight: bold; }
        .section { margin-top: 24px; }
        .meta { width: 100%; border-collapse: collapse; }
        .meta td { width: 50%; padding: 7px 0; vertical-align: top; }
        .label { display: block; margin-bottom: 3px; color: #718064; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .items { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .items th { padding: 10px 12px; background: #f1f6da; color: #526600; text-align: left; font-size: 10px; }
        .items td { padding: 14px 12px; border-bottom: 1px solid #d8ded0; }
        .right { text-align: right !important; }
        .total td { padding-top: 17px; border: 0; font-size: 15px; font-weight: bold; }
        .footer { margin-top: 44px; padding-top: 14px; border-top: 1px solid #d8ded0; color: #64748b; font-size: 10px; line-height: 1.5; }
    </style>
</head>
<body>
<div class="header">
    <h1>Payment Invoice</h1>
    <p>{{ $invoice['invoice_number'] }}</p>
    <span class="paid">PAID</span>
</div>

<div class="section">
    <table class="meta">
        <tr>
            <td><span class="label">Issued to</span>{{ $invoice['visitor_name'] }}<br>{{ $invoice['visitor_email'] }}</td>
            <td><span class="label">Payment date</span>{{ $invoice['paid_at'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Payment reference</span>{{ $invoice['payment_reference'] }}</td>
            <td><span class="label">Gateway transaction</span>{{ $invoice['transaction_id'] }}</td>
        </tr>
        <tr>
            <td><span class="label">Participant reference</span>{{ $invoice['participant_reference'] }}</td>
            <td><span class="label">Payment method</span>{{ $invoice['payment_method'] }}</td>
        </tr>
    </table>
</div>

<table class="items">
    <thead><tr><th>Description</th><th>Event date</th><th class="right">Amount</th></tr></thead>
    <tbody>
        <tr><td>{{ $invoice['event_name'] }} entrance fee</td><td>{{ $invoice['event_date'] }}</td><td class="right">{{ $invoice['currency'] }} {{ $invoice['amount'] }}</td></tr>
        <tr class="total"><td colspan="2" class="right">Total paid</td><td class="right">{{ $invoice['currency'] }} {{ $invoice['amount'] }}</td></tr>
    </tbody>
</table>

<div class="footer">Thank you for your payment. This invoice was generated electronically after successful payment confirmation by {{ config('app.name') }}.</div>
</body>
</html>
