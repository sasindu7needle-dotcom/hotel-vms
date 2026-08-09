<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Registration — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page manual-registration-flow">
<main class="registration-shell">
    <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art">@include('visitor.partials.checkin-illustration')</div><span class="registration-accent registration-accent-lime"></span><span class="registration-accent registration-accent-coral"></span></div>
    <section class="registration-card" aria-labelledby="manual-registration-title">
        <div class="registration-heading">
            <span class="tagline no-margin">{{ $exhibitorProfile ? 'EXHIBITOR MEMBERS' : 'FRONT DESK' }}</span>
            <h1 id="manual-registration-title" class="headline">{{ $exhibitorProfile ? 'Add exhibitor member' : 'Manual Registration' }}<span class="dot">.</span></h1>
            <p>{{ $exhibitorProfile ? 'Register a member for '.$exhibitorProfile->company_name.'. Their card will be ready to print once registration is complete.' : 'Register a walk-in visitor and securely capture their identity documents and face photo.' }}</p>
        </div>
        @if(session('status'))
            <div class="manual-flow-success" role="status">
                <span class="manual-flow-success-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"></path></svg></span>
                <div><strong>Visitor registered successfully</strong><p>Identity details and photos are saved. The entrance payment is ready to be confirmed in Receipt Manager.</p></div>
            </div>
        @endif
        @if($errors->any())<div class="manual-flow-errors">Please correct the highlighted fields and try again.</div>@endif
        <form method="POST" action="{{ route('visitor.manual.store') }}" enctype="multipart/form-data" class="registration-form">
            @csrf
            @if($exhibitorProfile)<input type="hidden" name="exhibitor" value="{{ $exhibitorProfile->registration_token }}">@endif
            <fieldset class="manual-flow-stage"><legend><span>1</span> Visitor details <small>Contact and work information</small></legend>
            <div class="registration-grid">
                <div class="form-group form-group-wide"><label class="form-label-premium">Full Name</label><input class="form-control-premium @error('full_name') is-invalid @enderror" name="full_name" value="{{ old('full_name') }}" required placeholder="e.g. Somarathna Mudiyansalage Piyadasa">@error('full_name')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label class="form-label-premium">Mobile Number</label><input class="form-control-premium @error('mobile_number') is-invalid @enderror" name="mobile_number" value="{{ old('mobile_number') }}" required inputmode="tel" placeholder="+94771234567">@error('mobile_number')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label class="form-label-premium">WhatsApp Number</label><input class="form-control-premium @error('whatsapp_number') is-invalid @enderror" name="whatsapp_number" value="{{ old('whatsapp_number') }}" inputmode="tel" placeholder="Same as mobile if blank">@error('whatsapp_number')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
                <div class="form-group form-group-wide"><label class="form-label-premium">Address</label><textarea class="form-control-premium registration-address @error('address') is-invalid @enderror" name="address" required placeholder="Street, city, country">{{ old('address') }}</textarea>@error('address')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label class="form-label-premium">Occupation</label><input class="form-control-premium @error('occupation') is-invalid @enderror" name="occupation" value="{{ old('occupation') }}" required placeholder="e.g. Director">@error('occupation')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
                <div class="form-group"><label class="form-label-premium">Company</label><input class="form-control-premium @error('company') is-invalid @enderror" name="company" value="{{ old('company', $exhibitorProfile?->company_name) }}" required placeholder="e.g. Manil Gems" @if($exhibitorProfile) readonly @endif>@error('company')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
            </div></fieldset>
            <fieldset class="manual-flow-stage"><legend><span>2</span> Visit &amp; identity <small>Category, fee, and document details</small></legend>
            <div class="registration-grid">
                @if($exhibitorProfile)
                    <div class="form-group"><label class="form-label-premium">Visitor Category</label><input class="form-control-premium" value="Exhibitor" readonly></div>
                    <div class="form-group"><label class="form-label-premium">Entrance Fee (LKR)</label><input id="manual-fee" class="form-control-premium" type="number" name="entrance_fee" min="0" step="0.01" value="0.00" readonly required></div>
                @else
                    <div class="form-group"><label class="form-label-premium">Visitor Category</label><select class="form-control-premium" name="category_id" id="manual-category"><option value="">Manual registration</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-fee="{{ $category->entrance_fee }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                    <div class="form-group"><label class="form-label-premium">Entrance Fee (LKR)</label><input id="manual-fee" class="form-control-premium" type="number" name="entrance_fee" min="0" step="0.01" value="{{ old('entrance_fee', '0.00') }}" required></div>
                @endif
                <div class="form-group"><label class="form-label-premium">Identity Type</label><select class="form-control-premium" id="manual-document-type" name="document_type" required><option value="nic">National Identity Card (NIC)</option><option value="driving_license">Driving Licence</option><option value="passport">Passport</option></select></div>
                <div class="form-group"><label class="form-label-premium">Identity Number</label><input class="form-control-premium @error('document_number') is-invalid @enderror" name="document_number" value="{{ old('document_number') }}" required placeholder="NIC, licence, or passport number">@error('document_number')<span class="form-error-msg">{{ $message }}</span>@enderror</div>
            </div></fieldset>
            <fieldset class="manual-flow-stage"><legend><span>3</span> Upload photos <small>Clear images only — JPG, PNG, or WEBP up to 10 MB</small></legend>
            <div class="manual-flow-upload-grid">
                <label class="manual-flow-upload"><input type="file" name="document_front" accept="image/jpeg,image/png,image/webp" required><strong>Identity document — front</strong><small>Choose photo</small></label>
                <label class="manual-flow-upload" id="manual-back-field"><input type="file" name="document_back" accept="image/jpeg,image/png,image/webp" required><strong>Identity document — back</strong><small>Required for NIC</small></label>
                <label class="manual-flow-upload"><input type="file" name="face_photo" accept="image/jpeg,image/png,image/webp" required><strong>Visitor face photo</strong><small>Choose photo</small></label>
            </div>
            @foreach(['document_front', 'document_back', 'face_photo'] as $field)
                @error($field)
                    <span class="form-error-msg">{{ $message }}</span>
                @enderror
            @endforeach
            </fieldset>
            <div class="manual-flow-actions"><a class="manual-flow-cancel" href="{{ $exhibitorProfile ? route('exhibitor.dashboard', $exhibitorProfile) : url('/') }}">Cancel</a><button class="btn btn-primary btn-large registration-next" type="submit">Register {{ $exhibitorProfile ? 'member' : 'visitor' }} <span>→</span></button></div>
        </form>
    </section>
    <footer class="registration-trust">Identity documents are stored securely and will be available to authorized reception staff only.</footer>
</main>
<script>
const category=document.getElementById('manual-category'),fee=document.getElementById('manual-fee'),type=document.getElementById('manual-document-type'),back=document.querySelector('[name="document_back"]'),backField=document.getElementById('manual-back-field');
if(category)category.addEventListener('change',()=>{const option=category.options[category.selectedIndex];if(option.dataset.fee!==undefined)fee.value=Number(option.dataset.fee).toFixed(2)});
function syncBack(){const needed=type.value==='nic';back.required=needed;backField.querySelector('small').textContent=needed?'Required for NIC':'Optional for this document type'}type.addEventListener('change',syncBack);syncBack();
document.querySelectorAll('.manual-flow-upload input').forEach(input=>input.addEventListener('change',()=>{if(input.files[0])input.closest('label').querySelector('small').textContent=input.files[0].name}));
</script>
</body></html>
