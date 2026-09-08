<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Card — {{ $visitor->full_name ?: 'Visitor' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --lime: #c8e063;
            --ink: #18202b;
            --muted: #718064;
        }
        * {
            box-sizing: border-box;
        }
        html {
            background: #eef1ec;
        }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 28px;
            background: #eef1ec;
            color: var(--ink);
            font-family: Inter, Arial, sans-serif;
        }
        .print-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: min(100%, 430px);
            margin: 0 auto 18px;
        }
        .print-toolbar a,
        .print-toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 17px;
            border: 1px solid #cad2d8;
            border-radius: 9px;
            background: #fff;
            color: #344054;
            font: 700 12px Inter, Arial, sans-serif;
            text-decoration: none;
            cursor: pointer;
        }
        .print-toolbar button {
            border-color: #afcb3d;
            background: var(--lime);
            color: #182000;
        }
        .visitor-card {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 90mm;
            height: 140mm;
            margin: auto;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(24,32,43,.1);
            border-radius: 6mm;
            box-shadow: 0 22px 60px rgba(24,32,43,.18);
            isolation: isolate;
        }
        .generated-card-preview {
            display: block;
            width: 80mm;
            height: 140mm;
            margin: auto;
            object-fit: fill;
            background: #fff;
            border: 1px solid rgba(24,32,43,.1);
            border-radius: 6mm;
            box-shadow: 0 22px 60px rgba(24,32,43,.18);
        }
        .visitor-card::after {
            position: absolute;
            z-index: -1;
            right: -25mm;
            bottom: -25mm;
            width: 58mm;
            height: 58mm;
            background: var(--lime);
            border-radius: 50%;
            content: '';
            opacity: .18;
        }
        .visitor-card.has-category-artwork::before {
            position: absolute;
            z-index: -2;
            inset: 0;
            background-image: linear-gradient(rgba(255,255,255,.58), rgba(255,255,255,.58)), var(--category-card-artwork);
            background-position: center;
            background-size: cover;
            content: '';
        }
        .visitor-card.has-category-artwork .card-event,
        .visitor-card.has-category-artwork .card-name,
        .visitor-card.has-category-artwork .card-qr {
            background: rgba(255,255,255,.52);
        }
        .card-topbar {
            display: flex;
            flex: 0 0 12mm;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0 5mm;
            color: #fff;
            background: #171a18;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: .12em;
        }
        .card-topbar span:last-child {
            padding: 1.5mm 2.5mm;
            color: #182000;
            background: var(--lime);
            border-radius: 20mm;
            font-size: 6pt;
        }
        .card-event {
            display: flex;
            flex: 0 0 17mm;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1.5mm 5mm 1mm;
            text-align: center;
        }
        .card-name > span,
        .card-visitor-details span,
        .card-qr > span {
            display: block;
            color: var(--muted);
            font-size: 6pt;
            font-weight: 800;
            letter-spacing: .12em;
        }
        .card-event img {
            display: block;
            width: 62mm;
            max-width: 100%;
            max-height: 11mm;
            object-fit: contain;
        }
        .card-event small {
            display: block;
            margin-top: 1mm;
            color: var(--muted);
            font-size: 6pt;
            font-weight: 800;
        }
        .card-photo {
            display: flex;
            flex: 0 0 34mm;
            align-items: center;
            justify-content: center;
            width: 30mm;
            height: 34mm;
            max-width: 30mm;
            max-height: 34mm;
            margin: 0 auto;
            overflow: hidden;
            background: #edf1e8;
            border: 1.2mm solid #fff;
            border-radius: 3.5mm;
            box-shadow: 0 2mm 6mm rgba(24,32,43,.16);
        }
        .card-photo img {
            display: block;
            width: 100%;
            height: 100%;
            min-width: 100%;
            min-height: 100%;
            object-fit: cover;
            object-position: center;
        }
        .card-photo strong {
            color: #839080;
            font-size: 24pt;
        }
        .card-name {
            display: flex;
            flex: 0 0 31mm;
            flex-direction: column;
            align-items: center;
            width: 100%;
            padding: 2.5mm 5mm 1.5mm;
            text-align: center;
        }
        .card-name h2 {
            display: -webkit-box;
            max-width: 78mm;
            max-height: 13.5mm;
            margin: 1.2mm 0 1.8mm;
            overflow: hidden;
            font-size: 12pt;
            line-height: 1.12;
            overflow-wrap: anywhere;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }
        .card-visitor-details {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .3mm;
            overflow: hidden;
            background: #f1f6da;
            border-radius: 2.5mm;
        }
        .card-visitor-details div {
            min-width: 0;
            padding: 1.4mm 1.8mm;
            background: rgba(255,255,255,.28);
        }
        .card-visitor-details span {
            font-size: 5pt;
        }
        .card-visitor-details strong {
            display: block;
            margin-top: .7mm;
            overflow: hidden;
            font-size: 6.5pt;
            line-height: 1.15;
            overflow-wrap: anywhere;
        }
        .card-qr {
            position: relative;
            z-index: 1;
            display: flex;
            flex: 1 1 auto;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 0;
            padding: 2.5mm 5mm 3mm;
            border-top: .3mm dashed #d8ded0;
            text-align: center;
        }
        .card-qr div[role="img"] {
            line-height: 0;
        }
        .card-qr svg {
            display: block;
            width: 25mm;
            height: 25mm;
            margin: 0 auto 1.5mm;
        }
        .card-qr strong {
            display: block;
            max-width: 76mm;
            margin: 1mm auto 0;
            font-size: 5.5pt;
            line-height: 1.2;
            letter-spacing: .02em;
            overflow-wrap: anywhere;
        }
        @page {
            size: 90mm 140mm;
            margin: 0;
        }
        @media print {
            html,
            body {
                width: 90mm !important;
                min-width: 90mm !important;
                max-width: 90mm !important;
                height: 140mm !important;
                min-height: 140mm !important;
                max-height: 140mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: #fff !important;
            }
            html,
            body,
            .visitor-card,
            .visitor-card *,
            .generated-card-preview {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-toolbar {
                display: none !important;
            }
            .visitor-card {
                width: 90mm !important;
                min-width: 90mm !important;
                max-width: 90mm !important;
                height: 140mm !important;
                min-height: 140mm !important;
                max-height: 140mm !important;
                margin: 0 !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .generated-card-preview {
                width: 40mm !important;
                min-width: 40mm !important;
                max-width: 40mm !important;
                height: 70mm !important;
                min-height: 70mm !important;
                max-height: 70mm !important;
                margin: 0 auto !important;
                border: 0 !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
        }
        @media screen and (max-width: 480px) {
            body {
                padding: 16px;
            }
            .visitor-card {
                width: min(90mm, 100%);
            }
            .generated-card-preview {
                width: min(80mm, 100%);
                height: auto;
                aspect-ratio: 4 / 7;
            }
        }
        @if($cardArtworkAvailable)
        @page {
            size: 40mm 70mm;
            margin: 0;
        }
        @media print {
            html,
            body {
                width: 40mm !important;
                min-width: 40mm !important;
                max-width: 40mm !important;
                height: 70mm !important;
                min-height: 70mm !important;
                max-height: 70mm !important;
            }
        }
        @endif
    </style>
</head>
<body>
    <nav class="print-toolbar" aria-label="Print controls">
        <a href="{{ route('admin.visitors.index') }}">Back to Visitors</a>
        <a href="{{ route('admin.visitors.card.download', $visitor) }}">Download PNG</a>
        <button type="button" onclick="printCard()">Print Card</button>
    </nav>

    @if($cardArtworkAvailable)
    <img
        class="generated-card-preview"
        src="{{ route('admin.visitors.card.preview', ['visitor' => $visitor, 'v' => $visitor->updated_at?->format('Uu') ?: $visitor->id]) }}"
        alt="Generated {{ $visitor->visitorCategory?->name ?: $visitor->category }} card for {{ $visitor->full_name }}"
    >
    @else
    <article class="visitor-card" aria-label="Printable visitor card">
        <div class="card-topbar"><span>{{ mb_strtoupper(($visitor->visitorCategory?->name ?: $visitor->category ?: 'Entrance').' Pass') }}</span><span>{{ $visitor->is_blocked ? 'BLOCKED' : 'VERIFIED' }}</span></div>
        <header class="card-event"><img src="{{ asset('img/logo.png') }}" alt="Institute of Hospitality">@if($visitor->eventRegistrationDay)<small>{{ $visitor->eventRegistrationDay->label }} · {{ $visitor->eventRegistrationDay->event_date->format('d M Y') }}</small>@endif</header>
        <div class="card-photo">
            @if($visitor->selfie_path)
                <img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $visitor->updated_at?->format('Uu') ?: $visitor->id]) }}" alt="Captured visitor photo of {{ $visitor->full_name }}">
            @else
                <strong aria-label="No visitor photo">{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</strong>
            @endif
        </div>
        <div class="card-name">
            <span>VISITOR NAME</span>
            <h2>{{ $visitor->full_name ?: 'Verified Visitor' }}</h2>
            <div class="card-visitor-details">
                <div><span>OCCUPATION</span><strong>{{ $visitor->occupation ?: 'Not provided' }}</strong></div>
                <div><span>COMPANY</span><strong>{{ $visitor->company ?: $visitor->exhibitorProfile?->company_name ?: $visitor->exhibitorProfile?->name_board ?: 'Not provided' }}</strong></div>
            </div>
        </div>
        <div class="card-qr">
            <div role="img" aria-label="QR code for visitor ID {{ $qrPayload }}">{!! $qrCode !!}</div>
            <span>{{ $visitor->ticket_number ? 'TICKET NUMBER' : 'PARTICIPANT REFERENCE NUMBER' }}</span>
            <strong>{{ $visitor->ticket_number ?: $qrPayload }}</strong>
        </div>
    </article>
    @endif
    <script>
        async function printCard() {
            if (document.fonts && document.fonts.ready) {
                await document.fonts.ready;
            }

            await Promise.all(
                Array.from(document.images)
                    .filter(image => !image.complete)
                    .map(image => new Promise(resolve => {
                        image.addEventListener('load', resolve, { once: true });
                        image.addEventListener('error', resolve, { once: true });
                    }))
            );

            window.print();
        }
    </script>
</body>
</html>
