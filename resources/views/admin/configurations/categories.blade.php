@extends('layouts.admin')

@section('title', 'Visitor Categories')

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Visitor Categories<span>.</span></h1>
        <p>Define visitor classifications, entry fees, and badge color coding</p>
    </div>
@endsection

@section('content')
    <nav class="configuration-tabs" aria-label="Master configuration sections">
        <a href="{{ route('admin.configurations.event.edit') }}">Event Configurations</a>
        <a href="{{ route('admin.configurations.capacity.edit') }}">Occupancy Limit</a>
        <a class="active" href="{{ route('admin.configurations.categories.index') }}" aria-current="page">Visitor Categories</a>
        <a href="{{ route('admin.configurations.users.index') }}">Users &amp; Access</a>
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
        <!-- Add Category Form -->
        <section class="admin-panel configuration-panel">
            <div class="configuration-panel-heading">
                <div>
                    <span>CLASSIFICATION FORM</span>
                    <h2>Create New Category</h2>
                    <p>Configure badge appearance and entrance pricing tier.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.configurations.categories.store') }}" class="configuration-form">
                @csrf

                <div style="display: grid; gap: 16px;">
                    <label class="configuration-field">
                        <span>Category Name <b>*</b></span>
                        <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required autofocus placeholder="e.g. VIP Guest">
                    </label>

                    <label class="configuration-field">
                        <span>Category Code / Identifier</span>
                        <input type="text" name="code" value="{{ old('code') }}" maxlength="50" placeholder="e.g. vip (auto-generated if empty)">
                    </label>

                    <label class="configuration-field">
                        <span>Entrance Fee (LKR) <b>*</b></span>
                        <input type="number" step="0.01" name="entrance_fee" value="{{ old('entrance_fee', '0.00') }}" min="0" required placeholder="0.00">
                    </label>

                    <label class="configuration-field">
                        <span>Badge Color <b>*</b></span>
                        <div style="display: flex; align-items: center; gap: 10px; width: 100%; min-width: 0;">
                            <input type="color" name="badge_color" value="{{ old('badge_color', '#C8E063') }}" style="width: 46px; height: 46px; flex: 0 0 46px; padding: 3px; border: 1px solid #d8e0e7; border-radius: 9px; cursor: pointer; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
                            <input type="text" id="colorText" value="{{ old('badge_color', '#C8E063') }}" readonly style="flex: 1; min-width: 0; width: 100%; height: 46px; padding: 0 14px; color: #172033; background: #fff; border: 1px solid #d8e0e7; border-radius: 9px; font: 700 12px Inter, sans-serif; text-transform: uppercase; outline: none; letter-spacing: 0.5px;">
                        </div>
                    </label>

                    <label class="configuration-field">
                        <span>Description</span>
                        <textarea name="description" rows="2" placeholder="Brief details about privileges or access zones for this category..." style="width: 100%; min-height: 80px; padding: 12px 14px; color: #172033; background: #fff; border: 1px solid #d8e0e7; border-radius: 9px; font: 500 12px Inter, sans-serif; outline: none; transition: border-color .15s, box-shadow .15s;">{{ old('description') }}</textarea>
                    </label>

                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 12px; font-weight: 700; color: #334155; margin-top: 4px;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} style="width: 17px; height: 17px; accent-color: #9cb62f; cursor: pointer;">
                        <span>Enable category for new visitor registrations</span>
                    </label>
                </div>

                <div class="configuration-actions" style="margin-top: 24px; padding-top: 18px; border-top: 1px solid #edf0f2;">
                    <p style="margin: 0; font-size: 10px; color: #7c8997;">Active categories will be selectable in identity kiosk &amp; verification flows.</p>
                    <button type="submit" class="btn btn-primary">
                        Save Category
                        <span>→</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- Categories List -->
        <section class="admin-panel configuration-panel">
            <div class="configuration-panel-heading">
                <div>
                    <span>ACTIVE DIRECTORY</span>
                    <h2>Configured Categories</h2>
                    <p>Live classification categories configured in the system.</p>
                </div>
                <span class="configuration-active-badge"><i></i> {{ $categories->where('is_active', true)->count() }} Active</span>
            </div>

            <div style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="admin-visitors-table" style="width: 100%; min-width: 580px; border-collapse: separate; border-spacing: 0;">
                    <thead>
                        <tr style="background: #f8fafc; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;">
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Category &amp; Color</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Code</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Entrance Fee</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2;">Status</th>
                            <th style="padding: 12px 14px; border-bottom: 1px solid #edf0f2; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;">
                                <td style="padding: 14px; vertical-align: top;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="display: inline-block; width: 14px; height: 14px; border-radius: 50%; background: {{ $category->badge_color }}; border: 2px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; flex: 0 0 14px;"></span>
                                        <div>
                                            <strong style="display: block; font-size: 12px; color: #0f172a;">{{ $category->name }}</strong>
                                            @if($category->description)
                                                <small style="display: block; font-size: 10px; color: #64748b; margin-top: 2px;">{{ Str::limit($category->description, 50) }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 14px; vertical-align: middle; font-family: monospace; font-size: 11px; color: #475569;">
                                    <code>{{ $category->code }}</code>
                                </td>
                                <td style="padding: 14px; vertical-align: middle; font-weight: 700; font-size: 12px; color: #1e293b;">
                                    @if($category->entrance_fee > 0)
                                        LKR {{ number_format($category->entrance_fee, 2) }}
                                    @else
                                        <span style="color: #16a34a; font-weight: 800; font-size: 10px; text-transform: uppercase; background: #dcfce7; padding: 3px 8px; border-radius: 4px;">Free</span>
                                    @endif
                                </td>
                                <td style="padding: 14px; vertical-align: middle;">
                                    <form method="POST" action="{{ route('admin.configurations.categories.toggle', $category) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;">
                                            @if($category->is_active)
                                                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 9px; border-radius: 20px;">
                                                    <i style="width: 6px; height: 6px; border-radius: 50%; background: #22c55e;"></i> Active
                                                </span>
                                            @else
                                                <span style="display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 800; text-transform: uppercase; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 4px 9px; border-radius: 20px;">
                                                    <i style="width: 6px; height: 6px; border-radius: 50%; background: #94a3b8;"></i> Disabled
                                                </span>
                                            @endif
                                        </button>
                                    </form>
                                </td>
                                <td style="padding: 14px; vertical-align: middle; text-align: right;">
                                    <form method="POST" action="{{ route('admin.configurations.categories.destroy', $category) }}" onsubmit="return confirm('Are you sure you want to remove this visitor category?');" style="display: inline-block;">
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
                                <td colspan="5" style="padding: 30px; text-align: center; color: #94a3b8; font-size: 12px;">No visitor categories defined yet. Use the form on the left to add one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
    <script>
        const colorInput = document.querySelector('input[name="badge_color"]');
        const colorText = document.getElementById('colorText');
        if (colorInput && colorText) {
            colorInput.addEventListener('input', (e) => {
                colorText.value = e.target.value.toUpperCase();
            });
        }
    </script>
    @endpush
@endsection
