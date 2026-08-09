@extends('layouts.admin')

@section('title', 'Exhibitor Directory')

@section('header')
    <div><span class="tagline no-margin">EXHIBITOR DIRECTORY</span><h1>Profiles and members<span>.</span></h1><p>Search for an exhibitor, then select its company to review the complete profile and member cards.</p></div>
@endsection

@section('content')
    @php($directoryQuery = array_filter(['search' => $search], fn ($value) => $value !== null && $value !== ''))

    <section class="admin-panel exhibitor-directory">
        <div class="configuration-panel-heading exhibitor-directory-heading"><div><span>EXHIBITOR DIRECTORY</span><h2>Profiles and members</h2><p>Choose a company to see its registration details and member cards.</p></div><span class="configuration-active-badge"><i></i> {{ $exhibitors->count() }} {{ $search ? 'results' : 'total' }}</span></div>

        <form class="exhibitor-search" method="GET" action="{{ route('admin.exhibitors.directory') }}">
            <label for="exhibitor-search-input">Search exhibitor</label>
            <div class="exhibitor-search-controls"><input id="exhibitor-search-input" name="search" value="{{ $search }}" type="search" placeholder="Search by company name" autocomplete="off"><button type="submit" class="btn btn-primary">Search</button>@if($search)<a href="{{ route('admin.exhibitors.directory') }}" class="exhibitor-clear-search">Clear search</a>@endif</div>
        </form>

        <div class="exhibitor-directory-wrap"><table class="admin-visitors-table exhibitor-table"><thead><tr><th>Exhibitor</th><th>Registration</th><th>Package</th><th>Members</th><th>Registration link</th></tr></thead><tbody>
            @forelse($exhibitors as $exhibitor)
                @php($company = $exhibitor->company_name ?: 'Profile pending')
                @php($companyUrl = route('admin.exhibitors.directory', array_merge($directoryQuery, ['exhibitor' => $exhibitor->id])))
                <tr class="{{ $selectedExhibitor?->id === $exhibitor->id ? 'selected' : '' }}"><td><a class="exhibitor-company-link" href="{{ $companyUrl }}#exhibitor-details" aria-current="{{ $selectedExhibitor?->id === $exhibitor->id ? 'page' : 'false' }}"><strong>{{ $company }}</strong><small>{{ $exhibitor->user->username }}</small></a></td><td><span class="exhibitor-status {{ $exhibitor->registered_at ? 'complete' : 'pending' }}">{{ $exhibitor->registered_at ? 'Complete' : 'Awaiting exhibitor' }}</span></td><td>{{ $exhibitor->package ? ucfirst($exhibitor->package) : '—' }}</td><td>{{ $exhibitor->members_count }} / {{ $exhibitor->member_limit ?: '—' }}</td><td><a href="{{ route('exhibitor.registration.show', $exhibitor) }}" target="_blank" rel="noopener">Open registration</a></td></tr>
            @empty
                <tr><td colspan="5" class="exhibitor-empty">No exhibitor profiles match your search.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    @if($selectedExhibitor)
        <section id="exhibitor-details" class="admin-panel exhibitor-detail">
            <div class="exhibitor-detail-heading"><div><span>SELECTED EXHIBITOR</span><h2>{{ $selectedExhibitor->company_name ?: $selectedExhibitor->user->username }}</h2><p>Company profile, registration status, and member cards.</p></div><a href="{{ route('admin.exhibitors.directory', $directoryQuery) }}" class="exhibitor-close-detail">Clear selection</a></div>

            <div class="exhibitor-profile-details">
                <div><span>Company name</span><strong>{{ $selectedExhibitor->company_name ?: 'Not completed' }}</strong></div>
                <div><span>Username</span><strong>{{ $selectedExhibitor->user->username }}</strong></div>
                <div><span>NGJA file number</span><strong>{{ $selectedExhibitor->ngja_file_number ?: '—' }}</strong></div>
                <div><span>Phone number</span><strong>{{ $selectedExhibitor->phone_number ?: '—' }}</strong></div>
                <div><span>Email address</span><strong>{{ $selectedExhibitor->email ?: '—' }}</strong></div>
                <div><span>Name board</span><strong>{{ $selectedExhibitor->name_board ?: '—' }}</strong></div>
                <div><span>Package</span><strong>{{ $selectedExhibitor->package ? ucfirst($selectedExhibitor->package) : '—' }}</strong></div>
                <div><span>Member allocation</span><strong>{{ $selectedExhibitor->members_count }} / {{ $selectedExhibitor->member_limit ?: '—' }}</strong></div>
            </div>

            <div class="exhibitor-admin-members">
                <div><span>MEMBER CARDS</span><h3>{{ $selectedExhibitor->members_count }} registered {{ Str::plural('member', $selectedExhibitor->members_count) }}</h3></div>
                <div class="exhibitor-directory-wrap"><table class="admin-visitors-table exhibitor-table"><thead><tr><th>Member</th><th>NIC / ID</th><th>Contact</th><th>Occupation</th><th>Card printing</th><th class="exhibitor-member-actions-heading">Actions</th></tr></thead><tbody>
                    @forelse($selectedExhibitor->members as $member)
                        <tr><td><strong>{{ $member->full_name }}</strong></td><td>{{ $member->document_number ?: '—' }}</td><td>{{ $member->mobile_number ?: '—' }}</td><td>{{ $member->occupation ?: '—' }}</td><td><a class="exhibitor-print-card" href="{{ route('admin.visitors.badge', $member) }}" target="_blank" rel="noopener">Print card</a></td><td><form method="POST" action="{{ route('admin.exhibitors.members.destroy', ['exhibitorId' => $selectedExhibitor->id, 'member' => $member, 'search' => $search]) }}" class="exhibitor-delete-member-form" onsubmit="return confirm('Delete this member? This cannot be undone.');">@csrf @method('DELETE')<button type="submit" class="exhibitor-delete-member">Delete</button></form></td></tr>
                    @empty
                        <tr><td colspan="6" class="exhibitor-empty">No member cards have been issued for this exhibitor yet.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>
        </section>
    @elseif($exhibitors->isNotEmpty())
        <div class="exhibitor-select-hint">Select a company name above to view its full profile and member cards.</div>
    @endif
@endsection

@push('styles')
<style>
.exhibitor-directory-heading { align-items: flex-start; }

.exhibitor-directory .exhibitor-search {
    margin: 24px 24px 16px;
    padding: 16px;
    border: 1px solid #e6ebef;
    border-radius: 10px;
    background: #f8fafc;
}

.exhibitor-search label { display: block; margin-bottom: 8px; color: #64748b; font-size: 11px; font-weight: 800; }
.exhibitor-search-controls { display: flex; align-items: center; gap: 10px; }
.exhibitor-search input { flex: 1; min-width: 0; padding: 10px 12px; border: 1px solid #d8e0e7; border-radius: 7px; background: #fff; color: #172033; font: 500 13px Inter, sans-serif; }
.exhibitor-search .btn { padding: 10px 16px; font-size: 11px; }
.exhibitor-clear-search, .exhibitor-close-detail { color: #58720a; font-size: 11px; font-weight: 800; text-decoration: none; }
.exhibitor-clear-search { padding: 9px 5px; white-space: nowrap; }

.exhibitor-directory-wrap { overflow: auto; -webkit-overflow-scrolling: touch; }
.exhibitor-directory > .exhibitor-directory-wrap { margin: 0 24px 24px; border: 1px solid #edf0f2; border-radius: 10px; }
.exhibitor-table { width: 100%; min-width: 720px; border-collapse: collapse; text-align: left; }
.exhibitor-table th { padding: 12px 16px; color: #718096; background: #f8fafc; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.exhibitor-table td { padding: 14px 16px; color: #334155; border-top: 1px solid #edf0f2; font-size: 12px; font-weight: 600; vertical-align: middle; }
.exhibitor-table small { display: block; margin-top: 4px; color: #64748b; font-size: 10px; }
.exhibitor-table a { color: #58720a; font-weight: 800; font-size: 11px; }
.exhibitor-table tr.selected td { background: #f4f9df; }
.exhibitor-company-link { display: block; color: #172033 !important; text-decoration: none; }
.exhibitor-company-link:hover strong { color: #58720a; }
.exhibitor-status { display: inline-block; padding: 4px 8px; border-radius: 20px; font-size: 10px; font-weight: 800; }
.exhibitor-status.complete { color: #3f6212; background: #ecfccb; }
.exhibitor-status.pending { color: #9a6700; background: #fef3c7; }
.exhibitor-empty { padding: 30px !important; color: #94a3b8; text-align: center !important; }

.exhibitor-detail { margin-top: 28px; padding: 28px; scroll-margin-top: 20px; }
.exhibitor-detail-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; }
.exhibitor-detail-heading > div > span, .exhibitor-admin-members > div:first-child > span, .exhibitor-profile-details span { color: #718096; font: 800 10px Inter, sans-serif; letter-spacing: .09em; }
.exhibitor-detail-heading h2 { margin: 7px 0 6px; color: #172033; }
.exhibitor-detail-heading p { margin: 0; color: #64748b; font-size: 12px; }
.exhibitor-close-detail { padding: 8px 10px; border: 1px solid #d8e0e7; border-radius: 7px; white-space: nowrap; }
.exhibitor-profile-details { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 24px; }
.exhibitor-profile-details > div { min-height: 74px; padding: 14px; border: 1px solid #edf0f2; border-radius: 8px; background: #f8fafc; }
.exhibitor-profile-details span { display: block; }
.exhibitor-profile-details strong { display: block; margin-top: 7px; color: #172033; font-size: 12px; line-height: 1.4; word-break: break-word; }
.exhibitor-admin-members { margin-top: 28px; padding-top: 24px; border-top: 1px solid #edf0f2; }
.exhibitor-admin-members h3 { margin: 6px 0 16px; color: #172033; font-size: 16px; }
.exhibitor-admin-members .exhibitor-directory-wrap { border: 1px solid #edf0f2; border-radius: 10px; }
.exhibitor-print-card { display: inline-flex !important; padding: 7px 11px; border: 1px solid #aeca37; border-radius: 7px; background: #c8e063; color: #273000 !important; text-decoration: none; }
.exhibitor-member-actions-heading { width: 1%; white-space: nowrap; }
.exhibitor-delete-member-form { margin: 0; }
.exhibitor-delete-member { padding: 7px 11px; border: 1px solid #f1b6b2; border-radius: 7px; color: #b42318; background: #fff5f4; font: 800 11px Inter, sans-serif; cursor: pointer; }
.exhibitor-delete-member:hover { border-color: #dc2626; color: #fff; background: #dc2626; }
.exhibitor-select-hint { margin-top: 24px; padding: 14px 16px; border: 1px dashed #cfd8b3; border-radius: 9px; color: #64748b; font-size: 12px; text-align: center; }

@media (max-width: 850px) {
    .exhibitor-profile-details { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .exhibitor-search-controls { flex-wrap: wrap; }
    .exhibitor-search input { flex-basis: 100%; }
}

@media (max-width: 500px) {
    .exhibitor-directory .exhibitor-search, .exhibitor-directory > .exhibitor-directory-wrap { margin-right: 16px; margin-left: 16px; }
    .exhibitor-detail { padding: 20px 16px; }
    .exhibitor-detail-heading { display: grid; }
    .exhibitor-profile-details { grid-template-columns: 1fr; }
}
</style>
@endpush
