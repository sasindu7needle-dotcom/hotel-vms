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
        .admin-dashboard-message{margin:0 0 14px;padding:11px 14px;border:1px solid #cbdc83;border-radius:9px;background:#f4f9dd;color:#405000;font-size:12px;font-weight:700}.admin-dashboard-message.error{border-color:#efb7bc;background:#fff0f1;color:#94232d}
        @media(max-width:900px){body.landing-page .admin-capacity-control{grid-template-columns:46px minmax(0,1fr)}body.landing-page .admin-capacity-form,body.landing-page .admin-capacity-control>.btn{grid-column:1/-1;width:100%}body.landing-page .admin-capacity-form label{flex:1}body.landing-page .admin-capacity-form input{width:100%}}
        @media(max-width:700px){body.landing-page .admin-inside-breakdown{grid-template-columns:1fr}body.landing-page .admin-capacity-control{grid-template-columns:40px minmax(0,1fr);padding:17px 16px}body.landing-page .admin-capacity-icon{width:40px;height:40px}}
        @media(max-width:460px){body.landing-page .admin-capacity-form{align-items:stretch;flex-direction:column}body.landing-page .admin-capacity-form button{width:100%}}
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
                    <a href="{{ route('admin.dashboard') }}" class="admin-nav-link active"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
                @endif
                @if($canAccess('Visitors'))
                    <a href="{{ route('admin.visitors.index') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 1 0 7.75"></path></svg><span>Visitors</span></a>
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

            <section class="admin-stat-grid" aria-label="Visitor statistics">
                @foreach([
                    ['label' => 'Total Visitors', 'value' => $stats['total'], 'tone' => 'lime'],
                    ['label' => 'Arrivals Today', 'value' => $stats['today'], 'tone' => 'coral'],
                    ['label' => 'Currently Inside', 'value' => $stats['checked_in'], 'tone' => 'black', 'key' => 'inside'],
                    ['label' => 'Checked Out', 'value' => $stats['checked_out'], 'tone' => 'slate']
                ] as $stat)
                    <article class="admin-stat-card admin-stat-{{ $stat['tone'] }}"><div><span>{{ $stat['label'] }}</span><strong @if(isset($stat['key'])) data-live-count="{{ $stat['key'] }}" @endif>{{ number_format($stat['value']) }}</strong></div><i></i></article>
                @endforeach
            </section>
            @if(session('status'))<div class="admin-dashboard-message" role="status">{{ session('status') }}</div>@endif
            @error('inside_count')<div class="admin-dashboard-message error" role="alert">{{ $message }}</div>@enderror
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
            <section class="admin-visitor-stat-grid admin-inside-breakdown" aria-label="Currently inside by category">
                <article><span>Visitors Inside</span><strong data-live-count="visitor">{{ number_format($stats['visitors_inside']) }}</strong></article>
                <article><span>Exhibitors Inside</span><strong data-live-count="exhibitor">{{ number_format($stats['exhibitors_inside']) }}</strong></article>
                <article><span>Staff Inside</span><strong data-live-count="staff">{{ number_format($stats['staff_inside']) }}</strong></article>
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
    <script>
        const sidebar = document.querySelector('.admin-sidebar');
        const menu = document.getElementById('adminMenuToggle');
        const overlay = document.getElementById('adminSidebarOverlay');
        const closeMenu = () => { sidebar.classList.remove('open'); overlay.classList.remove('show'); menu.setAttribute('aria-expanded', 'false'); };
        menu.addEventListener('click', () => { const open = sidebar.classList.toggle('open'); overlay.classList.toggle('show', open); menu.setAttribute('aria-expanded', String(open)); });
        overlay.addEventListener('click', closeMenu);
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
            } catch (_) {}
        }, 12000);
    </script>
</body>
</html>
