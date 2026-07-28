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
    <nav class="configuration-tabs" aria-label="Master configuration sections">
        <a href="{{ route('admin.configurations.event.edit') }}">Event Configurations</a>
        <a href="{{ route('admin.configurations.capacity.edit') }}">Occupancy Limit</a>
        <a href="{{ route('admin.configurations.categories.index') }}">Visitor Categories</a>
        <a class="active" href="{{ route('admin.configurations.users.index') }}" aria-current="page">Users &amp; Access</a>
    </nav>

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
                        <span>Email Address <b>*</b></span>
                        <input type="email" name="email" value="{{ old('email') }}" maxlength="255" required placeholder="e.g. john@tractionguest.com">
                    </label>

                    <label class="configuration-field">
                        <span>Password <b>*</b></span>
                        <input type="password" name="password" required placeholder="Minimum 8 characters">
                    </label>

                    <label class="configuration-field">
                        <span>Assigned System Role <b>*</b></span>
                        <select name="role" required style="width: 100%; height: 46px; padding: 0 14px; color: #172033; background: #fff; border: 1px solid #d8e0e7; border-radius: 9px; font: 500 12px Inter, sans-serif; outline: none; cursor: pointer;">
                            <option value="Administrator" {{ old('role') === 'Administrator' ? 'selected' : '' }}>Administrator (Full Access)</option>
                            <option value="Gate Guard" {{ old('role', 'Gate Guard') === 'Gate Guard' ? 'selected' : '' }}>Gate Guard (Check-In / Out Terminal)</option>
                            <option value="Desk Officer" {{ old('role') === 'Desk Officer' ? 'selected' : '' }}>Desk Officer (Registration / Verification Desk)</option>
                            <option value="Auditor" {{ old('role') === 'Auditor' ? 'selected' : '' }}>Auditor (Read-only Reports & Logs)</option>
                        </select>
                    </label>

                    <label class="configuration-field">
                        <span>Initial Account Status <b>*</b></span>
                        <select name="status" required style="width: 100%; height: 46px; padding: 0 14px; color: #172033; background: #fff; border: 1px solid #d8e0e7; border-radius: 9px; font: 500 12px Inter, sans-serif; outline: none; cursor: pointer;">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active (Access Granted)</option>
                            <option value="suspended" {{ old('status') === 'suspended' ? 'selected' : '' }}>Suspended (Access Disabled)</option>
                        </select>
                    </label>
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
                <table class="admin-visitors-table" style="width: 100%; min-width: 580px; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">User &amp; Email</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Role</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Status</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Created</th>
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
