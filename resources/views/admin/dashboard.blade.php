<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        body.landing-page .admin-capacity-control{display:grid;grid-template-columns:48px minmax(0,1fr) auto;align-items:center;gap:16px;min-height:104px;margin:0 0 18px;padding:20px 22px;border:1px solid #dce3e8;border-left:5px solid #c8e063;border-radius:13px;background:linear-gradient(105deg,#f9fce9 0,#fff 48%);box-shadow:0 9px 26px rgba(24,33,46,.06)}
        body.landing-page .admin-capacity-icon{display:grid;width:46px;height:46px;place-items:center;color:#506400;background:#eaf4b9;border:1px solid #d1e080;border-radius:12px}
        body.landing-page .admin-capacity-icon svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        body.landing-page .admin-capacity-copy{min-width:0}
        body.landing-page .admin-capacity-copy>span{display:block;margin-bottom:6px;color:#75830f;font-size:9px;font-weight:800;letter-spacing:.1em}
        body.landing-page .admin-capacity-copy strong{display:block;color:#172000;font-size:18px;line-height:1.25}
        body.landing-page .admin-capacity-copy strong b{font-size:24px}
        body.landing-page .admin-capacity-copy small{display:block;margin-top:5px;color:#75808d;font-size:11px;line-height:1.45}
        body.landing-page .admin-capacity-form{display:flex;align-items:end;gap:9px}
        body.landing-page .admin-capacity-form label{display:flex;flex-direction:column;gap:6px;color:#657080;font-size:9px;font-weight:800;letter-spacing:.06em}
        body.landing-page .admin-capacity-form input{width:136px;height:44px;padding:0 12px;border:1px solid #ccd5dc;border-radius:9px;background:#fff;font:800 14px Inter,sans-serif;outline:none}
        body.landing-page .admin-capacity-form input:focus{border-color:#aecb37;box-shadow:0 0 0 3px rgba(200,224,99,.25)}
        body.landing-page .admin-capacity-form button,body.landing-page .admin-capacity-control>.btn{display:inline-flex;min-height:44px;align-items:center;justify-content:center;white-space:nowrap}
        body.landing-page .admin-inside-breakdown{grid-template-columns:repeat(3,minmax(0,1fr))}
        body.landing-page .admin-live-inside{margin:0 0 24px;padding:24px;background:#fff;border:1px solid #e1e6e9;border-radius:13px;box-shadow:0 10px 28px rgba(20,28,38,.05)}
        body.landing-page .admin-live-inside-heading{margin-bottom:24px;text-align:center}
        body.landing-page .admin-live-inside-heading>span{display:block;color:#80920f;font-size:9px;font-weight:800;letter-spacing:.12em}
        body.landing-page .admin-live-inside-heading h2{margin:6px 0 5px;color:#111;font-size:22px;font-weight:800}
        body.landing-page .admin-live-inside-heading p{color:#7b8795;font-size:11px}
        body.landing-page .admin-live-category+.admin-live-category{margin-top:24px;padding-top:22px;border-top:1px solid #edf0f2}
        body.landing-page .admin-live-category-heading{display:flex;align-items:center;gap:10px;margin-bottom:13px}
        body.landing-page .admin-live-category-heading h3{color:#19212c;font-size:15px;font-weight:800}
        body.landing-page .admin-live-category-heading strong{display:inline-flex;min-width:28px;height:22px;align-items:center;justify-content:center;padding:0 7px;color:#111;background:#c8e063;border-radius:50px;font-size:10px;font-weight:800}
        body.landing-page .admin-live-profile-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(68px,1fr));gap:12px}
        body.landing-page .admin-live-profile{position:relative;display:block;min-width:0;margin:0;padding:0;aspect-ratio:1;overflow:hidden;cursor:pointer;background:#e9eef2;border:2px solid #d8e2e8;border-radius:9px;box-shadow:0 3px 9px rgba(20,28,38,.07);font:inherit;text-align:inherit}
        body.landing-page .admin-live-profile:hover,body.landing-page .admin-live-profile:focus-visible{border-color:#a9c630;box-shadow:0 0 0 3px rgba(200,224,99,.35),0 3px 9px rgba(20,28,38,.07);outline:none}
        body.landing-page .admin-live-profile img{width:100%;height:100%;object-fit:cover}
        body.landing-page .admin-live-profile-initial{display:grid;width:100%;height:100%;place-items:center;color:#405000;background:#edf6c7;font-size:21px;font-weight:800}
        body.landing-page .admin-live-profile-caption{position:absolute;right:0;bottom:0;left:0;padding:14px 5px 5px;overflow:hidden;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.8));font-size:8px;font-weight:700;line-height:1.2;text-align:center;text-overflow:ellipsis;white-space:nowrap;opacity:0;transition:opacity .16s ease}
        body.landing-page .admin-live-profile:hover .admin-live-profile-caption,.admin-live-profile:focus-visible .admin-live-profile-caption{opacity:1}
        body.landing-page .admin-live-empty{display:flex;grid-column:1/-1;min-height:70px;align-items:center;justify-content:center;color:#8894a0;background:#fafbfb;border:1px dashed #d9e0e5;border-radius:9px;font-size:11px;font-weight:600}
        body.landing-page .admin-profile-modal[hidden]{display:none}.admin-profile-modal{position:fixed;z-index:1000;inset:0;display:grid;place-items:center;padding:20px;background:rgba(13,20,29,.62);backdrop-filter:blur(3px)}.admin-profile-modal-dialog{width:min(760px,100%);max-height:min(780px,calc(100vh - 40px));overflow:auto;border:1px solid #dfe5e8;border-radius:18px;background:#fff;box-shadow:0 28px 80px rgba(0,0,0,.32)}.admin-profile-modal-head{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:20px 26px;border-bottom:1px solid #edf0f2;background:linear-gradient(105deg,#f8fce9,#fff 57%)}.admin-profile-modal-head span{display:block;margin-bottom:5px;color:#788d0d;font-size:9px;font-weight:800;letter-spacing:.12em}.admin-profile-modal-head h2{margin:0;color:#18212c;font-size:19px;line-height:1.2}.admin-profile-modal-close{display:grid;width:36px;height:36px;flex:0 0 auto;place-items:center;cursor:pointer;color:#52606d;border:1px solid #d9e0e5;border-radius:50%;background:#fff;font-size:24px;line-height:1;transition:.15s}.admin-profile-modal-close:hover,.admin-profile-modal-close:focus-visible{color:#1a2732;border-color:#b8ce4c;outline:3px solid rgba(200,224,99,.3)}.admin-profile-modal-body{padding:24px 26px 28px}.admin-profile-summary{display:grid;grid-template-columns:86px minmax(0,1fr) auto;align-items:center;gap:17px;margin-bottom:22px;padding:17px;border:1px solid #e1e8da;border-radius:14px;background:linear-gradient(115deg,#fbfdf4,#fff)}.admin-profile-avatar{display:grid;width:82px;height:82px;place-items:center;overflow:hidden;color:#405000;background:#edf6c7;border:3px solid #d7e99a;border-radius:14px;font-size:30px;font-weight:800;box-shadow:0 5px 14px rgba(39,57,14,.12)}.admin-profile-avatar img{width:100%;height:100%;object-fit:cover}.admin-profile-identity{min-width:0}.admin-profile-identity strong{display:block;overflow-wrap:anywhere;color:#17202c;font-size:19px;line-height:1.25}.admin-profile-identity small{display:block;margin-top:6px;color:#718091;font-size:12px;font-weight:600}.admin-profile-status{display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:5px 10px;color:#405000;background:#e7f2bc;border:1px solid #d0e27c;border-radius:999px;font-size:10px;font-weight:800}.admin-profile-status:before{width:6px;height:6px;border-radius:50%;background:#789310;content:''}.admin-profile-current-arrival{min-width:143px;padding:11px 13px;border-left:1px solid #dfe7d3}.admin-profile-current-arrival span{display:block;margin-bottom:5px;color:#7c8c99;font-size:9px;font-weight:800;letter-spacing:.08em}.admin-profile-current-arrival strong{display:block;color:#33404d;font-size:12px;line-height:1.45}.admin-profile-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-bottom:25px}.admin-profile-detail{min-height:72px;padding:14px 15px;border:1px solid #e2e8eb;border-radius:10px;background:#fff;box-shadow:0 2px 5px rgba(20,28,38,.025)}.admin-profile-detail span{display:block;margin-bottom:6px;color:#82909b;font-size:9px;font-weight:800;letter-spacing:.08em}.admin-profile-detail strong{display:block;overflow-wrap:anywhere;color:#26313d;font-size:12px;font-weight:700;line-height:1.35}.admin-profile-history{padding-top:2px}.admin-profile-history h3{margin:0 0 12px;color:#19212c;font-size:16px}.admin-profile-history>div:not(.admin-profile-empty){overflow:hidden;border:1px solid #e2e8eb;border-radius:11px}.admin-profile-history table{width:100%;border-collapse:collapse;font-size:12px}.admin-profile-history th{padding:11px 12px;color:#718091;background:#f7f9fa;font-size:9px;font-weight:800;letter-spacing:.07em;text-align:left}.admin-profile-history td{padding:12px;color:#3c4a58;border-top:1px solid #edf0f2}.admin-profile-history td:first-child{color:#26313d;font-weight:800}.admin-profile-history tr:first-child td:nth-child(2){color:#4d6600;font-weight:800}.admin-profile-empty{padding:16px;border:1px dashed #d9e0e5;border-radius:9px;color:#7c8995;font-size:12px;text-align:center}
        /* Deliberate spacing and borders keep each profile section easy to scan. */
        .admin-profile-modal-body{display:flex;flex-direction:column;gap:20px;padding:28px 30px 32px}.admin-profile-summary{margin-bottom:0}.admin-profile-details{gap:12px;margin:0;padding:12px;border:1px solid #e0e7ea;border-radius:14px;background:#f8faf9}.admin-profile-detail{min-height:74px;border-color:#dfe6e9}.admin-profile-history{padding:20px;border:1px solid #e0e7ea;border-radius:14px;background:#fff}.admin-profile-history h3{padding-bottom:12px;border-bottom:1px solid #edf0f2}.admin-profile-history>div:not(.admin-profile-empty){border-color:#dfe6e9}
        .admin-dashboard-message{margin:0 0 14px;padding:11px 14px;border:1px solid #cbdc83;border-radius:9px;background:#f4f9dd;color:#405000;font-size:12px;font-weight:700}.admin-dashboard-message.error{border-color:#efb7bc;background:#fff0f1;color:#94232d}
        @media(max-width:900px){body.landing-page .admin-capacity-control{grid-template-columns:46px minmax(0,1fr)}body.landing-page .admin-capacity-form,body.landing-page .admin-capacity-control>.btn{grid-column:1/-1;width:100%}body.landing-page .admin-capacity-form label{flex:1}body.landing-page .admin-capacity-form input{width:100%}}
        @media(max-width:700px){body.landing-page .admin-inside-breakdown{grid-template-columns:1fr}body.landing-page .admin-live-inside{padding:18px 15px}body.landing-page .admin-live-profile-grid{grid-template-columns:repeat(auto-fill,minmax(58px,1fr));gap:9px}body.landing-page .admin-capacity-control{grid-template-columns:40px minmax(0,1fr);padding:17px 16px}body.landing-page .admin-capacity-icon{width:40px;height:40px}.admin-profile-modal{padding:10px}.admin-profile-modal-head,.admin-profile-modal-body{padding:19px}.admin-profile-summary{grid-template-columns:70px minmax(0,1fr);padding:14px}.admin-profile-avatar{width:68px;height:68px}.admin-profile-current-arrival{grid-column:1/-1;min-width:0;padding:10px 0 0;border-top:1px solid #dfe7d3;border-left:0}.admin-profile-details{grid-template-columns:1fr}.admin-profile-history{overflow-x:auto}.admin-profile-history table{min-width:490px}}
        @media(max-width:460px){body.landing-page .admin-capacity-form{align-items:stretch;flex-direction:column}body.landing-page .admin-capacity-form button{width:100%}}
    </style>
</head>
<body class="landing-page admin-dashboard-page">
@include('layouts.site-header')
    <div class="admin-dashboard-shell">
        <aside id="adminSidebar" class="admin-sidebar">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand admin-sidebar-brand"><span class="admin-brand-mark"></span><span>TRACTION <strong>GUEST</strong></span></a>
            @php
                $permissions = session('admin_permissions', []);
                $isSuperadmin = session('superadmin_authenticated', false);
                $canAccess = fn ($permission) => $isSuperadmin || empty($permissions) || in_array($permission, (array) $permissions);
                $canAccessReceipts = $canAccess('Receipt Manager') || $canAccess('Visitors');
                $canAccessRevenue = $canAccess('Revenue Summary') || $canAccess('Revenue Detail') || $canAccessReceipts;
            @endphp
            <nav aria-label="Admin navigation">
                @if($canAccess('Dashboard'))
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-link active"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
                @endif
                @if($canAccess('Visitors'))
                    <a href="{{ route('admin.visitors.index') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 1 0 7.75"></path></svg><span>Visitors</span></a>
                    <a href="{{ route('visitor.manual.create') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path><rect x="3" y="3" width="18" height="18" rx="4"></rect></svg><span>Manual Registration</span></a>
                    <div class="admin-nav-group collapsed">
                        <button type="button" class="admin-nav-group-title" aria-expanded="false"><svg class="admin-nav-group-icon" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="15" rx="2"></rect><path d="M3 10h18M8 5V3M16 5V3"></path></svg><span>Exhibitors</span><svg class="admin-nav-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg></button>
                        <div class="admin-nav-subtabs"><a href="{{ route('admin.exhibitors.index') }}">Exhibitor Access</a><a href="{{ route('admin.exhibitors.directory') }}">Exhibitor Directory</a></div>
                    </div>
                @endif
                @if($canAccess('Attendance Summary') || $canAccess('Attendance Detail'))
                <div class="admin-nav-group collapsed">
                    <button type="button" class="admin-nav-group-title" aria-expanded="false"><svg class="admin-nav-group-icon" viewBox="0 0 24 24"><path d="M8 3v3M16 3v3M4 9h16"></path><rect x="4" y="5" width="16" height="16" rx="2"></rect><path d="M8 13h.01M12 13h.01M16 13h.01"></path></svg><span>Attendance</span><svg class="admin-nav-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg></button>
                    <div class="admin-nav-subtabs">
                        @if($canAccess('Attendance Summary'))<a href="{{ route('admin.attendance.entries') }}">Count-wise Register</a><a href="{{ route('admin.attendance.summary') }}">Attendance Summary</a>@endif
                        @if($canAccess('Attendance Detail'))<a href="{{ route('admin.attendance.detail') }}">Attendance Detail</a><a href="{{ route('admin.attendance.detail_with_photo') }}">Detail with Photos</a>@endif
                    </div>
                </div>
                @endif
                @if($canAccessRevenue)
                <div class="admin-nav-group collapsed">
                    <button type="button" class="admin-nav-group-title" aria-expanded="false"><svg class="admin-nav-group-icon" viewBox="0 0 24 24"><path d="M4 19V9M10 19V5M16 19v-7M22 19H2"></path></svg><span>Revenue</span><svg class="admin-nav-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg></button>
                    <div class="admin-nav-subtabs">@if($canAccess('Revenue Summary') || $canAccessReceipts)<a href="{{ route('admin.revenue.summary') }}">Revenue Summary</a>@endif @if($canAccess('Revenue Detail') || $canAccessReceipts)<a href="{{ route('admin.revenue.detail') }}">Revenue Detail</a>@endif</div>
                </div>
                @endif
                @if($canAccessReceipts)
                    <a href="{{ route('admin.receipts.index') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-3-6 3V3Z"></path><path d="M9 8h6M9 12h5"></path></svg><span>Receipt Manager</span></a>
                @endif
                @if($canAccess('Event Configurations') || $canAccess('Occupancy Limit') || $canAccess('Visitor Categories') || $canAccess('Users & Access'))
                <div class="admin-nav-group @if(request()->routeIs('admin.configurations*')) active @else collapsed @endif">
                    <button type="button" class="admin-nav-group-title" aria-expanded="{{ request()->routeIs('admin.configurations*') ? 'true' : 'false' }}">
                        <svg class="admin-nav-group-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34A1.7 1.7 0 0 0 14 20.92V21h-4v-.08A1.7 1.7 0 0 0 9 19.37l-1.94.4-2.83-2.83.4-1.94A1.7 1.7 0 0 0 3.08 14H3v-4h.08A1.7 1.7 0 0 0 4.63 9l-.4-1.94 2.83-2.83L9 4.63A1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15 4.63l1.94-.4 2.83 2.83-.4 1.94A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"></path></svg>
                        <span>Master Configurations</span>
                        <svg class="admin-nav-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg>
                    </button>
                    <div class="admin-nav-subtabs">
                        @if($canAccess('Event Configurations'))<a href="{{ route('admin.configurations.event.edit') }}">Event Configurations</a>@endif
                        @if($canAccess('Event Configurations'))<a href="{{ route('admin.configurations.event.edit') }}#daily-registration-forms">Daily Registration Forms</a>@endif
                        @if($canAccess('Occupancy Limit'))<a href="{{ route('admin.configurations.capacity.edit') }}">Occupancy Limit</a>@endif
                        @if($canAccess('Visitor Categories'))<a href="{{ route('admin.configurations.categories.index') }}">Visitor Categories</a>@endif
                        @if($canAccess('Users & Access'))<a href="{{ route('admin.configurations.users.index') }}">Users &amp; Access</a>@endif
                    </div>
                </div>
                @endif
            </nav>
            <form action="{{ route('admin.logout') }}" method="POST" class="admin-logout-form">@csrf<button type="submit" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"></path></svg><span>Sign Out</span></button></form>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <button id="adminMenuToggle" class="admin-menu-toggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
                <div><span class="tagline no-margin">ADMIN OVERVIEW</span><h1>Visitor Dashboard<span>.</span></h1><p>{{ now()->format('l, F j, Y') }}</p></div>
                <div class="admin-user-chip"><span>A</span><div><strong>{{ session('admin_username') }}</strong><small>{{ session('admin_role', 'Administrator') }}</small></div></div>
            </header>

            @if(session('status'))<div class="admin-dashboard-message" role="status">{{ session('status') }}</div>@endif
            @error('inside_count')<div class="admin-dashboard-message error" role="alert">{{ $message }}</div>@enderror
            <section class="admin-live-inside" aria-labelledby="liveInsideTitle">
                <div class="admin-live-inside-heading"><span>LIVE INSIDE</span><h2 id="liveInsideTitle">Participants currently at the event</h2><p>Profiles are grouped by participant category and reflect the latest gate activity.</p></div>
                @foreach($insideCategories as $group)
                    <section class="admin-live-category" aria-labelledby="{{ $group['key'] }}InsideTitle">
                        <div class="admin-live-category-heading"><h3 id="{{ $group['key'] }}InsideTitle">{{ $group['label'] }}</h3><strong data-live-category-count="{{ $group['key'] }}">{{ number_format($group['participants']->count()) }}</strong></div>
                        <div class="admin-live-profile-grid">
                            @forelse($group['participants'] as $participant)
                                @php
                                    $mediaVersion = $participant->updated_at?->format('Uu');
                                    $imageUrl = $participant->selfie_path
                                        ? route('admin.visitors.selfie', ['visitor' => $participant, 'v' => $mediaVersion])
                                        : $participant->photo_url;
                                    $activity = $participant->activity_rows->map(fn ($row) => [
                                        'date' => $row['in']->scanned_at->format('d M Y'),
                                        'check_in' => $row['in']->scanned_at->format('g:i A'),
                                        'check_in_gate' => $row['in']->gate ?: '—',
                                        'check_out' => $row['out']?->scanned_at?->format('g:i A') ?: 'Currently inside',
                                        'check_out_gate' => $row['out']?->gate ?: '—',
                                        'duration' => $row['duration_minutes'] === null ? 'In progress' : $row['duration_minutes'].' min',
                                    ])->values();
                                    $profile = [
                                        'name' => $participant->full_name ?: 'Unnamed participant',
                                        'initial' => mb_strtoupper(mb_substr($participant->full_name ?: '?', 0, 1)),
                                        'image' => $imageUrl,
                                        'category' => $participant->visitorCategory?->name ?: ($participant->category ?: 'Visitor'),
                                        'verification_id' => $participant->verification_id ?: '—',
                                        'document' => $participant->document_number ?: '—',
                                        'mobile' => $participant->mobile_number ?: '—',
                                        'company' => $participant->company ?: '—',
                                        'occupation' => $participant->occupation ?: '—',
                                        'checked_in_at' => $participant->checked_in_at?->format('d M Y, g:i A') ?: '—',
                                        'activity' => $activity,
                                    ];
                                @endphp
                                <button type="button" class="admin-live-profile" title="View {{ $profile['name'] }} details" aria-label="View {{ $profile['name'] }} details" data-live-profile='@json($profile)'>
                                    @if($participant->selfie_path)
                                        <img src="{{ route('admin.visitors.selfie', ['visitor' => $participant, 'v' => $mediaVersion]) }}" alt="Profile photo of {{ $participant->full_name ?: 'participant' }}" loading="lazy">
                                    @elseif($participant->photo_url)
                                        <img src="{{ $participant->photo_url }}" alt="Profile photo of {{ $participant->full_name ?: 'participant' }}" loading="lazy">
                                    @else
                                        <span class="admin-live-profile-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($participant->full_name ?: '?', 0, 1)) }}</span>
                                    @endif
                                    <span class="admin-live-profile-caption">{{ $participant->full_name ?: 'Unnamed participant' }}</span>
                                </button>
                            @empty
                                <div class="admin-live-empty">No {{ strtolower($group['label']) }} are currently inside.</div>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </section>
            <section class="admin-capacity-control" aria-label="Event occupancy control">
                <div class="admin-capacity-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path><path d="M2 21v-2a6 6 0 0 1 12 0v2"></path><path d="M17 8v6M14 11h6"></path></svg>
                </div>
                <div class="admin-capacity-copy">
                    <span>EVENT OCCUPANCY CONTROL</span>
                    @if($eventConfiguration)
                        <strong><b data-live-count="inside">{{ number_format($stats['checked_in']) }}</b> of {{ number_format($eventConfiguration->capacity_limit) }} inside</strong>
                        <small>Changing this number checks eligible visitors in or out and records the movements.</small>
                    @else
                        <strong>Capacity is not configured</strong>
                        <small>Configure the event capacity before adjusting the inside count.</small>
                    @endif
                </div>
                @if($eventConfiguration)
                    <form method="POST" action="{{ route('admin.dashboard.inside_count') }}" class="admin-capacity-form">
                        @csrf @method('PATCH')
                        <label>SET CURRENTLY INSIDE
                            <input type="number" name="inside_count" value="{{ old('inside_count', $stats['checked_in']) }}" min="0" max="{{ $eventConfiguration->capacity_limit }}" required>
                        </label>
                        <button type="submit" class="btn btn-primary">Update Count</button>
                    </form>
                @else
                    <a href="{{ route('admin.configurations.event.edit') }}" class="btn btn-primary">Set Capacity</a>
                @endif
            </section>
            <section class="admin-panel">
                <div class="admin-panel-heading"><div><span>LIVE RECORDS</span><h2>Recent visitors</h2></div><a href="{{ route('admin.visitors.index') }}">View all <span>→</span></a></div>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead><tr><th>Visitor</th><th>Phone</th><th>Purpose</th><th>Arrival</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse($recentVisitors as $visitor)
                                <tr><td><div class="admin-visitor-cell"><span>{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</span><div><strong>{{ $visitor->full_name ?: 'Unnamed visitor' }}</strong><small>{{ $visitor->document_number ?: 'No document number' }}</small></div></div></td><td>{{ $visitor->mobile_number ?: '—' }}</td><td class="admin-purpose-cell">{{ $visitor->occupation ?: '—' }}{{ $visitor->company ? ' · '.$visitor->company : '' }}</td><td>{{ ($visitor->verified_at ?: $visitor->created_at)?->format('M j, g:i A') }}</td><td><span class="{{ $visitor->checkin_status ? 'badge-pill-checkedin' : 'badge-pill-checkedout' }}">{{ $visitor->checkin_status ? 'Inside' : 'Not inside' }}</span></td></tr>
                            @empty
                                <tr><td colspan="5" class="admin-empty-state">No visitor records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>
    <div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>
    <div id="liveProfileModal" class="admin-profile-modal" hidden>
        <div class="admin-profile-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="liveProfileModalTitle">
            <div class="admin-profile-modal-head">
                <div><span>LIVE PARTICIPANT</span><h2 id="liveProfileModalTitle">Participant profile</h2></div>
                <button type="button" class="admin-profile-modal-close" aria-label="Close participant details">&times;</button>
            </div>
            <div class="admin-profile-modal-body">
                <div class="admin-profile-summary">
                    <div id="liveProfileAvatar" class="admin-profile-avatar" aria-hidden="true"></div>
                    <div class="admin-profile-identity"><strong id="liveProfileName"></strong><small id="liveProfileCategory"></small><span class="admin-profile-status">Currently inside</span></div>
                    <div class="admin-profile-current-arrival"><span>LAST CHECK-IN</span><strong id="liveProfileCheckedIn"></strong></div>
                </div>
                <div class="admin-profile-details">
                    <div class="admin-profile-detail"><span>PASS / VERIFICATION ID</span><strong id="liveProfileVerificationId"></strong></div>
                    <div class="admin-profile-detail"><span>DOCUMENT NUMBER</span><strong id="liveProfileDocument"></strong></div>
                    <div class="admin-profile-detail"><span>MOBILE NUMBER</span><strong id="liveProfileMobile"></strong></div>
                    <div class="admin-profile-detail"><span>COMPANY</span><strong id="liveProfileCompany"></strong></div>
                    <div class="admin-profile-detail"><span>OCCUPATION / ROLE</span><strong id="liveProfileOccupation"></strong></div>
                </div>
                <section class="admin-profile-history" aria-labelledby="liveProfileHistoryTitle">
                    <h3 id="liveProfileHistoryTitle">Check-in and check-out history</h3>
                    <div id="liveProfileHistory"></div>
                </section>
            </div>
        </div>
    </div>
    <script>
        const sidebar = document.querySelector('.admin-sidebar');
        const menu = document.getElementById('adminMenuToggle');
        const overlay = document.getElementById('adminSidebarOverlay');
        const closeMenu = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); menu.setAttribute('aria-expanded', 'false'); };
        menu.addEventListener('click', () => { const open = sidebar.classList.toggle('open'); overlay.classList.toggle('show', open); menu.setAttribute('aria-expanded', String(open)); });
        overlay.addEventListener('click', closeMenu);
        const liveProfileModal = document.getElementById('liveProfileModal');
        const liveProfileClose = liveProfileModal.querySelector('.admin-profile-modal-close');
        let lastProfileTrigger = null;

        const setProfileText = (id, value) => document.getElementById(id).textContent = value || 'Not provided';
        const closeProfileModal = () => {
            liveProfileModal.hidden = true;
            document.body.style.overflow = '';
            lastProfileTrigger?.focus();
        };
        const openProfileModal = (profile, trigger) => {
            lastProfileTrigger = trigger;
            setProfileText('liveProfileName', profile.name);
            setProfileText('liveProfileCategory', profile.category ? `Category · ${profile.category}` : 'Category not assigned');
            setProfileText('liveProfileCheckedIn', profile.checked_in_at);
            setProfileText('liveProfileVerificationId', profile.verification_id);
            setProfileText('liveProfileDocument', profile.document);
            setProfileText('liveProfileMobile', profile.mobile);
            setProfileText('liveProfileCompany', profile.company);
            setProfileText('liveProfileOccupation', profile.occupation);

            const avatar = document.getElementById('liveProfileAvatar');
            avatar.replaceChildren();
            if (profile.image) {
                const image = new Image();
                image.src = profile.image;
                image.alt = '';
                avatar.append(image);
            } else {
                avatar.textContent = profile.initial || '?';
            }

            const history = document.getElementById('liveProfileHistory');
            history.replaceChildren();
            if (!profile.activity?.length) {
                const empty = document.createElement('div');
                empty.className = 'admin-profile-empty';
                empty.textContent = 'No gate activity has been recorded yet.';
                history.append(empty);
            } else {
                const table = document.createElement('table');
                const header = document.createElement('thead');
                const headerRow = document.createElement('tr');
                ['Date', 'Check-in', 'Check-out', 'Time inside'].forEach(label => {
                    const cell = document.createElement('th');
                    cell.textContent = label;
                    headerRow.append(cell);
                });
                header.append(headerRow);
                const body = document.createElement('tbody');
                profile.activity.forEach(activity => {
                    const row = document.createElement('tr');
                    [activity.date, `${activity.check_in} (${activity.check_in_gate})`, `${activity.check_out} (${activity.check_out_gate})`, activity.duration].forEach(value => {
                        const cell = document.createElement('td');
                        cell.textContent = value;
                        row.append(cell);
                    });
                    body.append(row);
                });
                table.append(header, body);
                history.append(table);
            }
            liveProfileModal.hidden = false;
            document.body.style.overflow = 'hidden';
            liveProfileClose.focus();
        };
        document.querySelectorAll('[data-live-profile]').forEach(trigger => trigger.addEventListener('click', () => {
            try { openProfileModal(JSON.parse(trigger.dataset.liveProfile), trigger); } catch (_) {}
        }));
        liveProfileClose.addEventListener('click', closeProfileModal);
        liveProfileModal.addEventListener('click', event => { if (event.target === liveProfileModal) closeProfileModal(); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !liveProfileModal.hidden) closeProfileModal(); });
        document.querySelectorAll('.admin-nav-group-title').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const group = toggle.closest('.admin-nav-group');
                if (group) {
                    const isCollapsed = group.classList.toggle('collapsed');
                    toggle.setAttribute('aria-expanded', String(!isCollapsed));
                }
            });
        });
        setInterval(async () => {
            try {
                const response = await fetch(@json(route('admin.dashboard.counts')), {headers:{Accept:'application/json'}});
                if (!response.ok) return;
                const counts = await response.json();
                document.querySelectorAll('[data-live-count]').forEach(element => element.textContent = Number(counts[element.dataset.liveCount] || 0).toLocaleString());
                document.querySelectorAll('[data-live-category-count]').forEach(element => element.textContent = Number(counts.categories?.[element.dataset.liveCategoryCount] || 0).toLocaleString());
            } catch (_) {}
        }, 12000);
    </script>
</body>
</html>
