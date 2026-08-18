<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Registration &mdash; Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page visitor-registration-page manual-registration-flow">
    @include('layouts.site-header')
<main class="registration-shell">
    <div class="registration-background" aria-hidden="true"><div class="registration-background-glow"></div><div class="registration-background-art"><img src="{{ asset('img/hero.png') }}" alt="" class="hero-image"></div><span class="registration-accent registration-accent-lime"></span><span class="registration-accent registration-accent-coral"></span></div>
    <section class="registration-card" aria-labelledby="manual-registration-title">
        <div class="registration-heading">
            <span class="tagline no-margin">{{ $exhibitorProfile ? 'EXHIBITOR MEMBERS' : 'FRONT DESK' }}</span>
            <h1 id="manual-registration-title" class="headline">{{ $exhibitorProfile ? 'Add exhibitor member' : 'Manual Registration' }}<span class="dot">.</span></h1>
            <p>{{ $exhibitorProfile ? 'Register a member for '.$exhibitorProfile->company_name.'. Their card will be ready to print once registration is complete.' : 'Register a walk-in visitor and securely capture their identity document and face photo.' }}</p>
        </div>
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
                </div>
            </fieldset>

            <fieldset class="manual-flow-stage"><legend><span>2</span> Visit &amp; identity <small>Select the identity type, upload it, then verify the extracted number</small></legend>
                <div class="registration-grid">
                    @if($exhibitorProfile)
                        <div class="form-group"><label class="form-label-premium">Visitor Category</label><input class="form-control-premium" value="Exhibitor" readonly></div>
                        <div class="form-group"><label class="form-label-premium">Entrance Fee (LKR)</label><input id="manual-fee" class="form-control-premium" type="number" name="entrance_fee" min="0" step="0.01" value="0.00" readonly required></div>
                    @else
                        <div class="form-group"><label class="form-label-premium">Visitor Category</label><select class="form-control-premium" name="category_id" id="manual-category"><option value="">Manual registration</option>@foreach($categories as $category)<option value="{{ $category->id }}" data-fee="{{ $category->entrance_fee }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
                        <div class="form-group"><label class="form-label-premium">Entrance Fee (LKR)</label><input id="manual-fee" class="form-control-premium" type="number" name="entrance_fee" min="0" step="0.01" value="{{ old('entrance_fee', '0.00') }}" required></div>
                    @endif
                    <div class="form-group"><label class="form-label-premium">Identity Type</label><select class="form-control-premium" id="manual-document-type" name="document_type" required><option value="nic">National Identity Card (NIC)</option><option value="driving_license">Driving Licence</option><option value="passport">Passport</option></select></div>
                    <div class="form-group"><label class="form-label-premium">Identity Number</label><input id="manual-document-number" class="form-control-premium" readonly aria-describedby="manual-identity-help" placeholder="Verify the uploaded document to extract this number"><small id="manual-identity-help" class="field-microcopy">Extracted from the document and cannot be edited.</small></div>
                </div>
                <input type="hidden" name="identity_verification_id" id="manual-identity-verification-id">
                @error('identity')<span class="form-error-msg">{{ $message }}</span>@enderror
                <div class="manual-flow-upload-grid" style="margin-top:18px">
                    <label class="manual-flow-upload"><input id="manual-document-front" type="file" name="document_front" accept="image/jpeg,image/png,image/webp" required><strong id="manual-front-label">Identity document &mdash; front</strong><small>Choose photo</small></label>
                    <label class="manual-flow-upload" id="manual-back-field"><input id="manual-document-back" type="file" name="document_back" accept="image/jpeg,image/png,image/webp" required><strong>Identity document &mdash; back</strong><small>Required for NIC</small></label>
                </div>
                <div class="manual-flow-actions" style="margin-top:18px"><span id="manual-identity-status" class="field-microcopy" aria-live="polite">Upload the identity document, then verify it.</span><button id="manual-verify-identity" class="btn btn-primary" type="button" disabled>Verify identity</button></div>
            </fieldset>

            <fieldset class="manual-flow-stage"><legend><span>3</span> Upload face photo <small>Available after identity verification</small></legend>
                <div class="manual-flow-upload-grid"><label class="manual-flow-upload"><input id="manual-face-photo" type="file" name="face_photo" accept="image/jpeg,image/png,image/webp" required disabled><strong>Visitor face photo</strong><small>Verify the identity document first</small></label></div>
                @error('face_photo')<span class="form-error-msg">{{ $message }}</span>@enderror
            </fieldset>
            <div class="manual-flow-actions"><a class="manual-flow-cancel" href="{{ $exhibitorProfile ? route('exhibitor.dashboard', $exhibitorProfile) : url('/') }}">Cancel</a><button id="manual-register-button" class="btn btn-primary btn-large registration-next" type="submit" disabled>Register {{ $exhibitorProfile ? 'member' : 'visitor' }} &rarr;</button></div>
        </form>
    </section>
    <footer class="registration-trust">Identity documents are stored securely and will be available to authorized reception staff only.</footer>
</main>
<script>
const category=document.getElementById('manual-category'),fee=document.getElementById('manual-fee'),type=document.getElementById('manual-document-type'),front=document.getElementById('manual-document-front'),back=document.getElementById('manual-document-back'),backField=document.getElementById('manual-back-field'),face=document.getElementById('manual-face-photo'),verifyButton=document.getElementById('manual-verify-identity'),registerButton=document.getElementById('manual-register-button'),documentNumber=document.getElementById('manual-document-number'),verificationId=document.getElementById('manual-identity-verification-id'),identityStatus=document.getElementById('manual-identity-status'),frontLabel=document.getElementById('manual-front-label');
if(category)category.addEventListener('change',()=>{const option=category.options[category.selectedIndex];if(option.dataset.fee!==undefined)fee.value=Number(option.dataset.fee).toFixed(2)});
function resetIdentity(){documentNumber.value='';verificationId.value='';face.disabled=true;registerButton.disabled=true;verifyButton.disabled=!(front.files[0]&&(!back.required||back.files[0]));identityStatus.textContent='Upload the identity document, then verify it.';face.closest('label').querySelector('small').textContent='Verify the identity document first';}
function syncBack(){const needsBack=type.value==='nic';back.required=needsBack;backField.hidden=!needsBack;frontLabel.textContent=type.value==='passport'?'Passport identity page':type.value==='driving_license'?'Driving licence front':'NIC front';back.value='';resetIdentity();}
type.addEventListener('change',syncBack);
[front,back].forEach(input=>input.addEventListener('change',()=>{if(input.files[0])input.closest('label').querySelector('small').textContent=input.files[0].name;resetIdentity();}));
face.addEventListener('change',()=>{if(face.files[0])face.closest('label').querySelector('small').textContent=face.files[0].name;});
verifyButton.addEventListener('click',async()=>{const needsBack=back.required;if(!front.files[0]||(needsBack&&!back.files[0]))return;verifyButton.disabled=true;verifyButton.textContent='Reading identity…';identityStatus.textContent='Extracting the identity number…';const data=new FormData();data.append('document_type',type.value);data.append('document_front',front.files[0]);if(needsBack)data.append('document_back',back.files[0]);try{const response=await fetch("{{ route('visitor.manual.verify-identity') }}",{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:data});const result=await response.json().catch(()=>({}));if(!response.ok||!result.success)throw new Error(result.error||'Identity verification failed.');documentNumber.value=result.document_number;verificationId.value=result.verification_id;face.disabled=false;registerButton.disabled=false;identityStatus.textContent='Identity verified. Upload the face photo, then register.';face.closest('label').querySelector('small').textContent='Choose photo';}catch(error){identityStatus.textContent=error.message||'Identity verification failed. Please try again.';verifyButton.disabled=false;}finally{verifyButton.textContent='Verify identity';}});
syncBack();
</script>
</body></html>
