<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Photo Capture — Visitor Registration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page live-face-page">
    <section class="hero">
        <div class="hero-content">
            <a class="face-back" href="{{ route('visitor.upload_document', ['type' => $type]) }}">← Upload document again</a>
            <div class="tagline">Visitor photo</div>
            <h1 class="headline">Take your photo<span class="dot">.</span></h1>
            @include('visitor.partials.selected-registration-day')
            <p class="face-intro">Capture a clear photo for your visitor record, then continue to registration.</p>

            <div class="face-card">
                <div class="face-status"><span id="statusDot"></span><strong id="statusText">Camera not started</strong></div>
                <div class="camera-stage" id="cameraStage">
                    <video id="camera" autoplay muted playsinline></video>
                    <canvas id="captureCanvas" hidden></canvas>
                    <div class="face-guide" aria-hidden="true"></div>
                    <div class="camera-placeholder" id="cameraPlaceholder">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                        <strong>Allow camera access to continue</strong>
                        <small>Your browser will ask for camera permission.</small>
                    </div>
                </div>
                <div class="face-tips"><span>Look at the camera</span><span>Keep the photo clear</span><span>Use even lighting</span></div>
                <p class="face-error" id="faceError" role="alert"></p>
                <button type="button" id="cameraBtn" class="btn btn-secondary btn-large form-width-100">Start camera</button>
                <button type="button" id="captureBtn" class="btn btn-primary btn-large form-width-100" disabled>Capture photo &amp; proceed</button>
                <p class="face-disclaimer">This photo is stored with your visitor record. No biometric analysis is performed.</p>
            </div>
        </div>
        <div class="hero-visual" aria-hidden="true">@include('visitor.partials.checkin-illustration')</div>
    </section>

    <style>
        body.landing-page.live-face-page .face-back { display:inline-flex; margin-bottom:18px; color:#555; font-size:13px; font-weight:600; }
        body.landing-page.live-face-page .face-intro { color:#555; font-size:14px; line-height:1.6; margin:0 0 20px; max-width:520px; }
        body.landing-page.live-face-page .face-card { width:100%; max-width:520px; padding:22px; border:2px solid #e2e8f0; border-radius:14px; background:#fff; box-shadow:0 20px 45px rgba(17,17,17,.08); }
        body.landing-page.live-face-page .face-status { display:flex; align-items:center; gap:8px; margin-bottom:13px; color:#4b5563; font-size:12px; }
        body.landing-page.live-face-page .face-status span { width:9px; height:9px; border-radius:50%; background:#9ca3af; box-shadow:0 0 0 4px rgba(156,163,175,.14); }
        body.landing-page.live-face-page .face-status.is-live span { background:#c8e063; box-shadow:0 0 0 4px rgba(200,224,99,.22); }
        body.landing-page.live-face-page .camera-stage { position:relative; width:100%; aspect-ratio:4/3; overflow:hidden; border-radius:12px; background:#171717; }
        body.landing-page.live-face-page #camera { display:none; width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
        body.landing-page.live-face-page .camera-placeholder { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:24px; color:#fff; text-align:center; }
        body.landing-page.live-face-page .camera-placeholder svg { width:36px; height:36px; fill:none; stroke:#c8e063; stroke-width:1.8; }
        body.landing-page.live-face-page .camera-placeholder small { color:#b8b8b8; font-size:11px; }
        body.landing-page.live-face-page .face-guide { display:none; position:absolute; z-index:2; left:50%; top:50%; width:43%; height:70%; border:2px solid #c8e063; border-radius:48%; transform:translate(-50%,-50%); box-shadow:0 0 0 999px rgba(0,0,0,.18); pointer-events:none; }
        body.landing-page.live-face-page .face-tips { display:flex; justify-content:center; flex-wrap:wrap; gap:7px; margin:12px 0; }
        body.landing-page.live-face-page .face-tips span { padding:5px 8px; border-radius:99px; background:#f2f7dc; color:#53620b; font-size:10px; font-weight:700; }
        body.landing-page.live-face-page .face-error { min-height:18px; margin:0 0 8px; color:#c43d3d; font-size:12px; line-height:1.4; }
        body.landing-page.live-face-page .face-card .btn { margin-top:9px; }
        body.landing-page.live-face-page .face-disclaimer { margin:13px 0 0; color:#777; font-size:10px; line-height:1.45; text-align:center; }
        @media (max-width:700px) { body.landing-page.live-face-page .face-card { padding:16px; } }
    </style>

    <script>
        const video = document.getElementById('camera');
        const canvas = document.getElementById('captureCanvas');
        const cameraBtn = document.getElementById('cameraBtn');
        const captureBtn = document.getElementById('captureBtn');
        const placeholder = document.getElementById('cameraPlaceholder');
        const guide = document.querySelector('.face-guide');
        const status = document.querySelector('.face-status');
        const statusText = document.getElementById('statusText');
        const errorBox = document.getElementById('faceError');
        let stream;

        cameraBtn.addEventListener('click', async () => {
            errorBox.textContent = '';
            try {
                stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user', width:{ideal:1280}, height:{ideal:960}}, audio:false});
                video.srcObject = stream;
                await video.play();
                video.style.display = 'block';
                placeholder.style.display = 'none';
                guide.style.display = 'block';
                status.classList.add('is-live');
                statusText.textContent = 'Live camera ready';
                cameraBtn.style.display = 'none';
                captureBtn.disabled = false;
            } catch (error) {
                errorBox.textContent = 'Camera access was blocked or unavailable. Allow camera permission in the browser and try again.';
            }
        });

        captureBtn.addEventListener('click', () => {
            if (!stream || !video.videoWidth) return;
            captureBtn.disabled = true;
            captureBtn.textContent = 'Saving photo…';
            errorBox.textContent = '';
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.translate(canvas.width, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob(async blob => {
                const form = new FormData();
                form.append('selfie', blob, 'visitor-photo.jpg');
                try {
                    const response = await fetch("{{ route('visitor.capture_photo') }}", {method:'POST', headers:{'X-CSRF-TOKEN':"{{ csrf_token() }}", 'Accept':'application/json'}, body:form});
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) throw new Error(data.error || 'Photo capture failed. Please try again.');
                    statusText.textContent = 'Photo saved';
                    stream.getTracks().forEach(track => track.stop());
                    window.location.href = data.redirect_url;
                } catch (error) {
                    errorBox.textContent = error.message;
                    captureBtn.disabled = false;
                    captureBtn.textContent = 'Capture photo & proceed';
                }
            }, 'image/jpeg', .9);
        });

        window.addEventListener('beforeunload', () => stream?.getTracks().forEach(track => track.stop()));
    </script>
</body>
</html>
