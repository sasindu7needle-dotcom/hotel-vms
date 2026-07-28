<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gate {{ $gate }} {{ strtoupper($direction) }} Scanner — Traction Guest</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --lime: #c7e45c;
            --ink: #111412;
            --muted: #687382;
            --line: #dce3e8;
            --paper: #f5f7f4;
            --danger: #b42331;
        }
        * { box-sizing: border-box; }
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: var(--paper);
            color: var(--ink);
            font-family: Inter, system-ui, -apple-system, sans-serif;
            -webkit-text-size-adjust: 100%;
            overflow: hidden;
        }
        body {
            display: flex;
            flex-direction: column;
        }
        .terminal-shell {
            display: flex;
            flex-direction: column;
            width: min(1500px, 100%);
            height: 100vh;
            margin: 0 auto;
            padding: 14px 20px;
            box-sizing: border-box;
            overflow: hidden;
        }
        .terminal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px 16px;
            margin-bottom: 10px;
            flex: 0 0 auto;
        }
        .terminal-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .08em;
        }
        .terminal-brand i {
            width: 26px;
            height: 26px;
            background: linear-gradient(135deg, var(--lime) 0 72%, #ec6767 72%);
            border-radius: 7px;
            flex-shrink: 0;
        }
        .terminal-heading {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .terminal-heading h1 {
            margin: 0;
            font-size: clamp(17px, 3.5vw, 22px);
            font-weight: 800;
        }
        .terminal-mode {
            padding: 6px 13px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            white-space: nowrap;
        }
        .terminal-mode.in { color: #344500; background: #eaf5be; }
        .terminal-mode.out { color: #7c252d; background: #ffe0e2; }

        .terminal-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
            padding: 10px 14px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(24,33,46,.04);
            flex: 0 0 auto;
        }
        .camera-status {
            display: flex;
            align-items: center;
            gap: 9px;
            color: #52620c;
            font-size: 12px;
            font-weight: 750;
        }
        .camera-status i {
            width: 9px;
            height: 9px;
            background: #b8d34c;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(184,211,76,.2);
            flex-shrink: 0;
        }

        .terminal-workspace {
            flex: 1 1 auto;
            min-height: 0;
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.72fr);
            gap: 16px;
            align-items: stretch;
            overflow: hidden;
        }
        .camera-card, .result-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(24,33,46,.06);
            height: 100%;
            min-height: 0;
            overflow: hidden;
        }
        .camera-card {
            display: flex;
            flex-direction: column;
            padding: 8px;
        }
        .camera-frame {
            position: relative;
            flex: 1;
            width: 100%;
            height: 100%;
            min-height: 0;
            overflow: hidden;
            background: #000;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #gate-reader {
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
        }
        #gate-reader video {
            width: 100% !important;
            height: 100% !important;
            max-height: 100% !important;
            object-fit: cover !important;
            border-radius: 12px;
        }
        #gate-reader canvas {
            display: none !important;
        }
        #gate-reader img {
            max-width: 100% !important;
        }

        .result-card {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 24px 20px;
            text-align: center;
            transition: background-color .2s ease, border-color .2s ease;
        }
        .result-card.idle { color: #7b8794; }
        .result-card.success { color: #263000; background: #f4f9dc; border-color: #cadd7b; }
        .result-card.error { color: #8f202a; background: #fff0f1; border-color: #efb5ba; }

        .result-photo {
            width: 100px;
            height: 100px;
            margin: 0 auto 16px;
            overflow: hidden;
            border: 4px solid #fff;
            border-radius: 50%;
            box-shadow: 0 8px 20px rgba(20,28,38,.12);
        }
        .result-photo img { width: 100%; height: 100%; object-fit: cover; }
        .result-mark {
            display: grid;
            place-items: center;
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: #edf4ca;
            border-radius: 50%;
            font-size: 26px;
            font-weight: 800;
        }
        .result-card.error .result-mark { background: #ffdadd; }
        .result-card h2 { margin: 0 0 8px; font-size: clamp(20px, 3.5vw, 26px); font-weight: 800; line-height: 1.2; }
        .result-card p { margin: 0; font-size: 14px; font-weight: 700; line-height: 1.45; }
        .result-card small { display: block; margin-top: 10px; font-size: 11px; font-weight: 600; opacity: 0.85; }
        .verification-wrap { width: min(100%, 390px); }
        .verification-label {
            display: inline-block;
            margin-bottom: 12px;
            padding: 5px 10px;
            border-radius: 999px;
            background: #edf4ca;
            color: #455600;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .09em;
        }
        .registration-card {
            overflow: hidden;
            border: 1px solid #d8dfc6;
            border-radius: 15px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(20,28,38,.09);
            text-align: left;
        }
        .registration-card-header {
            padding: 12px 16px;
            background: #171a18;
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .1em;
        }
        .registration-identity {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border-bottom: 1px solid #edf0e8;
        }
        .registration-photo {
            display: grid;
            width: 82px;
            height: 92px;
            flex: 0 0 82px;
            overflow: hidden;
            place-items: center;
            border-radius: 10px;
            background: #eef2e9;
            color: #7b876f;
            font-size: 30px;
            font-weight: 800;
        }
        .registration-photo img { width: 100%; height: 100%; object-fit: cover; }
        .registration-name h2 { margin-bottom: 7px; font-size: 20px; }
        .registration-name span {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            background: #eff5d7;
            color: #435300;
            font-size: 10px;
            font-weight: 800;
        }
        .registration-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 11px 16px;
            padding: 14px 16px;
        }
        .registration-details div { min-width: 0; }
        .registration-details dt {
            margin-bottom: 3px;
            color: #87919d;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .registration-details dd {
            margin: 0;
            overflow: hidden;
            color: #202821;
            font-size: 11px;
            font-weight: 700;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .verification-prompt { margin: 13px 0 12px !important; color: #344054; font-size: 12px !important; }
        .decision-buttons { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .decision-buttons button {
            min-height: 46px;
            border: 0;
            border-radius: 10px;
            font: 800 12px Inter, sans-serif;
            cursor: pointer;
        }
        .decision-buttons button:disabled { cursor: wait; opacity: .55; }
        .reject-button { background: #fee4e6; color: #9d2631; }
        .accept-button { background: var(--lime); color: #273400; }

        @media (max-width: 900px) {
            html, body {
                height: auto;
                overflow: auto;
            }
            .terminal-shell {
                height: auto;
                max-height: none;
                overflow: visible;
            }
            .terminal-workspace {
                grid-template-columns: 1fr;
                gap: 14px;
                overflow: visible;
            }
            .camera-card {
                padding: 8px;
            }
            .camera-frame {
                height: 340px;
                min-height: 280px;
                max-height: 50vh;
                aspect-ratio: 4 / 3;
            }
            .result-card {
                min-height: 200px;
                padding: 20px;
            }
        }

        @media (max-width: 560px) {
            .terminal-shell {
                padding: 8px;
            }
            .terminal-header {
                gap: 8px;
                margin-bottom: 10px;
            }
            .terminal-brand span {
                font-size: 11px;
            }
            .terminal-heading h1 {
                font-size: 16px;
            }
            .terminal-mode {
                padding: 4px 9px;
                font-size: 9px;
            }
            .terminal-controls {
                padding: 8px 12px;
                margin-bottom: 10px;
            }
            .camera-frame {
                height: 280px;
                min-height: 240px;
                max-height: 42vh;
                aspect-ratio: 1 / 1;
            }
            .result-card {
                padding: 16px 14px;
                min-height: 170px;
            }
            .result-mark {
                width: 50px;
                height: 50px;
                font-size: 20px;
                margin-bottom: 10px;
            }
            .result-photo {
                width: 80px;
                height: 80px;
                margin-bottom: 10px;
            }
            .registration-identity { padding: 12px; }
            .registration-photo { width: 65px; height: 74px; flex-basis: 65px; }
            .registration-details { padding: 12px; }
        }
    </style>
</head>
<body>
<main class="terminal-shell">
    <header class="terminal-header">
        <div class="terminal-brand"><i></i><span>TRACTION GUEST</span></div>
        <div class="terminal-heading">
            <h1>Gate {{ $gate }}</h1>
            <span class="terminal-mode {{ $direction }}">{{ strtoupper($direction) }} TERMINAL</span>
        </div>
    </header>

    <section class="terminal-controls">
        <div class="camera-status"><i></i><span id="cameraStatus">Starting camera…</span></div>
    </section>

    <section class="terminal-workspace">
        <div class="camera-card"><div class="camera-frame"><div id="gate-reader"></div></div></div>
        <article id="scanResult" class="result-card idle" aria-live="assertive">
            <div><div class="result-mark">⌁</div><h2>Ready to scan {{ strtoupper($direction) }}</h2><p>Present a visitor QR code to the camera.</p><small>This terminal records check-{{ $direction }} movements only.</small></div>
        </article>
    </section>
</main>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
    const resultCard = document.getElementById('scanResult');
    const cameraStatus = document.getElementById('cameraStatus');
    let requestPending = false;
    let lastValue = '';
    let lastScannedAt = 0;
    let clearTimer;
    let pendingQr = null;
    let awaitingDecision = false;

    const resetResult = () => {
        resultCard.className = 'result-card idle';
        resultCard.innerHTML = '<div><div class="result-mark">⌁</div><h2>Ready to scan {{ strtoupper($direction) }}</h2><p>Present a visitor QR code to the camera.</p><small>This terminal records check-{{ $direction }} movements only.</small></div>';
    };
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    const showVerification = data => {
        clearTimeout(clearTimer);
        awaitingDecision = true;
        resultCard.className = 'result-card';
        const visitor = data.visitor || {};
        const name = escapeHtml(visitor.name || 'Unnamed visitor');
        const photo = visitor.photo_url
            ? `<img src="${escapeHtml(visitor.photo_url)}" alt="Registration photo of ${name}">`
            : `<span>${escapeHtml((visitor.name || '?').trim().charAt(0).toUpperCase())}</span>`;

        resultCard.innerHTML = `
            <div class="verification-wrap">
                <span class="verification-label">REGISTRATION FOUND</span>
                <section class="registration-card" aria-label="Visitor registration card">
                    <div class="registration-card-header">VISITOR REGISTRATION CARD</div>
                    <div class="registration-identity">
                        <div class="registration-photo">${photo}</div>
                        <div class="registration-name">
                            <h2>${name}</h2>
                            <span>${escapeHtml(visitor.category)}</span>
                        </div>
                    </div>
                    <dl class="registration-details">
                        <div><dt>Document</dt><dd>${escapeHtml(visitor.document_type)}</dd></div>
                        <div><dt>ID number</dt><dd>${escapeHtml(visitor.document_number)}</dd></div>
                        <div><dt>Company / Occupation</dt><dd>${escapeHtml(visitor.company)}</dd></div>
                        <div><dt>Visitor reference</dt><dd title="${escapeHtml(visitor.reference)}">${escapeHtml(visitor.reference)}</dd></div>
                    </dl>
                </section>
                <p class="verification-prompt">Does this registration photo and information match the person at the gate?</p>
                <div class="decision-buttons">
                    <button id="rejectVisitor" class="reject-button" type="button">Reject</button>
                    <button id="acceptVisitor" class="accept-button" type="button">Accept &amp; check {{ strtoupper($direction) }}</button>
                </div>
            </div>`;

        document.getElementById('rejectVisitor').addEventListener('click', rejectVisitor);
        document.getElementById('acceptVisitor').addEventListener('click', acceptVisitor);
    };
    const showResult = (data, successful) => {
        clearTimeout(clearTimer);
        awaitingDecision = false;
        pendingQr = null;
        resultCard.className = `result-card ${successful ? 'success' : 'error'}`;
        const photo = successful && data.visitor && data.visitor.photo_url
            ? `<div class="result-photo"><img src="${escapeHtml(data.visitor.photo_url)}" alt=""></div>`
            : `<div class="result-mark">${successful ? '✓' : '!'}</div>`;
        const name = successful && data.visitor ? escapeHtml(data.visitor.name) : 'Scan rejected';
        const msg = data && data.message ? escapeHtml(data.message) : '';
        resultCard.innerHTML = `<div>${photo}<h2>${name}</h2><p>${msg}</p><small>${successful ? 'Check-{{ strtoupper($direction) }} recorded successfully' : 'No gate movement was recorded'}</small></div>`;
        clearTimer = setTimeout(resetResult, 3500);
    };
    const onScan = async decodedText => {
        const now = Date.now();
        if (requestPending || awaitingDecision || (decodedText === lastValue && now - lastScannedAt < 10000)) return;
        requestPending = true;
        lastValue = decodedText;
        lastScannedAt = now;
        try {
            const response = await fetch(@json(route('gate.scan', ['direction' => $direction])), {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({qr_value:decodedText,gate:@json($gate),direction:@json($direction),action:'preview'})
            });
            const data = await response.json();
            if (response.ok && data.ok && data.requires_confirmation) {
                pendingQr = decodedText;
                showVerification(data);
            } else {
                showResult(data, false);
            }
        } catch (_) {
            showResult({message:'The scanner could not reach the server. Check the connection and try again.'}, false);
        } finally {
            requestPending = false;
        }
    };
    const acceptVisitor = async () => {
        if (requestPending || !pendingQr) return;
        requestPending = true;
        document.querySelectorAll('.decision-buttons button').forEach(button => button.disabled = true);
        try {
            const response = await fetch(@json(route('gate.scan', ['direction' => $direction])), {
                method:'POST',
                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
                body:JSON.stringify({qr_value:pendingQr,gate:@json($gate),direction:@json($direction),action:'accept'})
            });
            const data = await response.json();
            showResult(data, response.ok && data.ok);
        } catch (_) {
            showResult({message:'The decision could not reach the server. No gate movement was recorded.'}, false);
        } finally {
            requestPending = false;
        }
    };
    const rejectVisitor = () => {
        if (requestPending) return;
        awaitingDecision = false;
        pendingQr = null;
        lastValue = '';
        showResult({message:'Rejected by guard. No gate movement was recorded.'}, false);
    };

    if (typeof Html5Qrcode === 'undefined') {
        cameraStatus.textContent = 'Scanner library unavailable';
        showResult({message:'The QR scanner could not load. Check the terminal internet connection and refresh.'}, false);
    } else {
        const scanner = new Html5Qrcode('gate-reader');
        const qrboxCalc = (w, h) => {
            const minEdge = Math.min(w, h);
            const size = Math.floor(minEdge * 0.65);
            const clamped = Math.max(160, Math.min(280, size));
            return { width: clamped, height: clamped };
        };
        scanner.start({facingMode:'environment'}, {fps:10, qrbox: qrboxCalc}, onScan)
            .then(() => cameraStatus.textContent = 'Camera active — Gate {{ $gate }} {{ strtoupper($direction) }}')
            .catch(() => {
                cameraStatus.textContent = 'Camera unavailable';
                showResult({message:'Allow camera access in this browser, then refresh the page.'}, false);
            });
    }
</script>
</body>
</html>
