<svg xmlns="http://www.w3.org/2000/svg" width="90mm" height="140mm" viewBox="0 0 340 529" role="img" aria-labelledby="card-title card-description">
    <title id="card-title">Entrance card for {{ $visitor->full_name ?: 'Verified Visitor' }}</title>
    <desc id="card-description">{{ $eventName }} entrance card with visitor reference {{ $qrPayload }}</desc>
    <defs>
        <clipPath id="card-clip"><rect width="340" height="529" rx="22"/></clipPath>
        <clipPath id="photo-clip"><rect x="113" y="143" width="114" height="128" rx="14"/></clipPath>
        <filter id="photo-shadow" x="-25%" y="-25%" width="150%" height="150%"><feDropShadow dx="0" dy="7" stdDeviation="8" flood-color="#18202b" flood-opacity=".18"/></filter>
    </defs>
    <g clip-path="url(#card-clip)">
        <rect width="340" height="529" fill="#ffffff"/>
        <circle cx="335" cy="525" r="105" fill="#c8e063" opacity=".18"/>
        <rect width="340" height="46" fill="#171a18"/>
        <text x="20" y="29" fill="#ffffff" font-family="Arial, sans-serif" font-size="10" font-weight="700" letter-spacing="1.4">ENTRANCE ID</text>
        <rect x="{{ $cardStatus === 'VERIFIED' ? 261 : 222 }}" y="12" width="{{ $cardStatus === 'VERIFIED' ? 59 : 98 }}" height="22" rx="11" fill="#c8e063"/>
        <text x="{{ $cardStatus === 'VERIFIED' ? 290.5 : 271 }}" y="27" fill="#182000" text-anchor="middle" font-family="Arial, sans-serif" font-size="{{ $cardStatus === 'VERIFIED' ? 8 : 7 }}" font-weight="700">{{ $cardStatus }}</text>

        <image href="{{ $logoDataUri }}" x="52" y="57" width="236" height="58" preserveAspectRatio="xMidYMid meet"/>
        @if($visitor->eventRegistrationDay)
            <text x="170" y="132" fill="#718064" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" font-weight="700">{{ $visitor->eventRegistrationDay->label }} · {{ $visitor->eventRegistrationDay->event_date->format('d M Y') }}</text>
        @endif

        <g filter="url(#photo-shadow)">
            <rect x="108" y="138" width="124" height="138" rx="18" fill="#ffffff"/>
            <rect x="113" y="143" width="114" height="128" rx="14" fill="#edf1e8"/>
            @if($photoDataUri)
                <image href="{{ $photoDataUri }}" x="113" y="143" width="114" height="128" preserveAspectRatio="xMidYMid slice" clip-path="url(#photo-clip)"/>
            @else
                <circle cx="170" cy="191" r="24" fill="none" stroke="#839080" stroke-width="5"/>
                <path d="M127 263c3-35 20-53 43-53s40 18 43 53" fill="none" stroke="#839080" stroke-width="5" stroke-linecap="round"/>
            @endif
        </g>

        <text x="170" y="303" fill="#718064" text-anchor="middle" font-family="Arial, sans-serif" font-size="8" font-weight="700" letter-spacing="1.2">VISITOR NAME</text>
        <foreignObject x="24" y="310" width="292" height="40">
            <div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;align-items:flex-start;justify-content:center;width:292px;height:40px;overflow:hidden;color:#18202b;font:700 18px/1.12 Arial,sans-serif;text-align:center;overflow-wrap:anywhere;">{{ $visitor->full_name ?: 'Verified Visitor' }}</div>
        </foreignObject>
        <text x="85" y="360" fill="#718064" text-anchor="middle" font-family="Arial, sans-serif" font-size="6.5" font-weight="700" letter-spacing=".8">OCCUPATION</text>
        <text x="255" y="360" fill="#718064" text-anchor="middle" font-family="Arial, sans-serif" font-size="6.5" font-weight="700" letter-spacing=".8">COMPANY</text>
        <foreignObject x="10" y="365" width="150" height="32">
            <div xmlns="http://www.w3.org/1999/xhtml" style="width:150px;height:32px;overflow:hidden;color:#18202b;font:700 9px/1.2 Arial,sans-serif;text-align:center;overflow-wrap:anywhere;">{{ $visitor->occupation ?: 'Not provided' }}</div>
        </foreignObject>
        <foreignObject x="180" y="365" width="150" height="32">
            <div xmlns="http://www.w3.org/1999/xhtml" style="width:150px;height:32px;overflow:hidden;color:#18202b;font:700 9px/1.2 Arial,sans-serif;text-align:center;overflow-wrap:anywhere;">{{ $visitor->company ?: $visitor->exhibitorProfile?->company_name ?: $visitor->exhibitorProfile?->name_board ?: 'Not provided' }}</div>
        </foreignObject>

        <path d="M0 411H340" stroke="#d8ded0" stroke-dasharray="4 4"/>
        <svg x="126" y="417" width="88" height="88" viewBox="0 0 250 250" aria-label="QR code">{!! $qrCode !!}</svg>
        <text x="170" y="512" fill="#718064" text-anchor="middle" font-family="Arial, sans-serif" font-size="6.5" font-weight="700" letter-spacing="1">VISITOR REFERENCE</text>
        <text x="170" y="522" fill="#18202b" text-anchor="middle" font-family="Arial, sans-serif" font-size="6.5" font-weight="700">{{ $qrPayload }}</text>
    </g>
</svg>
