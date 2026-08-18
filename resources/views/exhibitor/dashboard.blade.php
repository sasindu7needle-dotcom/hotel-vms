<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exhibitor Members - Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page exhibitor-dashboard-page">
@include('layouts.site-header')
<main class="exhibitor-dashboard-shell">
    <header class="exhibitor-dashboard-heading">
        <span class="tagline no-margin">EXHIBITOR REGISTRATION</span>
        <h1>{{ $exhibitor->company_name }}<span>.</span></h1>
        <p>{{ $exhibitor->name_board }} &middot; {{ ucfirst($exhibitor->package) }} package &middot; {{ $exhibitor->members->count() }} of {{ $exhibitor->member_limit }} member cards used</p>
    </header>

    @if(session('status'))
        <div class="manual-flow-success" role="status"><div><strong>{{ session('status') }}</strong></div></div>
    @endif

    @if($errors->any())
        <div class="manual-flow-errors">{{ $errors->first() }}</div>
    @endif

    <section class="exhibitor-members-panel">
        <div class="exhibitor-member-toolbar">
            <div>
                <span>MEMBERS</span>
                <h2>Member administration</h2>
                <p>Add each team member using the existing identity-and-photo registration process.</p>
            </div>
            @if($exhibitor->members->count() < $exhibitor->member_limit)
                <a class="btn btn-primary" href="{{ route('visitor.manual.create', ['exhibitor' => $exhibitor->registration_token]) }}">Add member <span>&rarr;</span></a>
            @else
                <span class="exhibitor-limit">Member limit reached</span>
            @endif
        </div>
        <div class="exhibitor-members-table-wrap">
            <table>
                <thead><tr><th>#</th><th>Photo</th><th>Name</th><th>NIC / ID</th><th>Contact number</th><th>Occupation</th><th>Card</th></tr></thead>
                <tbody>
                @forelse($exhibitor->members as $member)
                    <tr>
                        <td data-label="#">{{ $loop->iteration }}</td>
                        <td data-label="Photo"><div class="member-avatar">{{ mb_strtoupper(mb_substr($member->full_name ?: '?', 0, 1)) }}</div></td>
                        <td data-label="Name"><strong>{{ $member->full_name }}</strong></td>
                        <td data-label="NIC / ID">{{ $member->document_number }}</td>
                        <td data-label="Contact">{{ $member->mobile_number }}</td>
                        <td data-label="Occupation">{{ $member->occupation }}</td>
                        <td data-label="Card"><span class="exhibitor-card-state">Registered</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="exhibitor-members-empty">No members have been added. Use “Add member” to begin registration.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="exhibitor-members-mobile" aria-label="Registered members">
            @forelse($exhibitor->members as $member)
                <article class="exhibitor-mobile-member-card">
                    <div class="exhibitor-mobile-member-heading">
                        <div class="exhibitor-mobile-member-identity">
                            <div class="member-avatar">{{ mb_strtoupper(mb_substr($member->full_name ?: '?', 0, 1)) }}</div>
                            <strong>{{ $member->full_name }}</strong>
                        </div>
                        <span class="exhibitor-card-state">Registered</span>
                    </div>
                    <dl class="exhibitor-mobile-member-details">
                        <div><dt>NIC / ID</dt><dd>{{ $member->document_number ?: '—' }}</dd></div>
                        <div><dt>Contact</dt><dd>{{ $member->mobile_number ?: '—' }}</dd></div>
                        <div><dt>Occupation</dt><dd>{{ $member->occupation ?: '—' }}</dd></div>
                    </dl>
                </article>
            @empty
                <div class="exhibitor-mobile-members-empty">No members have been added. Use “Add member” to begin registration.</div>
            @endforelse
        </div>
    </section>
</main>
<style>
body.exhibitor-dashboard-page { min-height: 100vh; margin: 0; background: #f5f7f3; font-family: Inter, sans-serif; }
body.exhibitor-dashboard-page *, body.exhibitor-dashboard-page *::before, body.exhibitor-dashboard-page *::after { box-sizing: border-box; }
body.exhibitor-dashboard-page .exhibitor-dashboard-shell { display: flex; width: 100%; max-width: 1480px; flex-direction: column; gap: 32px; margin: 0 auto; padding: 56px clamp(32px, 4vw, 64px) 64px; }
.exhibitor-dashboard-heading .tagline, .exhibitor-member-toolbar > div > span { color: #75880d; font: 800 10px Inter, sans-serif; letter-spacing: .1em; }
.exhibitor-dashboard-heading h1 { margin: 10px 0 8px; color: #172033; font-size: clamp(36px, 3vw, 44px); line-height: 1.1; }
.exhibitor-dashboard-heading h1 span { color: #8da719; }
.exhibitor-dashboard-heading p { margin: 0; color: #64748b; font-size: 14px; }

.exhibitor-members-panel { margin-top: 0; overflow: hidden; background: #fff; border: 1px solid #e4e9e2; border-radius: 16px; box-shadow: 0 14px 34px rgba(31, 41, 55, .06); }
.exhibitor-member-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 30px 32px; }
.exhibitor-member-toolbar h2 { margin: 7px 0 6px; color: #172033; font-size: 24px; }
.exhibitor-member-toolbar p { margin: 0; color: #64748b; font-size: 13px; }
.exhibitor-member-toolbar .btn { min-height: 44px; padding: 12px 20px; font-size: 12px; white-space: nowrap; }
.exhibitor-limit { padding: 11px 14px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 11px; font-weight: 800; white-space: nowrap; }

.exhibitor-members-table-wrap { overflow: auto; -webkit-overflow-scrolling: touch; border-top: 1px solid #edf0f2; }
.exhibitor-members-table-wrap table { width: 100%; min-width: 900px; border-collapse: collapse; }
.exhibitor-members-table-wrap th { padding: 16px 22px; color: #fff; background: #5d9bd3; text-align: left; font-size: 11px; font-weight: 800; letter-spacing: .02em; text-transform: uppercase; }
.exhibitor-members-table-wrap td { padding: 17px 22px; color: #334155; border-bottom: 1px solid #edf0f2; font-size: 13px; vertical-align: middle; }
.exhibitor-members-table-wrap tbody tr:last-child td { border-bottom: 0; }
.exhibitor-members-table-wrap tbody tr:hover td { background: #f8fbfd; }
.member-avatar { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; background: #e8f1fa; color: #4679aa; font-size: 13px; font-weight: 800; }
.exhibitor-card-state { color: #4d6b00; font-size: 11px; font-weight: 800; }
.exhibitor-members-empty { padding: 42px !important; color: #94a3b8 !important; text-align: center; }
.exhibitor-members-mobile { display: none; }

@media (max-width: 700px) {
    body.exhibitor-dashboard-page .exhibitor-dashboard-shell { gap: 24px; padding: 40px 20px 48px; }
    .exhibitor-member-toolbar { align-items: flex-start; flex-direction: column; padding: 24px; }
}

@media (max-width: 600px) {
    body.exhibitor-dashboard-page .exhibitor-dashboard-shell { gap: 20px; padding: 28px 16px 40px !important; }
    body.exhibitor-dashboard-page .exhibitor-dashboard-heading h1 { margin: 10px 0; font-size: 34px; }
    body.exhibitor-dashboard-page .exhibitor-dashboard-heading p { font-size: 13px; line-height: 1.55; }
    body.exhibitor-dashboard-page .exhibitor-members-panel { border-radius: 12px; }
    body.exhibitor-dashboard-page .exhibitor-member-toolbar { gap: 16px; padding: 22px 20px !important; }
    body.exhibitor-dashboard-page .exhibitor-member-toolbar h2 { font-size: 21px; }
    body.exhibitor-dashboard-page .exhibitor-member-toolbar p { font-size: 12px; line-height: 1.55; }
    body.exhibitor-dashboard-page .exhibitor-member-toolbar .btn { width: 100%; justify-content: center; }
    body.exhibitor-dashboard-page .exhibitor-members-table-wrap { display: none !important; }
    body.exhibitor-dashboard-page .exhibitor-members-mobile { display: grid !important; gap: 14px; padding: 14px !important; background: #f8fafc; }
    body.exhibitor-dashboard-page .exhibitor-mobile-member-card { padding: 16px !important; border: 1px solid #e6ebef; border-radius: 10px; background: #fff; box-shadow: 0 3px 10px rgba(31, 41, 55, .04); }
    .exhibitor-mobile-member-heading, .exhibitor-mobile-member-identity { display: flex; align-items: center; }
    .exhibitor-mobile-member-heading { justify-content: space-between; gap: 12px; padding-bottom: 14px; border-bottom: 1px solid #edf0f2; }
    .exhibitor-mobile-member-identity { min-width: 0; gap: 10px; color: #172033; font-size: 14px; }
    .exhibitor-mobile-member-identity strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .exhibitor-mobile-member-card .member-avatar { width: 34px; height: 34px; flex: 0 0 34px; font-size: 12px; }
    .exhibitor-mobile-member-details { display: grid; gap: 10px; margin: 14px 0 0; }
    .exhibitor-mobile-member-details > div { display: flex; align-items: baseline; justify-content: space-between; gap: 16px; }
    .exhibitor-mobile-member-details dt { color: #718096; font-size: 10px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .exhibitor-mobile-member-details dd { margin: 0; color: #334155; font-size: 12px; font-weight: 600; text-align: right; word-break: break-word; }
    .exhibitor-mobile-members-empty { padding: 28px 16px; color: #94a3b8; text-align: center; font-size: 12px; }
}
</style>
</body>
</html>
