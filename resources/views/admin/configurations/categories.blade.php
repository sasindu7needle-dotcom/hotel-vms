@extends('layouts.admin')

@section('title', 'Visitor Categories')

@section('header')
    <div>
        <span class="tagline no-margin">MASTER CONFIGURATIONS</span>
        <h1>Visitor Category<span>.</span></h1>
        <p>Create a category and define the times it is allowed to access the event.</p>
    </div>
@endsection

@section('content')
    @if(session('status'))
        <div class="admin-page-alert configuration-success" role="status">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="admin-page-alert admin-alert-danger" role="alert">
            @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    @php
        $isEditing = (bool) $selectedCategory;
        $schedule = old('access_schedule', $selectedCategory?->access_schedule ?? []);
        $schedule = count($schedule) ? $schedule : [['date' => '', 'from' => '', 'to' => '']];
    @endphp

    <section class="admin-panel category-config-panel">
        <div class="configuration-panel-heading category-panel-heading">
            <div>
                <span>CLASSIFICATION SETTINGS</span>
                <h2>{{ $isEditing ? 'Edit Visitor Category' : 'Create Visitor Category' }}</h2>
                <p>{{ $isEditing ? 'Update the category details and permitted entry windows.' : 'Set up a visitor type with its fee and permitted entry windows.' }}</p>
            </div>
            @if($isEditing)
                <form method="POST" action="{{ route('admin.configurations.categories.toggle', $selectedCategory) }}" class="category-status-control">
                    @csrf
                    @method('PATCH')
                    <span class="configuration-active-badge @if(!$selectedCategory->is_active) category-badge-disabled @endif"><i></i> {{ $selectedCategory->is_active ? 'Active' : 'Disabled' }}</span>
                    <button type="submit">{{ $selectedCategory->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
            @else
                <span class="configuration-active-badge"><i></i> {{ $categories->count() }} configured</span>
            @endif
        </div>

        <form method="POST" action="{{ $isEditing ? route('admin.configurations.categories.update', $selectedCategory) : route('admin.configurations.categories.store') }}" class="category-config-form">
            @csrf
            @if($isEditing) @method('PUT') @endif

            <div class="category-picker-card">
                <div class="category-picker-form">
                    <label for="category-picker">Select a category</label>
                    <select id="category-picker" onchange="window.location.href = this.value ? '{{ route('admin.configurations.categories.index') }}?category=' + this.value : '{{ route('admin.configurations.categories.index') }}'">
                        <option value="">Create a new category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory?->id === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <small>Select an existing category to load it for editing.</small>
                </div>
                @if($categories->isNotEmpty())
                    <div class="category-directory" aria-label="Configured visitor categories">
                        @foreach($categories as $category)
                            <a href="{{ route('admin.configurations.categories.index', ['category' => $category->id]) }}" class="category-directory-item @if($selectedCategory?->id === $category->id) active @endif">
                                <span class="category-directory-dot" style="background: {{ $category->badge_color }}"></span>
                                <span><strong>{{ $category->name }}</strong><small>LKR {{ number_format((float) $category->entrance_fee, 2) }}</small></span>
                                @if($category->is_active)<em>Active</em>@else<em class="disabled">Disabled</em>@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="category-fields">
                <label>
                    <span>Category Name <b>*</b></span>
                    <input type="text" name="name" value="{{ old('name', $selectedCategory?->name) }}" maxlength="255" required placeholder="e.g. VIP Guest">
                </label>
                <label>
                    <span>Entrance Fee (LKR) <b>*</b></span>
                    <input type="number" name="entrance_fee" step="0.01" min="0" value="{{ old('entrance_fee', $selectedCategory?->entrance_fee ?? '0.00') }}" required placeholder="0.00">
                </label>
            </div>

            <input type="hidden" name="badge_color" value="{{ old('badge_color', $selectedCategory?->badge_color ?? '#5d9bd3') }}">
            @if(!$isEditing)<input type="hidden" name="is_active" value="1">@endif

            <section class="access-time-section" aria-labelledby="access-time-heading">
                <div class="access-time-heading">
                    <div>
                        <span id="access-time-heading">Access Time</span>
                        <small>Configure the date and time windows this category can enter.</small>
                    </div>
                    <button type="button" class="btn btn-primary category-add-button" id="add-access-row">+ Add access time</button>
                </div>

                <div class="category-access-table-wrap">
                    <table class="category-access-table">
                        <thead><tr><th>Date</th><th>From</th><th>To</th><th><span class="sr-only">Remove</span></th></tr></thead>
                        <tbody id="access-schedule-body">
                            @foreach($schedule as $index => $slot)
                                <tr>
                                    <td><input type="date" name="access_schedule[{{ $index }}][date]" value="{{ $slot['date'] ?? '' }}" required></td>
                                    <td><input type="time" name="access_schedule[{{ $index }}][from]" value="{{ $slot['from'] ?? '' }}" required></td>
                                    <td><input type="time" name="access_schedule[{{ $index }}][to]" value="{{ $slot['to'] ?? '' }}" required></td>
                                    <td><button type="button" class="category-remove-row" aria-label="Remove this access time">Remove</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="category-form-actions">
                @if($isEditing)
                    <a href="{{ route('admin.configurations.categories.index') }}" class="category-cancel-link">Cancel editing</a>
                @else
                    <span>Access windows can be adjusted at any time.</span>
                @endif
                <button type="submit" class="btn btn-primary">{{ $isEditing ? 'Update Category' : 'Submit Category' }}</button>
            </div>
        </form>
    </section>

    <section class="admin-panel category-directory-panel">
        <div class="configuration-panel-heading">
            <div>
                <span>CONFIGURED CATEGORIES</span>
                <h2>Visitor category directory</h2>
                <p>Edit category details or remove categories that are no longer needed.</p>
            </div>
            <span class="configuration-active-badge"><i></i> {{ $categories->where('is_active', true)->count() }} active</span>
        </div>

        @if($categories->isNotEmpty())
            <div class="category-directory-table-wrap">
                <table class="category-directory-table">
                    <thead><tr><th>Category</th><th>Entrance Fee</th><th>Access Windows</th><th>Status</th><th class="category-actions-heading">Actions</th></tr></thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td>
                                    <div class="category-name-cell">
                                        <span style="background: {{ $category->badge_color }}"></span>
                                        <strong>{{ $category->name }}</strong>
                                    </div>
                                </td>
                                <td>LKR {{ number_format((float) $category->entrance_fee, 2) }}</td>
                                <td>{{ count($category->access_schedule ?? []) }} {{ count($category->access_schedule ?? []) === 1 ? 'window' : 'windows' }}</td>
                                <td><span class="category-status {{ $category->is_active ? 'is-active' : 'is-disabled' }}">{{ $category->is_active ? 'Active' : 'Disabled' }}</span></td>
                                <td>
                                    <div class="category-row-actions">
                                        <a href="{{ route('admin.configurations.categories.index', ['category' => $category->id]) }}">Edit</a>
                                        <form method="POST" action="{{ route('admin.configurations.categories.toggle', $category) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="category-toggle-button">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.configurations.categories.destroy', $category) }}" onsubmit="return confirm('Remove {{ addslashes($category->name) }}? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="category-directory-empty"><span>+</span><h3>No categories yet</h3><p>Create the first visitor category using the form above.</p></div>
        @endif
    </section>
@endsection

@push('styles')
<style>
    body.landing-page .category-config-panel { max-width:1080px; }
    body.landing-page .category-status-control { display:flex; align-items:center; gap:9px; margin:0; }
    body.landing-page .category-status-control button { min-height:32px; padding:0 10px; color:#536b00; background:#f2f8db; border:1px solid #dcebad; border-radius:6px; font:800 10px Inter,sans-serif; cursor:pointer; }
    body.landing-page .category-status-control .category-badge-disabled { color:#64748b; background:#f1f5f9; border-color:#e2e8f0; }
    body.landing-page .category-status-control .category-badge-disabled i { background:#94a3b8; }
    body.landing-page .category-directory-panel { max-width:1080px; margin-top:22px; }
    body.landing-page .category-panel-heading p { margin:5px 0 0; color:#7b8794; font-size:11px; }
    body.landing-page .category-config-form { padding:0; }
    body.landing-page .category-picker-card { padding:22px 28px; background:#fafbf8; border-bottom:1px solid #edf0f2; }
    body.landing-page .category-picker-form { display:grid; grid-template-columns:170px minmax(260px,1fr); gap:6px 18px; align-items:center; }
    body.landing-page .category-picker-form label { grid-row:span 2; color:#475569; font-size:11px; font-weight:800; }
    body.landing-page .category-picker-form select, body.landing-page .category-fields input, body.landing-page .category-access-table input { border:1px solid #d8e0e7; border-radius:9px; color:#172033; background:#fff; outline:none; font:500 12px Inter,sans-serif; }
    body.landing-page .category-picker-form select { height:44px; padding:0 13px; cursor:pointer; }
    body.landing-page .category-picker-form small { color:#7c8997; font-size:10px; }
    body.landing-page .category-directory { display:flex; flex-wrap:wrap; gap:9px; margin-top:18px; padding-top:17px; border-top:1px solid #e6ebdf; }
    body.landing-page .category-directory-item { display:flex; min-width:185px; flex:1 1 210px; align-items:center; gap:9px; padding:10px 11px; color:#334155; background:#fff; border:1px solid #e0e6dc; border-radius:9px; text-decoration:none; transition:border-color .15s, box-shadow .15s, background .15s; }
    body.landing-page .category-directory-item:hover, body.landing-page .category-directory-item.active { background:#f7fbe9; border-color:#bfd45a; box-shadow:0 3px 10px rgba(86,110,14,.08); }
    body.landing-page .category-directory-dot { width:10px; height:10px; flex:0 0 10px; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #cbd5e1; }
    body.landing-page .category-directory-item span:nth-child(2) { min-width:0; flex:1; }
    body.landing-page .category-directory-item strong, body.landing-page .category-directory-item small { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    body.landing-page .category-directory-item strong { color:#253043; font-size:11px; }
    body.landing-page .category-directory-item small { margin-top:3px; color:#7c8997; font-size:9px; }
    body.landing-page .category-directory-item em { padding:3px 6px; color:#59730f; background:#eff7d5; border-radius:4px; font-size:8px; font-style:normal; font-weight:800; text-transform:uppercase; }
    body.landing-page .category-directory-item em.disabled { color:#718096; background:#f1f5f9; }
    body.landing-page .category-directory-table-wrap { overflow-x:auto; }
    body.landing-page .category-directory-table { width:100%; min-width:670px; border-collapse:collapse; }
    body.landing-page .category-directory-table th { padding:12px 22px; color:#7b8795; background:#f8faf8; text-align:left; font-size:9px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; }
    body.landing-page .category-directory-table td { padding:14px 22px; color:#4b5b6d; border-top:1px solid #edf0f2; font-size:11px; font-weight:600; vertical-align:middle; }
    body.landing-page .category-name-cell { display:flex; align-items:center; gap:9px; color:#253043; }
    body.landing-page .category-name-cell>span { width:11px; height:11px; flex:0 0 11px; border:2px solid #fff; border-radius:50%; box-shadow:0 0 0 1px #cbd5e1; }
    body.landing-page .category-name-cell strong { font-size:12px; }
    body.landing-page .category-status { display:inline-block; padding:4px 8px; border-radius:999px; font-size:9px; font-weight:800; text-transform:uppercase; }
    body.landing-page .category-status.is-active { color:#4d6b0e; background:#f0f8d8; border:1px solid #d7e99a; }
    body.landing-page .category-status.is-disabled { color:#64748b; background:#f1f5f9; border:1px solid #e2e8f0; }
    body.landing-page .category-actions-heading { text-align:right; }
    body.landing-page .category-row-actions { display:flex; justify-content:flex-end; align-items:center; gap:9px; }
    body.landing-page .category-row-actions a, body.landing-page .category-row-actions button { display:inline-flex; align-items:center; justify-content:center; min-height:30px; padding:0 10px; border-radius:6px; font:800 10px Inter,sans-serif; text-decoration:none; cursor:pointer; }
    body.landing-page .category-row-actions a { color:#536b00; background:#f2f8db; border:1px solid #dcebad; }
    body.landing-page .category-row-actions form { margin:0; }
    body.landing-page .category-row-actions button { color:#c53e3e; background:#fff5f5; border:1px solid #f7d2d2; }
    body.landing-page .category-row-actions .category-toggle-button { color:#536b00; background:#f2f8db; border-color:#dcebad; }
    body.landing-page .category-directory-empty { padding:36px; text-align:center; }.category-directory-empty>span { display:grid; place-items:center; width:34px; height:34px; margin:0 auto 10px; color:#75880d; background:#f1f7d5; border-radius:50%; font-weight:800; }.category-directory-empty h3 { margin:0; color:#253043; font-size:14px; }.category-directory-empty p { margin:6px 0 0; color:#7c8997; font-size:11px; }
    body.landing-page .category-fields { display:grid; grid-template-columns:1fr 1fr; gap:20px; padding:26px 28px; }
    body.landing-page .category-fields label > span { display:block; margin:0 0 8px; color:#475569; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    body.landing-page .category-fields b { color:#ef4444; }
    body.landing-page .category-fields input { width:100%; height:46px; padding:0 14px; box-sizing:border-box; }
    body.landing-page .category-fields input:focus, body.landing-page .category-picker-form select:focus, body.landing-page .category-access-table input:focus { border-color:#a8bd38; box-shadow:0 0 0 3px rgba(200,224,99,.23); }
    body.landing-page .access-time-section { padding:0 28px 26px; }
    body.landing-page .access-time-heading, body.landing-page .category-form-actions { display:flex; justify-content:space-between; gap:20px; align-items:center; }
    body.landing-page .access-time-heading { padding:19px 0 14px; border-top:1px solid #edf0f2; }
    body.landing-page .access-time-heading span { display:block; color:#172033; font-size:14px; font-weight:800; }
    body.landing-page .access-time-heading small { display:block; margin-top:4px; color:#7c8997; font-size:10px; }
    body.landing-page .category-add-button { min-height:38px; padding:0 15px; font-size:11px; }
    body.landing-page .category-access-table-wrap { overflow-x:auto; border:1px solid #e1e6e9; border-radius:10px; }
    body.landing-page .category-access-table { width:100%; min-width:620px; border-collapse:collapse; }
    body.landing-page .category-access-table th { padding:11px 14px; color:#7b8795; background:#f8faf8; text-align:left; font-size:9px; font-weight:800; letter-spacing:.7px; text-transform:uppercase; }
    body.landing-page .category-access-table td { padding:10px 12px; background:#fff; border-top:1px solid #edf0f2; }
    body.landing-page .category-access-table input { width:100%; height:38px; padding:0 9px; box-sizing:border-box; }
    body.landing-page .category-remove-row { padding:7px 5px; color:#d64242; border:0; background:transparent; font:700 11px Inter,sans-serif; text-decoration:underline; cursor:pointer; }
    body.landing-page .category-form-actions { padding:19px 28px; background:#fafbfb; border-top:1px solid #edf0f2; }
    body.landing-page .category-form-actions > span, body.landing-page .category-cancel-link { color:#7c8997; font-size:10px; }
    body.landing-page .category-cancel-link { font-weight:700; text-decoration:underline; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    @media(max-width:700px) { body.landing-page .category-panel-heading, body.landing-page .category-form-actions { display:block; } body.landing-page .category-panel-heading .configuration-active-badge { display:inline-flex; margin-top:14px; } body.landing-page .category-picker-card, body.landing-page .category-fields, body.landing-page .access-time-section { padding-left:18px; padding-right:18px; } body.landing-page .category-picker-form { grid-template-columns:1fr; } body.landing-page .category-picker-form label { grid-row:auto; } body.landing-page .category-fields { grid-template-columns:1fr; gap:16px; } body.landing-page .category-add-button { margin-top:12px; } body.landing-page .category-form-actions .btn { margin-top:15px; } }
</style>
@endpush

@push('scripts')
<script>
    const accessBody = document.getElementById('access-schedule-body');
    const addAccessRow = document.getElementById('add-access-row');
    const renumberAccessRows = () => [...accessBody.rows].forEach((row, index) => {
        row.querySelectorAll('input').forEach(input => input.name = input.name.replace(/access_schedule\[\d+\]/, `access_schedule[${index}]`));
    });
    addAccessRow.addEventListener('click', () => {
        const index = accessBody.rows.length;
        accessBody.insertAdjacentHTML('beforeend', `<tr><td><input type="date" name="access_schedule[${index}][date]" required></td><td><input type="time" name="access_schedule[${index}][from]" required></td><td><input type="time" name="access_schedule[${index}][to]" required></td><td><button type="button" class="category-remove-row" aria-label="Remove this access time">Remove</button></td></tr>`);
    });
    accessBody.addEventListener('click', event => {
        if (!event.target.classList.contains('category-remove-row')) return;
        if (accessBody.rows.length === 1) { accessBody.rows[0].querySelectorAll('input').forEach(input => input.value = ''); return; }
        event.target.closest('tr').remove(); renumberAccessRows();
    });
</script>
@endpush
