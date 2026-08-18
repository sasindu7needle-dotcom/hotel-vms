<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>User Access Management — Superadmin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <style>
        .superadmin-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 999px; color: #dc2626; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .superadmin-badge i { width: 6px; height: 6px; background: #dc2626; border-radius: 50%; display: inline-block; }
        .permission-badge { display: inline-block; padding: 2px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; margin: 2px; }
        .permission-badge.all { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
    </style>
</head>
<body class="landing-page admin-dashboard-page">
<div class="admin-dashboard-shell">
    <aside id="adminSidebar" class="admin-sidebar">
        <a href="{{ route('superadmin.dashboard') }}" class="admin-brand admin-sidebar-brand" aria-label="Institute of Hospitality superadmin dashboard"><img src="{{ asset('img/logo.png') }}" alt="Institute of Hospitality" class="admin-brand-logo"></a>
        <nav aria-label="Superadmin navigation">
            <a href="{{ route('superadmin.dashboard') }}" class="admin-nav-link active">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <span>User Access Management</span>
            </a>
        </nav>
        <form action="{{ route('superadmin.logout') }}" method="POST" class="admin-logout-form">
            @csrf
            <button type="submit" class="admin-nav-link">
                <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"></path></svg>
                <span>Sign Out Superadmin</span>
            </button>
        </form>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <button id="adminMenuToggle" class="admin-menu-toggle" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false"><span></span><span></span><span></span></button>
            <div>
                <span class="superadmin-badge"><i></i> Superadmin Master Access</span>
                <h1 style="margin: 4px 0 0; font-size: 24px; font-weight: 800; color: #0f172a;">User Access Management<span>.</span></h1>
                <p style="margin: 2px 0 0; font-size: 12px; color: #64748b;">Manage administrative user accounts, assigned system roles, and page access permissions</p>
            </div>
            <div class="admin-user-chip" style="border-color:#fca5a5; background:#fff1f1;">
                <span style="background:#dc2626; color:#fff;">S</span>
                <div>
                    <strong>{{ session('superadmin_username', 'superadmin') }}</strong>
                    <small style="color:#dc2626; font-weight:700;">Superadministrator</small>
                </div>
            </div>
        </header>

        @if(session('status'))
            <div class="admin-page-alert configuration-success" role="status" style="margin-bottom: 20px;">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="admin-page-alert admin-alert-danger" role="alert" style="margin-bottom: 20px; padding: 14px 18px; background: #fff1f1; border: 1px solid #fecaca; border-radius: 10px; color: #991b1b; font-size: 12px; font-weight: 600;">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="configuration-split-layout">
            <!-- Add Account Form -->
            <section class="admin-panel configuration-panel">
                <div class="configuration-panel-heading">
                    <div>
                        <span>ACCOUNT CREATION</span>
                        <h2>Add User Account</h2>
                        <p>Grant admin credentials and assign specific page access permissions.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('superadmin.users.store') }}" class="configuration-form">
                    @csrf

                    <div style="display: grid; gap: 16px;">
                        <label class="configuration-field">
                            <span>Full Name <b>*</b></span>
                            <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required autofocus placeholder="e.g. John Doe">
                        </label>

                        <label class="configuration-field">
                            <span>Username <b>*</b></span>
                            <input type="text" name="username" value="{{ old('username') }}" minlength="3" maxlength="100" pattern="[A-Za-z0-9_-]+" required autocomplete="username" placeholder="e.g. john_doe">
                            <small style="margin-top: 5px; display: block; color: #64748b; font-size: 10px;">Used with the password on the Admin Login page.</small>
                        </label>

                        <label class="configuration-field">
                            <span>Email Address <b>*</b></span>
                            <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required placeholder="e.g. john@tractionguest.com">
                        </label>

                        <label class="configuration-field">
                            <span>Password <b>*</b></span>
                            <input type="password" name="password" required placeholder="Minimum 6 characters">
                        </label>

                        <label class="configuration-field">
                            <span>Assigned System Role <b>*</b></span>
                            <input type="text" name="role" value="{{ old('role') }}" maxlength="50" required placeholder="e.g. Administrator, Gate Guard, Desk Officer">
                        </label>

                        <label class="configuration-field">
                            <span>Initial Account Status <b>*</b></span>
                            <select name="status" required style="width: 100%; height: 46px; padding: 0 14px; color: #172033; background: #fff; border: 1px solid #d8e0e7; border-radius: 9px; font: 500 12px Inter, sans-serif; outline: none; cursor: pointer;">
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active (Access Granted)</option>
                                <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended (Access Disabled)</option>
                            </select>
                        </label>

                        <!-- PAGE PERMISSIONS CHECKBOXES -->
                        <fieldset class="configuration-field" style="margin-top: 10px; border: none; padding: 0;">
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; margin-bottom: 8px;">
                                <span style="font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Allowed Admin Panel Page Permissions</span>
                                <button type="button" id="toggleAllPermissions" style="background: none; border: none; font-size: 11px; font-weight: 700; color: #2563eb; cursor: pointer; text-decoration: underline;">Select / Deselect All</button>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; padding: 14px; background: #fafbf8; border: 1px solid #e1e7da; border-radius: 10px;">
                                @foreach($availablePages as $pageKey => $pageName)
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #172033; cursor: pointer; user-select: none;">
                                        <input type="checkbox" name="permissions[]" value="{{ $pageKey }}" class="page-permission-checkbox" style="width: 16px; height: 16px; accent-color: #16a34a; cursor: pointer;" checked>
                                        <span>{{ $pageName }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <div class="configuration-actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #edf0f2;">
                        <p style="margin: 0; font-size: 10px; color: #7c8997;">User credentials and permissions take effect immediately.</p>
                        <button type="submit" class="btn btn-primary">
                            Create Account
                            <span>→</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- System Users List -->
            <section class="admin-panel configuration-panel">
                <div class="configuration-panel-heading">
                    <div>
                        <span>USER DIRECTORY</span>
                        <h2>System Users</h2>
                        <p>Accounts with access to the admin system.</p>
                    </div>
                    <span class="configuration-active-badge"><i></i> {{ $users->where('status', 'active')->count() }} Active Users</span>
                </div>

                <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="admin-visitors-table" style="width: 100%; min-width: 650px; border-collapse: separate; border-spacing: 0;">
                        <thead>
                            <tr style="background: #f8fafc; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                                <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">User &amp; Email</th>
                                <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Assigned Role</th>
                                <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Allowed Page Permissions</th>
                                <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Status</th>
                                <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                    <td style="padding: 14px; vertical-align: middle;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #c8e063; color: #111; display: grid; place-items: center; font-weight: 800; font-size: 12px; flex: 0 0 32px;">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong style="display: block; font-size: 12px; color: #0f172a;">{{ $user->name }}</strong>
                                                <small style="display: block; font-size: 10px; color: #64748b; margin-top: 2px;">{{ $user->email }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 14px; vertical-align: middle;">
                                        <span style="display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #1e293b; background: #f1f5f9; border: 1px solid #cbd5e1;">
                                            {{ $user->role }}
                                        </span>
                                    </td>
                                    <td style="padding: 14px; vertical-align: middle;">
                                        @php
                                            $userPerms = is_array($user->permissions) ? $user->permissions : [];
                                            $allCount = count($availablePages);
                                            $permCount = count($userPerms);
                                        @endphp
                                        
                                        @if($permCount === 0)
                                            <span style="font-size: 10px; color: #94a3b8; font-style: italic;">No Page Permissions</span>
                                        @elseif($permCount >= $allCount)
                                            <span class="permission-badge all">Full Access (All {{ $allCount }} Pages)</span>
                                        @else
                                            <div style="display: flex; flex-wrap: wrap; gap: 2px; max-width: 220px;">
                                                @foreach($userPerms as $perm)
                                                    <span class="permission-badge">{{ $perm }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td style="padding: 14px; vertical-align: middle;">
                                        <form method="POST" action="{{ route('superadmin.users.toggle', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;">
                                                @if($user->status === 'active')
                                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 9px; border-radius: 20px;">
                                                        <i style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></i> Active
                                                    </span>
                                                @else
                                                    <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; padding: 4px 9px; border-radius: 20px;">
                                                        <i style="width: 6px; height: 6px; border-radius: 50%; background: #ef4444;"></i> Suspended
                                                    </span>
                                                @endif
                                            </button>
                                        </form>
                                    </td>
                                    <td style="padding: 14px; vertical-align: middle; text-align: right;">
                                        <form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user account?');" style="display: inline-block;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="padding: 6px 10px; font-size: 10px; font-weight: 700; color: #ef4444; background: #fef2f2; border: 1px solid #fee2e2; border-radius: 6px; cursor: pointer; transition: all 0.15s ease;">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 30px; text-align: center; color: #94a3b8; font-size: 12px;">No user accounts found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
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

    // Toggle All Permissions Checkboxes
    const toggleBtn = document.getElementById('toggleAllPermissions');
    if (toggleBtn) {
        let allChecked = true;
        toggleBtn.addEventListener('click', () => {
            allChecked = !allChecked;
            document.querySelectorAll('.page-permission-checkbox').forEach(cb => {
                cb.checked = allChecked;
            });
        });
    }
</script>
</body>
</html>
