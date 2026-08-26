<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f5f7f3;color:#172033;font-family:Arial,sans-serif">
<div style="max-width:680px;margin:auto;overflow:hidden;background:#ffffff;border:1px solid #e1e6e9;border-radius:12px">
    <div style="padding:28px 30px;background:#171a18;color:#ffffff">
        <p style="margin:0 0 8px;color:#c8e063;font-size:12px;font-weight:bold;letter-spacing:.08em">PAYMENT CONFIRMED</p>
        <h1 style="margin:0;font-size:26px;line-height:1.25">Thank you for your payment.</h1>
    </div>
    <div style="padding:30px">
        <p style="margin:0 0 16px;line-height:1.6">Hello {{ $visitor->full_name ?: 'Visitor' }},</p>
        <p style="margin:0 0 24px;color:#475569;line-height:1.65">Your payment and registration for <strong>{{ $invoice['event_name'] }}</strong> have been successfully confirmed. Please keep the attached entrance card ready for admission.</p>

        <div style="margin:0 0 24px;padding:18px;background:#f4f8df;border:1px solid #dce6b6;border-radius:10px">
            <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
                <tr><td style="padding:5px 0;color:#64748b">Payment reference</td><td style="padding:5px 0;text-align:right;font-weight:bold">{{ $invoice['payment_reference'] }}</td></tr>
                <tr><td style="padding:5px 0;color:#64748b">Amount paid</td><td style="padding:5px 0;text-align:right;font-weight:bold">{{ $invoice['currency'] }} {{ $invoice['amount'] }}</td></tr>
                <tr><td style="padding:5px 0;color:#64748b">Payment date</td><td style="padding:5px 0;text-align:right;font-weight:bold">{{ $invoice['paid_at'] }}</td></tr>
            </table>
        </div>

        <p style="margin:0 0 8px;font-weight:bold">Attached to this email:</p>
        <ul style="margin:0;padding-left:20px;color:#475569;line-height:1.8">
            <li>Your entrance card (PNG)</li>
            <li>Your paid payment invoice (PDF)</li>
        </ul>
        <p style="margin:26px 0 0;color:#7c8997;font-size:12px;line-height:1.5">This message was sent automatically by {{ config('app.name') }}. Please keep it for your records.</p>
    </div>
</div>
</body>
</html>
