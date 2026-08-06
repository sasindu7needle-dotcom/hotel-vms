@extends('layouts.admin')

@section('title', 'Users & Access')

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Users &amp; Access<span>.</span></h1>
        <p>Manage administrative accounts, security personnel, and role permissions</p>
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
                    <p>Grant admin or staff credentials to access portal features.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.configurations.users.store') }}" class="configuration-form">
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
                        <input type="password" name="password" required placeholder="Minimum 8 characters">
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
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; padding: 14px; background: #fafbf8; border: 1px solid #e1e7da; border-radius: 10px;">
                            @foreach($availablePages as $pageKey => $pageName)
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #172033; cursor: pointer; user-select: none;">
                                    <input type="checkbox" name="permissions[]" value="{{ $pageKey }}" style="width: 16px; height: 16px; accent-color: #75880d; cursor: pointer;" checked>
                                    <span>{{ $pageName }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>

                <div class="configuration-actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #edf0f2;">
                    <p style="margin: 0; font-size: 10px; color: #7c8997;">User credentials take effect immediately upon creation.</p>
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
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Role</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Permissions</th>
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
                                    @php
                                        $roleStyles = [
                                            'Administrator' => 'color: #365314; background: #ecfccb; border: 1px solid #d9f99d;',
                                            'Gate Guard' => 'color: #0369a1; background: #e0f2fe; border: 1px solid #bae6fd;',
                                            'Desk Officer' => 'color: #6b21a8; background: #f3e8ff; border: 1px solid #e9d5ff;',
                                            'Auditor' => 'color: #334155; background: #f1f5f9; border: 1px solid #cbd5e1;'
                                        ];
                                        $style = $roleStyles[$user->role] ?? 'color: #334155; background: #f1f5f9; border: 1px solid #cbd5e1;';
                                    @endphp
                                    <span style="display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; {{ $style }}">
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
                                        <span style="font-size: 10px; color: #94a3b8; font-style: italic;">No Permissions</span>
                                    @elseif($permCount >= $allCount)
                                        <span style="display: inline-block; padding: 2px 7px; border-radius: 5px; font-size: 10px; font-weight: 700; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0;">Full Access</span>
                                    @else
                                        <div style="display: flex; flex-wrap: wrap; gap: 2px; max-width: 200px;">
                                            @foreach($userPerms as $perm)
                                                <span style="display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe;">{{ $perm }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td style="padding: 14px; vertical-align: middle;">
                                    <form method="POST" action="{{ route('admin.configurations.users.toggle', $user) }}">
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
                                <td style="padding: 14px; vertical-align: middle; font-size: 10px; color: #64748b;">
                                    {{ $user->created_at->format('M j, Y') }}
                                </td>
                                <td style="padding: 14px; vertical-align: middle; text-align: right;">
                                    <form method="POST" action="{{ route('admin.configurations.users.destroy', $user) }}" onsubmit="return confirm('Are you sure you want to delete this user account?');" style="display: inline-block;">
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
@endsection
