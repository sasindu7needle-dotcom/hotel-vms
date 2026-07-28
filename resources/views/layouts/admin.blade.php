<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('styles')
</head>
<body class="landing-page admin-dashboard-page">
<div class="admin-dashboard-shell">
    <aside id="adminSidebar" class="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand admin-sidebar-brand"><span class="admin-brand-mark"></span><span>TRACTION <strong>GUEST</strong></span></a>
        <nav aria-label="Admin navigation">
            <a href="{{ route('admin.dashboard') }}" class="admin-nav-link @if(request()->routeIs('admin.dashboard*')) active @endif"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg><span>Dashboard</span></a>
            <a href="{{ route('admin.visitors.index') }}" class="admin-nav-link @if(request()->routeIs('admin.visitors*')) active @endif"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path></svg><span>Visitors</span></a>
            <div class="admin-nav-group @if(request()->routeIs('admin.configurations*')) active @else collapsed @endif">
                <button type="button" class="admin-nav-group-title" aria-expanded="{{ request()->routeIs('admin.configurations*') ? 'true' : 'false' }}">
                    <svg class="admin-nav-group-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1.03 1.56V21h-4v-.08A1.7 1.7 0 0 0 9 19.37a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 4.63 15 1.7 1.7 0 0 0 3.08 14H3v-4h.08A1.7 1.7 0 0 0 4.63 9a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 9 4.63h.01A1.7 1.7 0 0 0 10 3.08V3h4v.08A1.7 1.7 0 0 0 15 4.63a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.37 9v.01A1.7 1.7 0 0 0 20.92 10H21v4h-.08A1.7 1.7 0 0 0 19.4 15Z"></path></svg>
                    <span>Master Configurations</span>
                    <svg class="admin-nav-arrow" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"></path></svg>
                </button>
                <div class="admin-nav-subtabs">
                    <a href="{{ route('admin.configurations.event.edit') }}" class="@if(request()->routeIs('admin.configurations.event*')) active @endif">Event Configurations</a>
                    <a href="{{ route('admin.configurations.capacity.edit') }}" class="@if(request()->routeIs('admin.configurations.capacity*')) active @endif">Occupancy Limit</a>
                    <a href="{{ route('admin.configurations.categories.index') }}" class="@if(request()->routeIs('admin.configurations.categories*')) active @endif">Visitor Categories</a>
                    <a href="{{ route('admin.configurations.users.index') }}" class="@if(request()->routeIs('admin.configurations.users*')) active @endif">Users &amp; Access</a>
                </div>
            </div>
        </nav>
        <form action="{{ route('admin.logout') }}" method="POST" class="admin-logout-form">@csrf<button type="submit" class="admin-nav-link"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"></path></svg><span>Sign Out</span></button></form>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar">
            <button id="adminMenuToggle" class="admin-menu-toggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
            @yield('header')
            <div class="admin-user-chip"><span>A</span><div><strong>{{ session('admin_username') }}</strong><small>Administrator</small></div></div>
        </header>
        @yield('content')
    </main>
</div>
<div id="adminSidebarOverlay" class="admin-sidebar-overlay"></div>
<script>
    const adminSidebar = document.getElementById('adminSidebar');
    const adminMenu = document.getElementById('adminMenuToggle');
    const adminOverlay = document.getElementById('adminSidebarOverlay');
    const closeAdminMenu = () => { adminSidebar.classList.remove('open'); adminOverlay.classList.remove('show'); adminMenu.setAttribute('aria-expanded', 'false'); };
    adminMenu.addEventListener('click', () => { const open = adminSidebar.classList.toggle('open'); adminOverlay.classList.toggle('show', open); adminMenu.setAttribute('aria-expanded', String(open)); });
    adminOverlay.addEventListener('click', closeAdminMenu);
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
</script>
@stack('scripts')
</body>
</html>
