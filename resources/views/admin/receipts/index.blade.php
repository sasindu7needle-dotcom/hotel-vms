@extends('layouts.admin')

@section('title', 'Receipt Manager')

@section('header')
    <div>
        <span class="tagline no-margin">PAYMENT MANAGEMENT</span>
        <h1>Receipt Manager<span>.</span></h1>
        <p>Find a verified visitor and confirm their entrance payment.</p>
    </div>
@endsection

@section('content')
    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="admin-page-alert admin-alert-danger receipt-error" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <section class="admin-panel receipt-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>VISITOR LOOKUP</span>
                <h2>Search visitor details</h2>
                <p>Use the NIC or the registered mobile number to retrieve a visitor record.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('admin.receipts.index') }}" class="receipt-search-form">
            <label for="receipt-search">NIC or mobile number</label>
            <div class="receipt-search-input">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                <input id="receipt-search" name="search" value="{{ $search }}" maxlength="50" required placeholder="e.g. 199012345678 or +94771234567" autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Search visitor</button>
        </form>
    </section>

    <section class="admin-panel receipt-manual-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>MANUAL REGISTRATIONS</span>
                <h2>Recent manual visitors</h2>
                <p>Select a visitor to view their details and upload the payment slip.</p>
            </div>
        </div>
        @if($manualRegistrations->isEmpty())
            <div class="receipt-manual-empty">No manual registrations are available yet.</div>
        @else
            <div class="receipt-manual-list">
                @foreach($manualRegistrations as $manualVisitor)
                    <a class="{{ $visitor?->id === $manualVisitor->id ? 'active' : '' }}" href="{{ route('admin.receipts.index', ['manual_id' => $manualVisitor->id]) }}">
                        <span class="receipt-manual-avatar">{{ mb_strtoupper(mb_substr($manualVisitor->full_name ?: '?', 0, 1)) }}</span>
                        <span class="receipt-manual-summary">
                            <strong>{{ $manualVisitor->full_name ?: 'Unnamed visitor' }}</strong>
                            <small>{{ $manualVisitor->document_number ?: 'No identity number' }} &middot; {{ $manualVisitor->mobile_number ?: 'No mobile number' }}</small>
                        </span>
                        <em class="{{ $manualVisitor->payment_slip_path ? 'uploaded' : '' }}">{{ $manualVisitor->payment_slip_path ? 'Slip uploaded' : 'Awaiting slip' }}</em>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if($search !== '' && !$visitor)
        <section class="admin-panel receipt-empty-result">
            <div class="receipt-empty-icon">?</div>
            <div><h2>No visitor found</h2><p>No verified visitor matches “{{ $search }}”. Check the NIC or mobile number and try again.</p></div>
        </section>
    @endif

    @if($matches->count() > 1)
        <section class="admin-panel receipt-match-panel">
            <div class="configuration-panel-heading"><div><span>MULTIPLE DAILY REGISTRATIONS</span><h2>Choose the event-day payment</h2><p>This visitor has separate records and payments for multiple dates.</p></div></div>
            <div class="receipt-match-list">
                @foreach($matches as $match)
                    <a class="{{ $visitor?->id === $match->id ? 'active' : '' }}" href="{{ route('admin.receipts.index', ['search' => $search, 'visitor_id' => $match->id]) }}">
                        <strong>{{ $match->eventRegistrationDay?->label ?: 'General registration' }}</strong>
                        <span>{{ $match->eventRegistrationDay?->event_date?->format('d M Y') ?: ($match->created_at?->format('d M Y') ?? 'No date') }}</span>
                        <em>{{ strtoupper($match->payment_status) }} · LKR {{ number_format((float) $match->entrance_fee, 2) }}</em>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($visitor)
        @php
            $mediaVersion = $visitor->updated_at?->format('Uu') ?: $visitor->id;
            $isManualRegistration = $visitor->ocr_provider === 'manual_registration' && $visitor->exhibitor_profile_id === null;
            $photoUrl = $visitor->selfie_path
                ? route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $mediaVersion])
                : $visitor->photo_url;
        @endphp
        <section class="admin-panel receipt-result-panel">
            <div class="configuration-panel-heading">
                <div>
                    <span>VISITOR DETAILS</span>
                    <h2>{{ $visitor->full_name ?: 'Unnamed visitor' }}</h2>
                    <p>{{ strtoupper($visitor->document_type ?: 'Identity document') }} · {{ $visitor->document_number ?: 'No document number' }}</p>
                </div>
                <span class="admin-payment-badge admin-payment-{{ $visitor->payment_status }}">{{ strtoupper(str_replace('_', ' ', $visitor->payment_status)) }}</span>
            </div>

            <div class="receipt-profile-grid">
                <div class="receipt-photo" aria-label="Visitor photo">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}" alt="Photo of {{ $visitor->full_name }}">
                    @else
                        <span>{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</span>
                    @endif
                </div>

                <dl class="receipt-details">
                    <div class="receipt-detail-wide"><dt>Email address</dt><dd>{{ $visitor->email ?: 'Not provided' }}</dd></div>
                    <div><dt>Mobile number</dt><dd>{{ $visitor->mobile_number ?: 'Not provided' }}</dd></div>
                    <div><dt>WhatsApp number</dt><dd>{{ $visitor->whatsapp_number ?: 'Not provided' }}</dd></div>
                    <div class="receipt-detail-wide"><dt>Address</dt><dd>{{ $visitor->address ?: 'Not provided' }}</dd></div>
                    <div><dt>Occupation</dt><dd>{{ $visitor->occupation ?: 'Not provided' }}</dd></div>
                    <div><dt>Company</dt><dd>{{ $visitor->company ?: 'Not provided' }}</dd></div>
                    <div class="receipt-detail-wide"><dt>Visitor category</dt><dd>{{ $visitor->category ?: 'Not assigned' }}</dd></div>
                    @if($visitor->payment_slip_path)
                        <div class="receipt-detail-wide"><dt>Payment slip</dt><dd><a class="receipt-slip-link" href="{{ route('admin.visitors.payment_slip', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" target="_blank" rel="noopener">View uploaded payment slip</a></dd></div>
                    @endif
                    @if($visitor->eventRegistrationDay)<div class="receipt-detail-wide"><dt>Paid event date</dt><dd>{{ $visitor->eventRegistrationDay->label }} · {{ $visitor->eventRegistrationDay->event_date->format('d F Y') }}</dd></div>@endif
                </dl>

                @if($isManualRegistration)
                    <form method="POST" action="{{ route('admin.receipts.payment_slip.store', $visitor) }}" enctype="multipart/form-data" class="receipt-payment-form receipt-slip-form">
                        @csrf
                        <div class="receipt-payment-title"><span>PAYMENT SLIP</span><strong>{{ $visitor->payment_slip_path ? 'Replace payment slip' : 'Upload payment slip' }}</strong></div>
                        <label class="receipt-slip-upload">
                            <span>Payment slip file</span>
                            <input type="file" name="payment_slip" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                            <small>JPG, PNG, WebP, or PDF up to 10 MB.</small>
                        </label>
                        <button type="submit" class="btn btn-primary">{{ $visitor->payment_slip_path ? 'Replace slip' : 'Upload slip' }}</button>
                        @if($visitor->payment_slip_uploaded_at)<small>Last uploaded {{ $visitor->payment_slip_uploaded_at->format('d M Y, h:i A') }}.</small>@endif
                    </form>
                @else
                <form method="POST" action="{{ route('admin.receipts.confirm', $visitor) }}" class="receipt-payment-form">
                    @csrf
                    <div class="receipt-payment-title"><span>PAYMENT DETAILS</span><strong>Confirm entrance payment</strong></div>
                    <label>
                        <span>Entrance fee (LKR)</span>
                        <input type="number" name="entrance_fee" min="0" step="0.01" value="{{ old('entrance_fee', $visitor->entrance_fee ?? '0.00') }}" required>
                    </label>
                    <label>
                        <span>Payment method</span>
                        <select name="payment_method" required>
                            <option value="cash" @selected(old('payment_method', $visitor->payment_method) === 'cash')>Cash</option>
                            <option value="visa_master" @selected(old('payment_method', $visitor->payment_method) === 'visa_master')>Visa / MasterCard</option>
                            <option value="amex" @selected(old('payment_method', $visitor->payment_method) === 'amex')>American Express</option>
                            <option value="ticket" @selected(old('payment_method', $visitor->payment_method) === 'ticket')>Ticket</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary">Confirm payment <span>→</span></button>
                    @if($visitor->payment_status === 'paid')<small>This payment has already been confirmed. Saving will update its amount or method.</small>@endif
                </form>
                @endif
            </div>
        </section>
    @endif
@endsection

@push('styles')
<style>
body.landing-page .receipt-panel,body.landing-page .receipt-manual-panel,body.landing-page .receipt-result-panel,body.landing-page .receipt-empty-result{max-width:1080px}
body.landing-page .receipt-manual-panel{margin-top:20px}.receipt-manual-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:18px 22px}.receipt-manual-list a{display:grid;grid-template-columns:38px minmax(0,1fr) auto;gap:11px;align-items:center;padding:12px 13px;color:#253043;background:#fafbf8;border:1px solid #e1e7da;border-radius:10px;text-decoration:none}.receipt-manual-list a.active{background:#f2f8db;border-color:#bfd659;box-shadow:0 0 0 2px rgba(200,224,99,.25)}.receipt-manual-avatar{display:grid;place-items:center;width:38px;height:38px;color:#536b00;background:#edf5c8;border-radius:9px;font-size:14px;font-weight:800}.receipt-manual-summary{display:grid;gap:4px;min-width:0}.receipt-manual-summary strong,.receipt-manual-summary small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.receipt-manual-summary strong{font-size:11px}.receipt-manual-summary small{color:#64748b;font-size:9px}.receipt-manual-list em{padding:5px 7px;color:#8a5b00;background:#fff7dc;border-radius:999px;font-size:8px;font-style:normal;font-weight:800;white-space:nowrap}.receipt-manual-list em.uploaded{color:#3f6500;background:#eaf5d1}.receipt-manual-empty{padding:22px 28px;color:#7c8997;font-size:11px}
body.landing-page .receipt-match-panel{max-width:1080px;margin-top:20px}.receipt-match-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:18px 22px}.receipt-match-list a{display:grid;gap:5px;padding:13px 14px;color:#253043;background:#fafbf8;border:1px solid #e1e7da;border-radius:10px;text-decoration:none}.receipt-match-list a.active{background:#f2f8db;border-color:#bfd659;box-shadow:0 0 0 2px rgba(200,224,99,.25)}.receipt-match-list strong{font-size:11px}.receipt-match-list span{color:#64748b;font-size:10px}.receipt-match-list em{color:#71800f;font-size:9px;font-style:normal;font-weight:800}
body.landing-page .receipt-search-form{display:grid;grid-template-columns:180px minmax(260px,1fr) auto;gap:14px;align-items:center;padding:24px 28px}
body.landing-page .receipt-search-form>label,body.landing-page .receipt-payment-form label>span{color:#475569;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
body.landing-page .receipt-search-input{display:flex;align-items:center;gap:9px;height:46px;padding:0 13px;background:#fff;border:1px solid #d8e0e7;border-radius:9px}
body.landing-page .receipt-search-input:focus-within{border-color:#a8bd38;box-shadow:0 0 0 3px rgba(200,224,99,.23)}
body.landing-page .receipt-search-input svg{width:17px;height:17px;flex:0 0 auto;fill:none;stroke:#7c8997;stroke-linecap:round;stroke-width:2}
body.landing-page .receipt-search-input input{width:100%;border:0;outline:0;color:#172033;font:500 13px Inter,sans-serif}
body.landing-page .receipt-search-form .btn{height:46px;padding:0 19px;white-space:nowrap}
body.landing-page .receipt-empty-result{display:flex;align-items:center;gap:18px;margin-top:20px;padding:25px 28px}
body.landing-page .receipt-empty-icon{display:grid;place-items:center;width:47px;height:47px;color:#75880d;background:#f1f7d5;border:1px solid #d8e59d;border-radius:13px;font-size:23px;font-weight:800}
body.landing-page .receipt-empty-result h2{margin:0 0 5px;color:#172033;font-size:16px}body.landing-page .receipt-empty-result p{margin:0;color:#7c8997;font-size:11px}
body.landing-page .receipt-result-panel{margin-top:20px}.receipt-profile-grid{display:grid;grid-template-columns:170px minmax(0,1fr) 285px;gap:24px;padding:26px 28px}
body.landing-page .receipt-photo{display:grid;place-items:center;overflow:hidden;width:170px;height:205px;background:#f1f7d5;border:1px solid #d8e59d;border-radius:12px;color:#536b00;font-size:46px;font-weight:800}
body.landing-page .receipt-photo img{width:100%;height:100%;object-fit:cover}
body.landing-page .receipt-details{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;align-content:start;margin:0}.receipt-details div{padding:0 0 12px;border-bottom:1px solid #edf0f2}.receipt-details .receipt-detail-wide{grid-column:1/-1}.receipt-details dt{margin-bottom:5px;color:#7b8795;font-size:9px;font-weight:800;letter-spacing:.7px;text-transform:uppercase}.receipt-details dd{margin:0;color:#253043;font-size:12px;font-weight:600;line-height:1.45}
.receipt-details .receipt-slip-link{display:inline-flex;padding:7px 10px;color:#53620b;background:#f7faeb;border:1px solid #dce8aa;border-radius:7px;font-size:10px;font-weight:800;text-decoration:none}
body.landing-page .receipt-payment-form{display:grid;gap:14px;align-content:start;padding:19px;background:#fafbf8;border:1px solid #e1e7da;border-radius:12px}.receipt-payment-title{padding-bottom:13px;border-bottom:1px solid #e1e7da}.receipt-payment-title span,.receipt-payment-title strong{display:block}.receipt-payment-title span{color:#80920f;font-size:9px;font-weight:800;letter-spacing:.8px}.receipt-payment-title strong{margin-top:5px;color:#172033;font-size:14px}.receipt-payment-form label>span{display:block;margin-bottom:7px}.receipt-payment-form input,.receipt-payment-form select{width:100%;height:42px;padding:0 11px;box-sizing:border-box;color:#172033;background:#fff;border:1px solid #d8e0e7;border-radius:8px;font:600 12px Inter,sans-serif;outline:0}.receipt-payment-form input:focus,.receipt-payment-form select:focus{border-color:#a8bd38;box-shadow:0 0 0 3px rgba(200,224,99,.23)}.receipt-payment-form .btn{height:43px;margin-top:3px}.receipt-payment-form small{color:#7c8997;font-size:10px;line-height:1.45}.receipt-slip-upload input[type=file]{height:auto;padding:9px;font-size:10px}.receipt-slip-upload small{display:block;margin-top:7px}.receipt-error{margin-bottom:20px;padding:14px 18px;background:#fff1f1;border:1px solid #fecaca;border-radius:10px;color:#991b1b;font-size:12px;font-weight:600}
@media(max-width:900px){body.landing-page .receipt-profile-grid{grid-template-columns:150px minmax(0,1fr)}body.landing-page .receipt-photo{width:150px;height:180px}body.landing-page .receipt-payment-form{grid-column:1/-1;grid-template-columns:1fr 1fr}.receipt-payment-title{grid-column:1/-1}.receipt-payment-form .btn,.receipt-payment-form small{grid-column:1/-1}}
@media(max-width:650px){body.landing-page .receipt-search-form{grid-template-columns:1fr;padding:20px 18px}body.landing-page .receipt-search-form .btn{width:100%}.receipt-manual-list{grid-template-columns:1fr;padding:16px}.receipt-manual-list a{grid-template-columns:38px minmax(0,1fr)}.receipt-manual-list em{grid-column:2;justify-self:start}body.landing-page .receipt-profile-grid{grid-template-columns:1fr;padding:20px 18px}body.landing-page .receipt-photo{width:100%;height:210px}body.landing-page .receipt-details{grid-template-columns:1fr}.receipt-details .receipt-detail-wide{grid-column:auto}body.landing-page .receipt-payment-form{grid-column:auto;grid-template-columns:1fr}.receipt-match-list{grid-template-columns:1fr}}
</style>
@endpush
