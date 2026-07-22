<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page admin-dashboard-page">
    <div class="admin-dashboard-shell">
        <aside id="adminSidebar" class="admin-sidebar">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand admin-sidebar-brand"><span class="admin-brand-mark"></span><span>TRACTION <strong>GUEST</strong></span></a>
            <nav aria-label="Admin navigation">
                <a href="{{ route('admin.dashboard') }}" class="admin-nav-link active"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
                <a href="{{ route('admin.visitors.index') }}" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 1 0 7.75"></path></svg><span>Visitors</span></a>
            </nav>
            <form action="{{ route('admin.logout') }}" method="POST" class="admin-logout-form">@csrf<button type="submit" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"></path></svg><span>Sign Out</span></button></form>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <button id="adminMenuToggle" class="admin-menu-toggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
                <div><span class="tagline no-margin">ADMIN OVERVIEW</span><h1>Visitor Dashboard<span>.</span></h1><p>{{ now()->format('l, F j, Y') }}</p></div>
                <div class="admin-user-chip"><span>A</span><div><strong>{{ session('admin_username') }}</strong><small>Administrator</small></div></div>
            </header>

            <section class="admin-stat-grid" aria-label="Visitor statistics">
                @foreach([
                    ['label' => 'Total Visitors', 'value' => $stats['total'], 'tone' => 'lime'],
                    ['label' => 'Arrivals Today', 'value' => $stats['today'], 'tone' => 'coral'],
                    ['label' => 'Currently Inside', 'value' => $stats['checked_in'], 'tone' => 'black'],
                    ['label' => 'Checked Out', 'value' => $stats['checked_out'], 'tone' => 'slate']
                ] as $stat)
                    <article class="admin-stat-card admin-stat-{{ $stat['tone'] }}"><div><span>{{ $stat['label'] }}</span><strong>{{ number_format($stat['value']) }}</strong></div><i></i></article>
                @endforeach
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
    </script>
</body>
</html>
