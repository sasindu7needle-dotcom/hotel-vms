@extends('layouts.admin')

@section('title', 'Exhibitors')

@section('header')
    <div><span class="tagline no-margin">EXHIBITOR MANAGEMENT</span><h1>Exhibitors<span>.</span></h1><p>Create secure exhibitor registration links for the exhibitor portal.</p></div>
@endsection

@section('content')
    @if(session('status'))<div class="admin-page-alert configuration-success" role="status"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>{{ session('status') }}</div>@endif
    @if($errors->any())<div class="admin-page-alert admin-alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <div class="exhibitor-admin-grid">
        <section class="admin-panel configuration-panel">
            <div class="configuration-panel-heading"><div><span>STEP 1</span><h2>Create exhibitor access</h2><p>Generate the credentials used only for this exhibitor's registration portal.</p></div></div>
            <form method="POST" action="{{ route('admin.exhibitors.store') }}" class="configuration-form">
                @csrf
                <div class="exhibitor-create-fields">
                    <label class="configuration-field"><span>Username <b>*</b></span><input name="username" value="{{ old('username') }}" minlength="3" maxlength="100" pattern="[A-Za-z0-9_-]+" required autofocus placeholder="e.g. gemhouse_2026"></label>
                    <label class="configuration-field"><span>Temporary password <b>*</b></span><div class="exhibitor-password-field"><input id="exhibitor-password" type="text" name="password" minlength="10" required autocomplete="new-password" placeholder="Generate a password"><button type="button" id="generate-password">Generate</button></div><small>Copy this securely. It is not shown again after creation.</small></label>
                </div>
                <div class="configuration-actions"><p>The registration URL is created after saving this account.</p><button class="btn btn-primary" type="submit">Create exhibitor <span>&rarr;</span></button></div>
            </form>
        </section>

        @if(session('new_exhibitor_id'))
            @php($newExhibitor = $exhibitors->firstWhere('id', session('new_exhibitor_id')))
            @if($newExhibitor)
                <section class="admin-panel exhibitor-share-panel"><span>READY TO SHARE</span><h2>Registration details</h2><p>Send this URL with the temporary credentials to the exhibitor.</p><label>Registration URL<input readonly value="{{ route('exhibitor.registration.show', $newExhibitor) }}" onclick="this.select()"></label><div class="exhibitor-credentials"><div><span>USERNAME</span><strong>{{ $newExhibitor->user->username }}</strong></div><div><span>TEMPORARY PASSWORD</span><strong>{{ session('new_exhibitor_password') }}</strong></div></div></section>
            @endif
        @endif
    </div>
@endsection

@push('styles')
<style>
.exhibitor-admin-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,.8fr);gap:20px}.exhibitor-create-fields{display:grid;gap:16px}.exhibitor-password-field{display:flex;gap:8px}.exhibitor-password-field input{min-width:0}.exhibitor-password-field button{padding:0 12px;border:1px solid #d8e0e7;border-radius:8px;background:#f8fafc;color:#334155;font:700 11px Inter,sans-serif;cursor:pointer}.exhibitor-share-panel{padding:26px}.exhibitor-share-panel>span,.exhibitor-share-panel label span,.exhibitor-credentials span{font:800 10px Inter,sans-serif;letter-spacing:.09em;color:#718096}.exhibitor-share-panel h2{margin:7px 0;color:#172033}.exhibitor-share-panel p{font-size:12px;color:#64748b}.exhibitor-share-panel label{display:grid;gap:6px;margin-top:18px;font:800 10px Inter,sans-serif;color:#718096}.exhibitor-share-panel input{width:100%;padding:11px;border:1px solid #d8e0e7;border-radius:8px;font-size:11px}.exhibitor-credentials{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:15px}.exhibitor-credentials div{padding:11px;background:#f8fafc;border:1px solid #edf0f2;border-radius:8px}.exhibitor-credentials strong{display:block;margin-top:4px;font-size:12px;color:#172033;word-break:break-word}@media(max-width:850px){.exhibitor-admin-grid{grid-template-columns:1fr}}
</style>
@endpush

@push('scripts')
<script>document.getElementById('generate-password')?.addEventListener('click',()=>{const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';const password=Array.from(crypto.getRandomValues(new Uint32Array(14)),n=>chars[n%chars.length]).join('');document.getElementById('exhibitor-password').value=password;});</script>
@endpush
