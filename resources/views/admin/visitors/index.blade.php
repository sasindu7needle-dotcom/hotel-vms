<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verified Visitors — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<style>
    body.landing-page .admin-document-sides > div { display:grid; grid-template-columns:repeat(2,minmax(0,180px)); gap:12px; margin-top:8px; }
    body.landing-page .admin-document-sides a { display:block; padding:7px; color:#53620b; background:#f7faeb; border:1px solid #dce8aa; border-radius:10px; text-align:center; }
    body.landing-page .admin-document-sides img { display:block; width:100%; height:100px; border-radius:7px; object-fit:cover; }
    body.landing-page .admin-document-sides small { display:block; margin-top:5px; font-size:10px; font-weight:800; text-transform:uppercase; }
    body.landing-page .admin-row-actions { display:flex; gap:6px; }
    body.landing-page .admin-edit-button { padding:7px 11px; border:1px solid #c7da6a; border-radius:7px; background:#fff; color:#4e5c0d; font-size:11px; font-weight:800; cursor:pointer; }
    body.landing-page .admin-print-button { display:inline-flex; align-items:center; justify-content:center; padding:7px 11px; border:1px solid #aeca37; border-radius:7px; background:#c8e063; color:#273000; font-size:11px; font-weight:800; text-decoration:none; white-space:nowrap; }
    body.landing-page .admin-print-disabled { border-color:#d9dfe4; background:#eef1f3; color:#929ba5; cursor:not-allowed; }
    body.landing-page .admin-header-print-button { position:absolute; top:72px; right:88px; z-index:2; min-height:34px; padding:0 13px; border:1px solid #aeca37; border-radius:8px; background:#c8e063; color:#273000; font-size:10px; font-weight:800; line-height:32px; text-decoration:none; white-space:nowrap; }
    body.landing-page .admin-edit-dialog { width:min(760px,calc(100vw - 28px)); }
    body.landing-page .admin-edit-form { padding:20px 24px 24px; }
    body.landing-page .admin-edit-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    body.landing-page .admin-edit-grid label { display:flex; flex-direction:column; gap:6px; color:#596579; font-size:10px; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }
    body.landing-page .admin-edit-grid input, body.landing-page .admin-edit-grid select, body.landing-page .admin-edit-grid textarea { width:100%; min-height:42px; padding:9px 11px; border:1px solid #dbe2eb; border-radius:8px; background:#fff; color:#172033; font:500 12px Inter,sans-serif; outline:none; }
    body.landing-page .admin-edit-grid input:focus, body.landing-page .admin-edit-grid select:focus, body.landing-page .admin-edit-grid textarea:focus { border-color:#b8d34c; box-shadow:0 0 0 3px rgba(200,224,99,.2); }
    body.landing-page .admin-edit-grid .wide { grid-column:1/-1; }
    body.landing-page .admin-edit-grid textarea { min-height:76px; resize:vertical; }
    body.landing-page .admin-edit-actions { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-top:20px; padding-top:16px; border-top:1px solid #edf0f3; }
    body.landing-page .admin-edit-actions > div { display:flex; gap:8px; }
    body.landing-page .admin-delete-button { padding:9px 13px; border:1px solid #efb4b4; border-radius:7px; background:#fff5f5; color:#b42323; font-size:11px; font-weight:800; cursor:pointer; }
    body.landing-page .admin-row-delete-button { display:grid; place-items:center; width:34px; min-width:34px; padding:0; color:#a72c35; background:#fff; border:1px solid #efc3c6; border-radius:7px; cursor:pointer; transition:background .15s,border-color .15s,transform .15s; }
    body.landing-page .admin-row-delete-button:hover { background:#fff0f1; border-color:#df8d94; transform:translateY(-1px); }
    body.landing-page .admin-row-delete-button svg { width:14px; height:14px; fill:none; stroke:currentColor; stroke-width:1.9; stroke-linecap:round; stroke-linejoin:round; }
    body.landing-page .admin-delete-dialog { width:min(480px,calc(100vw - 32px)); overflow:hidden; }
    body.landing-page .admin-delete-dialog .admin-dialog-heading { min-height:auto; background:linear-gradient(135deg,#fff0f1,#fff); border-bottom-color:#f0c7ca; }
    body.landing-page .admin-delete-dialog .admin-dialog-heading span { color:#a72c35; }
    body.landing-page .admin-delete-content { padding:24px 28px 10px; color:#4b5665; }
    body.landing-page .admin-delete-content p { margin:0 0 14px; font-size:12px; line-height:1.6; }
    body.landing-page .admin-delete-content strong { color:#111; }
    body.landing-page .admin-delete-list { display:grid; gap:8px; margin:0; padding:13px 15px; background:#fff7f7; border:1px solid #f3d4d6; border-radius:10px; color:#7e3036; font-size:10px; font-weight:700; list-style:none; }
    body.landing-page .admin-delete-list li::before { content:'✓'; margin-right:8px; }
    .admin-delete-modal-form { margin:0 !important; padding:0 !important; width:100% !important; }
    .admin-delete-actions-bar { display:flex !important; align-items:center !important; justify-content:flex-end !important; gap:16px !important; width:100% !important; box-sizing:border-box !important; padding:20px 28px 24px !important; margin:0 !important; background:#fbfcfd !important; border-top:1px solid #edf0f3 !important; }
    .btn-keep-visitor { display:inline-flex !important; align-items:center !important; justify-content:center !important; height:44px !important; min-height:44px !important; padding:0 22px !important; color:#344054 !important; background:#ffffff !important; border:1px solid #d0d7de !important; border-radius:10px !important; font-family:Inter,sans-serif !important; font-size:13px !important; font-weight:700 !important; cursor:pointer !important; box-shadow:0 1px 2px rgba(16,24,40,.05) !important; transition:all .15s ease !important; }
    .btn-keep-visitor:hover { color:#111111 !important; background:#f4f8e5 !important; border-color:#c8dc72 !important; transform:translateY(-1px) !important; }
    .btn-delete-permanently { display:inline-flex !important; align-items:center !important; justify-content:center !important; height:44px !important; min-height:44px !important; padding:0 24px !important; color:#ffffff !important; background:linear-gradient(135deg,#e11d48 0%,#be123c 100%) !important; border:1px solid #b91c1c !important; border-radius:10px !important; font-family:Inter,sans-serif !important; font-size:13px !important; font-weight:700 !important; letter-spacing:.01em !important; cursor:pointer !important; box-shadow:0 4px 14px rgba(225,29,72,.3) !important; transition:all .15s ease !important; }
    .btn-delete-permanently:hover { background:linear-gradient(135deg,#be123c 0%,#9f1239 100%) !important; box-shadow:0 6px 18px rgba(190,18,60,.4) !important; transform:translateY(-1px) !important; }

    body.landing-page.admin-modal-open { overflow:hidden; }
    body.landing-page .admin-visitor-dialog { position:fixed; inset:0; margin:auto; padding:0; max-height:min(88vh,860px); overflow:auto; overscroll-behavior:contain; border:0; border-radius:18px; box-shadow:0 28px 80px rgba(15,23,42,.28); }
    body.landing-page .admin-visitor-dialog::backdrop { background:rgba(13,18,16,.62); backdrop-filter:blur(3px); }
    body.landing-page .admin-preview-dialog { width:min(1040px,calc(100vw - 32px)); }
    body.landing-page .admin-edit-dialog { width:min(760px,calc(100vw - 32px)); }
    body.landing-page .admin-dialog-heading { position:sticky; top:0; z-index:10; min-height:112px; padding:24px 30px; background:linear-gradient(135deg,#f3f9dc 0%,#fbfdf3 58%,#fff 100%); border-bottom:1px solid #dce8ae; box-shadow:0 8px 22px rgba(36,48,16,.08); isolation:isolate; }
    body.landing-page .admin-dialog-heading h2 { max-width:calc(100% - 64px); margin-top:7px; font-size:25px; line-height:1.15; overflow-wrap:anywhere; }
    body.landing-page .admin-dialog-heading button[data-close] { position:relative; display:grid; place-items:center; flex:0 0 42px; width:42px; height:42px; padding:0; color:#566477; background:rgba(255,255,255,.94); border:1px solid #d8e0e7; border-radius:50%; box-shadow:0 5px 14px rgba(24,34,47,.08); font-size:0; line-height:1; cursor:pointer; transition:background .15s ease,border-color .15s ease,color .15s ease,transform .15s ease; }
    body.landing-page .admin-dialog-heading button[data-close]::before, body.landing-page .admin-dialog-heading button[data-close]::after { content:''; position:absolute; width:15px; height:2px; background:currentColor; border-radius:2px; }
    body.landing-page .admin-dialog-heading button[data-close]::before { transform:rotate(45deg); }
    body.landing-page .admin-dialog-heading button[data-close]::after { transform:rotate(-45deg); }
    body.landing-page .admin-dialog-heading button[data-close]:hover { color:#111; background:#f2f7db; border-color:#c8dc72; transform:rotate(3deg); }
    body.landing-page .admin-dialog-heading button[data-close]:focus-visible { outline:3px solid rgba(200,224,99,.45); outline-offset:2px; }
    body.landing-page .admin-modal-close-button { display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:9px 16px; color:#344054; background:#fff; border:1px solid #d8e0e7; border-radius:8px; font-size:11px; font-weight:800; cursor:pointer; transition:background .15s ease,border-color .15s ease,transform .15s ease; }
    body.landing-page .admin-modal-close-button:hover { color:#111; background:#f4f8e5; border-color:#c8dc72; transform:translateY(-1px); }
    body.landing-page .admin-status-stack { display:flex; align-items:flex-start; flex-direction:column; gap:6px; }
    body.landing-page .admin-payment-badge, body.landing-page .admin-face-badge { display:inline-flex; align-items:center; gap:6px; min-height:26px; padding:5px 10px; border:1px solid transparent; border-radius:999px; font-size:9px; font-weight:800; letter-spacing:.045em; line-height:1; text-transform:uppercase; white-space:nowrap; }
    body.landing-page .admin-payment-badge::before, body.landing-page .admin-face-badge::before { content:''; width:7px; height:7px; flex:0 0 7px; border-radius:50%; background:currentColor; box-shadow:0 0 0 3px color-mix(in srgb,currentColor 18%,transparent); }
    body.landing-page .admin-payment-pending, body.landing-page .admin-payment-cash_pending, body.landing-page .admin-payment-card_pending { color:#8a5a00; background:#fff9df; border-color:#f1dda0; }
    body.landing-page .admin-payment-paid { color:#526600; background:#f1f8d4; border-color:#d4e691; }
    body.landing-page .admin-face-status-verified { color:#526600; background:#f1f8d4; border-color:#d4e691; }
    body.landing-page .admin-face-status-pending { color:#735c00; background:#fff9df; border-color:#f1dda0; }
    body.landing-page .admin-face-status-review_required { color:#9a4d00; background:#fff2e4; border-color:#f4c899; }
    body.landing-page .admin-face-status-rejected { color:#a52d35; background:#fff0f1; border-color:#efbdc1; }
    body.landing-page .admin-status-detail { color:#7f8c9b; font-size:9px; line-height:1.35; }
    body.landing-page .admin-preview-dialog .admin-dialog-profile { grid-template-columns:180px minmax(0,1fr); align-items:start; }
    body.landing-page .admin-preview-dialog .admin-dialog-heading { position:sticky; padding-right:220px; }
    body.landing-page .admin-preview-dialog .admin-dialog-heading button[data-close] { position:absolute; top:26px; right:28px; margin:0; }
    body.landing-page .admin-preview-dialog .admin-dialog-photo { position:sticky; top:136px; }
    body.landing-page .admin-preview-dialog .admin-dialog-grid > div { padding:12px 13px; background:#fbfcfd; }
    body.landing-page .admin-preview-dialog .admin-dialog-actions { position:sticky; bottom:0; z-index:4; box-shadow:0 -8px 24px rgba(20,28,38,.05); }
    body.landing-page .admin-activity-section { grid-column:1/-1; margin-top:10px; }
    body.landing-page .admin-activity-section > span { display:block; margin-bottom:9px; color:#667085; font-size:10px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
    body.landing-page .admin-activity-table { width:100%; border-collapse:collapse; font-size:11px; }
    body.landing-page .admin-activity-table th, body.landing-page .admin-activity-table td { padding:9px 8px; border-bottom:1px solid #e8edf1; text-align:left; white-space:nowrap; }
    body.landing-page .admin-activity-table th { color:#75808e; font-size:9px; letter-spacing:.05em; text-transform:uppercase; }
    body.landing-page .admin-preview-dialog .admin-dialog-heading::after { content:'READ ONLY'; position:absolute; top:35px; right:88px; margin:0; padding:6px 10px; color:#596c08; background:#f0f7cf; border:1px solid #d8e89a; border-radius:999px; font-size:9px; font-weight:800; letter-spacing:.09em; white-space:nowrap; }
    body.landing-page dialog[open] { animation:adminDialogIn .18s cubic-bezier(.2,.8,.2,1); }
    @keyframes adminDialogIn { from { opacity:0; transform:translateY(12px) scale(.985); } to { opacity:1; transform:none; } }
    @media(max-width:640px){ body.landing-page .admin-edit-grid{grid-template-columns:1fr} body.landing-page .admin-edit-grid .wide{grid-column:auto} body.landing-page .admin-edit-actions{align-items:stretch;flex-direction:column-reverse} }
    @media(max-width:700px){ body.landing-page .admin-preview-dialog .admin-dialog-profile{grid-template-columns:1fr} body.landing-page .admin-preview-dialog .admin-dialog-photo{position:relative;top:auto;width:150px} body.landing-page .admin-preview-dialog .admin-dialog-heading{padding-right:86px;padding-bottom:62px} body.landing-page .admin-preview-dialog .admin-dialog-heading::after{display:none} body.landing-page .admin-preview-dialog .admin-dialog-heading button[data-close]{top:22px;right:20px} body.landing-page .admin-header-print-button{top:auto;right:auto;bottom:14px;left:20px} }

</style>
</head>
<body class="landing-page admin-dashboard-page">
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
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
                @endif
                @if($canAccess('Visitors'))
                    <a href="{{ route('admin.visitors.index') }}" class="admin-nav-link active"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg><span>Visitors</span></a>
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

        <main class="admin-main admin-visitors-main">
            <header class="admin-topbar">
                <button id="adminMenuToggle" class="admin-menu-toggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
                <div><span class="tagline no-margin">VERIFIED DIRECTORY</span><h1>Visitor Records<span>.</span></h1><p>Verified identity and registration data in one secure view</p></div>
                <a href="{{ route('visitor.start') }}" class="btn btn-primary admin-add-visitor">+ New Check-in</a>
            </header>

            @if(session('status'))<div class="admin-page-alert">{{ session('status') }}</div>@endif
            @error('delete')<div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6">{{ $message }}</div>@enderror
            @error('checkin')<div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6">{{ $message }}</div>@enderror
            @error('badge')<div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6">{{ $message }}</div>@enderror
            @if($errors->any())<div class="admin-page-alert" style="color:#9f252e;background:#fff0f1;border-color:#efc3c6"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <section class="admin-visitor-stat-grid">
                @foreach([
                    ['label' => 'Verified Records', 'value' => $stats['total']],
                    ['label' => 'Verified Today', 'value' => $stats['verified_today']],
                    ['label' => 'Currently Inside', 'value' => $stats['inside']],
                    ['label' => 'Payment Pending', 'value' => $stats['payment_pending']]
                ] as $stat)
                    <article><span>{{ $stat['label'] }}</span><strong>{{ number_format($stat['value']) }}</strong></article>
                @endforeach
            </section>

            <section class="admin-panel admin-visitors-panel">
                <form method="GET" action="{{ route('admin.visitors.index') }}" class="admin-visitor-filters">
                    <div class="admin-search-field"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.35-4.35"></path></svg><input name="search" value="{{ data_get($filters, 'search') }}" placeholder="Search name, NIC, phone or company…" aria-label="Search visitors"></div>
                    <select name="payment_status" aria-label="Filter by payment"><option value="">All payments</option>@foreach(['pending' => 'Pending', 'paid' => 'Paid'] as $value => $label)<option value="{{ $value }}" @selected(data_get($filters, 'payment_status') === $value)>{{ $label }}</option>@endforeach</select>
                    <select name="checkin_status" aria-label="Filter by check-in"><option value="">All locations</option><option value="inside" @selected(data_get($filters, 'checkin_status') === 'inside')>Currently inside</option><option value="outside" @selected(data_get($filters, 'checkin_status') === 'outside')>Not inside</option></select>
                    <button class="btn btn-primary" type="submit">Filter</button>
                    @if(request()->hasAny(['search', 'payment_status', 'checkin_status']))<a href="{{ route('admin.visitors.index') }}" class="admin-clear-filter">Clear</a>@endif
                </form>

                <div class="table-responsive">
                    <table class="admin-table admin-visitors-table">
                        <thead><tr><th class="admin-index-column">#</th><th>Verified Visitor</th><th>Contact</th><th>Category & Fee</th><th>Payment</th><th>Photo</th><th>Verified</th><th></th></tr></thead>
                        <tbody>
                            @forelse($visitors as $visitor)
                                @php
                                    $mediaVersion = $visitor->updated_at?->format('Uu') ?: $visitor->id;
                                @endphp
                                @php
                                    $displayPaymentStatus = in_array($visitor->payment_status, ['cash_pending', 'card_pending'], true)
                                        ? 'pending'
                                        : $visitor->payment_status;
                                @endphp
                                <tr>
                                    <td class="admin-record-index">{{ ($visitors->firstItem() ?: 1) + $loop->index }}</td>
                                    <td><div class="admin-visitor-cell">
                                        @if($visitor->selfie_path)<img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="">@elseif($visitor->photo_path)<img src="{{ route('admin.visitors.photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="">@elseif($visitor->photo_url)<img src="{{ $visitor->photo_url }}" alt="">@else<span>{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</span>@endif
                                        <div><strong>{{ $visitor->full_name ?: 'Unnamed visitor' }}</strong><small>{{ strtoupper(str_replace('_', ' ', $visitor->document_type ?: 'Document')) }} · {{ $visitor->document_number ?: '—' }}</small></div>
                                    </div></td>
                                    <td><strong class="admin-cell-primary">{{ $visitor->mobile_number ?: '—' }}</strong><small class="admin-cell-secondary">{{ $visitor->company ?: $visitor->occupation ?: 'No company' }}</small></td>
                                    <td><strong class="admin-cell-primary">{{ $visitor->category ?: 'Not assigned' }}</strong><small class="admin-cell-secondary">{{ $visitor->entrance_fee !== null ? 'LKR '.number_format((float)$visitor->entrance_fee, 2) : 'No fee' }}</small>@if($visitor->eventRegistrationDay)<small class="admin-cell-secondary">{{ $visitor->eventRegistrationDay->label }} · {{ $visitor->eventRegistrationDay->event_date->format('d M Y') }}</small>@endif</td>
                                    <td><div class="admin-status-stack"><span class="admin-payment-badge admin-payment-{{ $displayPaymentStatus }}">{{ strtoupper($displayPaymentStatus) }}</span><small class="admin-status-detail">{{ strtoupper(str_replace('_', ' / ', $visitor->payment_method ?: 'Not selected')) }}</small></div></td>
                                    <td><div class="admin-status-stack"><span class="admin-face-badge admin-face-status-{{ $visitor->selfie_path ? 'verified' : 'pending' }}">{{ $visitor->selfie_path ? 'Photo captured' : 'Pending' }}</span><small class="admin-status-detail">{{ $visitor->selfie_path ? 'Stored with visitor record' : 'No visitor photo' }}</small></div></td>
                                    <td>{{ ($visitor->verified_at ?: $visitor->created_at)?->format('M j, Y') }}<small class="admin-cell-secondary">{{ ($visitor->verified_at ?: $visitor->created_at)?->format('g:i A') }}</small></td>
                                    <td><div class="admin-row-actions"><button type="button" class="admin-view-button" data-dialog="visitor-{{ $visitor->id }}">View</button><a class="admin-print-button" href="{{ route('admin.visitors.badge', $visitor) }}" target="_blank" rel="noopener">Print</a><button type="button" class="admin-edit-button" data-dialog="edit-visitor-{{ $visitor->id }}">Edit</button><button type="button" class="admin-row-delete-button" data-dialog="delete-visitor-{{ $visitor->id }}" aria-label="Delete {{ $visitor->full_name }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18"></path><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v5M14 11v5"></path></svg></button></div></td>
                                </tr>
                            @empty
                                <tr><td colspan="8"><div class="admin-visitor-empty"><span>+</span><h3>No verified visitors found</h3><p>Completed registrations will appear here with their verified identity and form details.</p></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @foreach($visitors as $visitor)
                    @php
                        $mediaVersion = $visitor->updated_at?->format('Uu') ?: $visitor->id;
                    @endphp
                    <dialog id="visitor-{{ $visitor->id }}" class="admin-visitor-dialog admin-preview-dialog">
                        <div class="admin-dialog-heading"><div><span>VISITOR PROFILE</span><h2>{{ $visitor->full_name ?: 'Visitor details' }}</h2></div><a class="admin-header-print-button" href="{{ route('admin.visitors.badge', $visitor) }}" target="_blank" rel="noopener">Print Card</a><button type="button" data-close aria-label="Close">×</button></div>
                        <div class="admin-dialog-profile">
                            <div class="admin-dialog-photo">@if($visitor->selfie_path)<img src="{{ route('admin.visitors.selfie', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Captured photo of {{ $visitor->full_name }}">@elseif($visitor->photo_path)<img src="{{ route('admin.visitors.photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Document photo of {{ $visitor->full_name }}">@elseif($visitor->photo_url)<img src="{{ $visitor->photo_url }}" alt="Visitor photo of {{ $visitor->full_name }}">@else<span>{{ mb_strtoupper(mb_substr($visitor->full_name ?: '?', 0, 1)) }}</span>@endif<i>{{ $visitor->selfie_path ? 'PHOTO CAPTURED' : 'NO PHOTO' }}</i></div>
                            <div class="admin-dialog-grid">
                                @foreach([
                                    'Sinhala / Preferred Name' => $visitor->full_name,
                                    'Latin Name' => $visitor->full_name_latin,
                                    'Document Type' => strtoupper(str_replace('_', ' ', $visitor->document_type ?: '')),
                                    'NIC / Passport' => $visitor->document_number,
                                    'Mobile Number' => $visitor->mobile_number,
                                    'WhatsApp Number' => $visitor->whatsapp_number,
                                    'Occupation' => $visitor->occupation,
                                    'Company' => $visitor->company,
                                    'Category' => $visitor->category,
                                    'Entrance Fee' => $visitor->entrance_fee !== null ? 'LKR '.number_format((float)$visitor->entrance_fee, 2) : null,
                                    'Event Day' => $visitor->eventRegistrationDay ? $visitor->eventRegistrationDay->label.' · '.$visitor->eventRegistrationDay->event_date->format('d M Y') : null,
                                    'Payment Method' => strtoupper(str_replace('_', ' / ', $visitor->payment_method ?: '')),
                                    'Payment Status' => strtoupper(in_array($visitor->payment_status, ['cash_pending', 'card_pending'], true) ? 'pending' : $visitor->payment_status),
                                    'Access Status' => $visitor->is_blocked ? 'BLOCKED' : 'ALLOWED',
                                    'Verification ID' => $visitor->verification_id ?: $visitor->didit_session_id,
                                    'Visitor Photo' => $visitor->selfie_path ? 'CAPTURED' : 'NOT CAPTURED',
                                    'OCR Provider' => $visitor->ocr_provider ? strtoupper(str_replace('_', ' ', $visitor->ocr_provider)) : null,
                                    'Identity Reviewed' => $visitor->identity_reviewed_at?->format('M j, Y · g:i A'),
                                ] as $label => $value)<div><span>{{ $label }}</span><strong>{{ filled($value) ? $value : '—' }}</strong></div>@endforeach
                                <div class="admin-dialog-wide"><span>Address</span><strong>{{ $visitor->address ?: '—' }}</strong></div>
                                @if($visitor->address_latin && $visitor->address_latin !== $visitor->address)<div class="admin-dialog-wide"><span>Latin Address</span><strong>{{ $visitor->address_latin }}</strong></div>@endif
                                <div class="admin-dialog-wide admin-document-sides">
                                    <span>Stored document images</span>
                                    <div>
                                        @if($visitor->photo_path)<a href="{{ route('admin.visitors.photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.visitors.photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Front of document"><small>{{ $visitor->document_type === 'passport' ? 'Identity page' : 'Front' }}</small></a>@endif
                                        @if($visitor->back_photo_path)<a href="{{ route('admin.visitors.back_photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.visitors.back_photo', ['visitor' => $visitor, 'v' => $mediaVersion]) }}" alt="Back of document"><small>Back</small></a>@endif
                                    </div>
                                </div>
                                <div class="admin-activity-section">
                                    <span>Activity</span>
                                    <div class="table-responsive">
                                        <table class="admin-activity-table">
                                            <thead><tr><th>Date</th><th>Time-In</th><th>Gate-In</th><th>Time-Out</th><th>Gate-Out</th><th>Duration</th></tr></thead>
                                            <tbody>
                                            @forelse($visitor->activity_rows as $activity)
                                                <tr>
                                                    <td>{{ $activity['in']->scanned_at->format('M j, Y') }}</td>
                                                    <td>{{ $activity['in']->scanned_at->format('H:i') }}</td>
                                                    <td>{{ $activity['in']->gate }}</td>
                                                    <td>{{ $activity['out']?->scanned_at?->format('H:i') ?: '—' }}</td>
                                                    <td>{{ $activity['out']?->gate ?: '—' }}</td>
                                                    <td>{{ $activity['duration_minutes'] !== null ? intdiv($activity['duration_minutes'], 60).'h '.($activity['duration_minutes'] % 60).'m' : 'Inside now' }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6">No gate activity recorded.</td></tr>
                                            @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="admin-dialog-actions"><form method="POST" action="{{ route('admin.visitors.checkin', $visitor) }}">@csrf @method('PATCH')<button class="btn {{ $visitor->checkin_status ? 'btn-secondary' : 'btn-primary' }}" type="submit">{{ $visitor->checkin_status ? 'Check Out Visitor' : 'Check In Visitor' }}</button></form><a class="btn btn-primary" href="{{ route('admin.visitors.badge', $visitor) }}" target="_blank" rel="noopener">Print Card</a><button type="button" class="admin-edit-button" data-dialog-switch="edit-visitor-{{ $visitor->id }}">Edit record</button><button type="button" class="admin-modal-close-button" data-close>Close</button></div>
                    </dialog>

                    <dialog id="edit-visitor-{{ $visitor->id }}" class="admin-visitor-dialog admin-edit-dialog">
                        <div class="admin-dialog-heading"><div><span>ADMIN CONTROLS</span><h2>Edit visitor record</h2></div><button type="button" data-close aria-label="Close">×</button></div>
                        <form class="admin-edit-form" method="POST" action="{{ route('admin.visitors.update', $visitor) }}">
                            @csrf @method('PATCH')
                            <div class="admin-edit-grid">
                                <label>Full name<input name="full_name" value="{{ $visitor->full_name }}"></label>
                                <label>Document type<select name="document_type"><option value="">Not specified</option>@foreach(['nic'=>'NIC','driving_license'=>'Driving Licence','passport'=>'Passport'] as $value=>$label)<option value="{{ $value }}" @selected($visitor->document_type===$value)>{{ $label }}</option>@endforeach</select></label>
                                <label>Document number<input name="document_number" value="{{ $visitor->document_number }}"></label>
                                <label class="wide">Address<textarea name="address">{{ $visitor->address }}</textarea></label>
                                <label>Mobile number<input name="mobile_number" value="{{ $visitor->mobile_number }}"></label>
                                <label>WhatsApp number<input name="whatsapp_number" value="{{ $visitor->whatsapp_number }}"></label>
                                <label>Occupation<input name="occupation" value="{{ $visitor->occupation }}"></label>
                                <label>Company<input name="company" value="{{ $visitor->company }}"></label>
                                <label>Category<input name="category" value="{{ $visitor->category }}"></label>
                                <label>Entrance fee<input name="entrance_fee" type="number" min="0" step="0.01" value="{{ $visitor->entrance_fee }}"></label>
                                <label>Payment method<select name="payment_method"><option value="">Not selected</option>@foreach(['cash'=>'Cash','visa_master'=>'Visa / MasterCard','amex'=>'American Express'] as $value=>$label)<option value="{{ $value }}" @selected($visitor->payment_method===$value)>{{ $label }}</option>@endforeach</select></label>
                                <label>Payment status<select name="payment_status" required>@foreach(['pending'=>'Pending','paid'=>'Paid'] as $value=>$label)<option value="{{ $value }}" @selected(($value === 'pending' && in_array($visitor->payment_status, ['pending', 'cash_pending', 'card_pending'], true)) || $visitor->payment_status === $value)>{{ $label }}</option>@endforeach</select></label>
                                <label>Access status<select name="is_blocked" required><option value="0" @selected(!$visitor->is_blocked)>Allowed</option><option value="1" @selected($visitor->is_blocked)>Blocked</option></select></label>
                            </div>
                            <div class="admin-edit-actions"><button type="button" class="admin-modal-close-button" data-close>Cancel</button><div><button type="submit" class="btn btn-primary">Save changes</button></div></div>
                        </form>
                    </dialog>

                    <dialog id="delete-visitor-{{ $visitor->id }}" class="admin-visitor-dialog admin-delete-dialog">
                        <div class="admin-dialog-heading"><div><span>PERMANENT DELETION</span><h2>Delete visitor?</h2></div><button type="button" data-close aria-label="Close">×</button></div>
                        <div class="admin-delete-content">
                            <p>You are about to permanently delete <strong>{{ $visitor->full_name ?: 'this visitor' }}</strong>. This action cannot be undone.</p>
                            <ul class="admin-delete-list"><li>Visitor registration and contact data</li><li>NIC or passport front image</li>@if($visitor->back_photo_path)<li>Document back image</li>@endif @if($visitor->selfie_path)<li>Captured visitor photo</li>@endif</ul>
                        </div>
                        <form method="POST" action="{{ route('admin.visitors.destroy', $visitor) }}" class="admin-delete-modal-form">@csrf @method('DELETE')<div class="admin-delete-actions-bar"><button type="button" class="btn-keep-visitor" data-close>Keep visitor</button><button type="submit" class="btn-delete-permanently">Delete permanently</button></div></form>
                    </dialog>
                @endforeach

                @if($visitors->hasPages())
                    <nav class="admin-pagination" aria-label="Visitor records pagination">
                        <span>Showing {{ $visitors->firstItem() }}–{{ $visitors->lastItem() }} of {{ $visitors->total() }}</span>
                        <div class="admin-page-links">
                            @if($visitors->onFirstPage())<span class="disabled" aria-disabled="true">Previous</span>@else<a href="{{ $visitors->previousPageUrl() }}" rel="prev">Previous</a>@endif
                            @php
                                $current = $visitors->currentPage();
                                $last = $visitors->lastPage();
                                $pages = collect([1, $last, $current - 2, $current - 1, $current, $current + 1, $current + 2])->filter(fn ($page) => $page >= 1 && $page <= $last)->unique()->sort()->values();
                                $previousPage = null;
                            @endphp
                            @foreach($pages as $page)
                                @if($previousPage !== null && $page > $previousPage + 1)<span class="ellipsis" aria-hidden="true">…</span>@endif
                                @if($page === $current)<span class="active" aria-current="page">{{ $page }}</span>@else<a href="{{ $visitors->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>@endif
                                @php $previousPage = $page; @endphp
                            @endforeach
                            @if($visitors->hasMorePages())<a href="{{ $visitors->nextPageUrl() }}" rel="next">Next</a>@else<span class="disabled" aria-disabled="true">Next</span>@endif
                        </div>
                    </nav>
                @elseif($visitors->total() > 0)
                    <div class="admin-pagination admin-pagination-single"><span>Showing {{ $visitors->firstItem() }}–{{ $visitors->lastItem() }} of {{ $visitors->total() }}</span></div>
                @endif
            </section>
        </main>
    </div>
    <div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>
    <script>
        const sidebar = document.querySelector('.admin-sidebar'), menu = document.getElementById('adminMenuToggle'), overlay = document.getElementById('adminSidebarOverlay');
        menu.addEventListener('click', () => { const open = sidebar.classList.toggle('open'); overlay.classList.toggle('show', open); menu.setAttribute('aria-expanded', String(open)); });
        overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); });
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
        // Dialogs are emitted while looping table rows. Mounting them directly under
        // <body> avoids HTML table "foster parenting" and inconsistent modal targets.
        const visitorDialogs = [...document.querySelectorAll('dialog.admin-visitor-dialog')];
        visitorDialogs.forEach(dialog => document.body.appendChild(dialog));

        const openDialog = id => {
            const target = document.getElementById(id);
            if (!target) return;
            visitorDialogs.forEach(dialog => { if (dialog !== target && dialog.open) dialog.close(); });
            if (!target.open) target.showModal();
            target.scrollTop = 0;
            document.body.classList.add('admin-modal-open');
        };

        document.querySelectorAll('.admin-view-button[data-dialog]').forEach(button => button.addEventListener('click', () => openDialog(button.dataset.dialog)));
        document.querySelectorAll('.admin-edit-button[data-dialog]').forEach(button => button.addEventListener('click', () => openDialog(button.dataset.dialog)));
        document.querySelectorAll('.admin-row-delete-button[data-dialog]').forEach(button => button.addEventListener('click', () => openDialog(button.dataset.dialog)));
        document.querySelectorAll('[data-dialog-switch]').forEach(button => button.addEventListener('click', () => {
            button.closest('dialog').close();
            openDialog(button.dataset.dialogSwitch);
        }));
        document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
        visitorDialogs.forEach(dialog => {
            dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
            dialog.addEventListener('close', () => {
                if (!visitorDialogs.some(item => item.open)) document.body.classList.remove('admin-modal-open');
            });
        });
        document.querySelectorAll('[data-confirm-delete]').forEach(form => form.addEventListener('submit', event => {
            if (!window.confirm(`Permanently delete ${form.dataset.confirmDelete}? This also removes the stored document and visitor photos.`)) event.preventDefault();
        }));
    </script>
</body>
</html>
