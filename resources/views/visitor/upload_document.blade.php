<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Document — Identity Verification</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="landing-page upload-document-page">
    @include('layouts.site-header')

    <section class="hero">
        <div class="hero-content">
            <div style="margin-bottom: 12px;">
                <a href="{{ route('visitor.create') }}" class="btn-back-nav">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                    Back to Privacy Notice
                </a>
            </div>

            <div class="tagline">Step 2 of 2</div>
            <h1 class="headline">Upload your document<span class="dot">.</span></h1>
            @include('visitor.partials.selected-registration-day')
            <p class="description upload-doc-intro">
                Select your document type and upload or capture a clear photo of your identity document.
            </p>

            <div class="verification-consent-card upload-doc-card">
                
                <!-- Document Type Selector — matches form-control-premium theme -->
                <div style="text-align: left; margin-bottom: 20px;">
                    <label class="form-label-premium">Select Document Type</label>
                    
                    <div class="doc-select" id="docTypeDropdown">
                        <button type="button" class="doc-select__trigger" id="docTrigger" aria-haspopup="listbox" aria-controls="docMenu" aria-expanded="false">
                            <span class="doc-select__icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                            </span>
                            <span class="doc-select__label" id="selectedDocLabel">
                                @switch($type)
                                    @case('driving_license') Driving License @break
                                    @case('passport') Passport @break
                                    @default National Identity Card (NIC)
                                @endswitch
                            </span>
                            <span class="doc-select__arrow">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            </span>
                        </button>
                        
                        <ul class="doc-select__menu" id="docMenu" role="listbox" aria-label="Document type">
                            <li class="doc-select__item @if($type === 'nic') doc-select__item--active @endif" data-value="nic" role="option" aria-selected="{{ $type === 'nic' ? 'true' : 'false' }}" tabindex="-1">
                                <span class="doc-select__item-text">National Identity Card (NIC)</span>
                                <span class="doc-select__check">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#688009" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            </li>
                            <li class="doc-select__item @if($type === 'driving_license') doc-select__item--active @endif" data-value="driving_license" role="option" aria-selected="{{ $type === 'driving_license' ? 'true' : 'false' }}" tabindex="-1">
                                <span class="doc-select__item-text">Driving License</span>
                                <span class="doc-select__check">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#688009" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            </li>
                            <li class="doc-select__item @if($type === 'passport') doc-select__item--active @endif" data-value="passport" role="option" aria-selected="{{ $type === 'passport' ? 'true' : 'false' }}" tabindex="-1">
                                <span class="doc-select__item-text">Passport</span>
                                <span class="doc-select__check">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#688009" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                </span>
                            </li>
                        </ul>
                        <input type="hidden" id="docTypeSelect" name="document_type" value="{{ $type }}">
                    </div>
                </div>

                <div class="document-sides @if($type !== 'nic') document-sides--single @endif" style="text-align: left; margin-bottom: 20px;">
                    <div class="document-side">
                        <label class="form-label-premium" id="frontLabel">
                            @if($type === 'passport') Passport identity page
                            @elseif($type === 'driving_license') Driving licence front
                            @else NIC front
                            @endif
                        </label>
                        <div class="doc-dropzone">
                            <input type="file" id="documentFrontImage" accept="image/*" capture="environment" class="doc-dropzone__input">
                            <div id="frontContent" class="doc-dropzone__placeholder">
                                <div class="doc-dropzone__icon-circle">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1 2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                </div>
                                <h3 class="doc-dropzone__title">Capture or browse front</h3>
                                <p class="doc-dropzone__subtitle">JPEG, PNG or WEBP · Max 10MB</p>
                            </div>
                            <div id="frontPreviewContainer" class="doc-dropzone__preview" style="display:none">
                                <img id="frontPreview" src="" alt="Front document preview" class="doc-dropzone__preview-img">
                                <p id="frontFileName" class="doc-dropzone__filename"></p>
                                <span class="doc-dropzone__change-hint">Tap to replace front</span>
                            </div>
                        </div>
                    </div>

                    <div class="document-side @if($type !== 'nic') document-side--hidden @endif" id="backDocumentSide">
                        <label class="form-label-premium">Back of document</label>
                        <div class="doc-dropzone">
                            <input type="file" id="documentBackImage" accept="image/*" capture="environment" class="doc-dropzone__input">
                            <div id="backContent" class="doc-dropzone__placeholder">
                            <div class="doc-dropzone__icon-circle">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                            </div>
                                <h3 class="doc-dropzone__title">Capture or browse back</h3>
                                <p class="doc-dropzone__subtitle">JPEG, PNG or WEBP · Max 10MB</p>
                            </div>
                            <div id="backPreviewContainer" class="doc-dropzone__preview" style="display:none">
                                <img id="backPreview" src="" alt="Back document preview" class="doc-dropzone__preview-img">
                                <p id="backFileName" class="doc-dropzone__filename"></p>
                                <span class="doc-dropzone__change-hint">Tap to replace back</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="button" id="verifyBtn" class="btn btn-primary btn-large form-width-100" style="margin-top: 10px;" disabled>Verify document</button>

            </div>

            <div class="verification-assurance" style="margin-top: 16px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                <span>Encrypted connection · Gemini document extraction</span>
            </div>
        </div>

        <!-- Animated SVG graphic on the right — same partial as homepage -->
        <div class="hero-visual" aria-hidden="true">
            <img src="{{ asset('img/hero.png') }}" alt="" class="hero-image">
        </div>
    </section>

    <style>
        /* ─── Document Type Dropdown ─── */
        body.landing-page.upload-document-page .upload-doc-card {
            overflow: visible;
            width: 100%;
            max-width: 600px;
            padding: 24px;
        }

        body.landing-page.upload-document-page .upload-doc-intro {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.55;
        }

        body.landing-page.upload-document-page .document-sides {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        body.landing-page.upload-document-page .document-sides--single {
            grid-template-columns: minmax(0, 1fr);
        }

        body.landing-page.upload-document-page .document-side--hidden { display: none; }

        body.landing-page.upload-document-page .doc-select {
            position: relative;
            width: 100%;
        }

        body.landing-page.upload-document-page .doc-select__trigger {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 48px;
            padding: 11px 14px;
            background: #fff;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            color: #111;
            cursor: pointer;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            outline: none;
            -webkit-appearance: none;
        }

        body.landing-page.upload-document-page .doc-select__trigger:hover {
            border-color: #b9ce5a;
            background: #fcfef6;
        }

        body.landing-page.upload-document-page .doc-select__trigger:focus-visible,
        body.landing-page.upload-document-page .doc-select.open .doc-select__trigger {
            border-color: #b6cf50;
            box-shadow: 0 0 0 3px rgba(200, 224, 99, 0.25);
        }

        body.landing-page.upload-document-page .doc-select__icon {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            justify-content: center;
            color: #53620b;
            background: rgba(200, 224, 99, 0.24);
            border-radius: 8px;
        }

        body.landing-page.upload-document-page .doc-select__icon svg { stroke: currentColor; }

        body.landing-page.upload-document-page .doc-select__label {
            flex: 1;
            text-align: left;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        body.landing-page.upload-document-page .doc-select__arrow {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            color: #9ca3af;
            transition: transform 0.2s ease;
        }

        body.landing-page.upload-document-page .doc-select.open .doc-select__arrow {
            transform: rotate(180deg);
        }

        /* Dropdown Menu */
        body.landing-page.upload-document-page .doc-select__menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            margin: 0;
            padding: 7px;
            list-style: none;
            background: #fff;
            border: 1px solid #dce5b5;
            border-radius: 12px;
            box-shadow: 0 18px 40px rgba(17, 17, 17, 0.14), 0 4px 12px rgba(17, 17, 17, 0.06);
            z-index: 200;
            display: none;
            opacity: 0;
            transform: translateY(-6px);
        }

        body.landing-page.upload-document-page .doc-select.open .doc-select__menu {
            display: block;
            animation: docMenuSlide 0.18s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes docMenuSlide {
            to { opacity: 1; transform: translateY(0); }
        }

        /* Menu Items */
        body.landing-page.upload-document-page .doc-select__item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 44px;
            padding: 10px 12px;
            margin: 0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            cursor: pointer;
            outline: none;
            transition: background 0.12s ease, color 0.12s ease, transform 0.12s ease;
        }

        body.landing-page.upload-document-page .doc-select__item:hover,
        body.landing-page.upload-document-page .doc-select__item:focus {
            background: rgba(200, 224, 99, 0.16);
            color: #111;
            transform: translateX(2px);
        }

        body.landing-page.upload-document-page .doc-select__item--active {
            background: #f0f7d4;
            color: #1a1a1a;
            font-weight: 700;
        }

        body.landing-page.upload-document-page .doc-select__item--active:hover {
            background: rgba(200, 224, 99, 0.22);
        }

        body.landing-page.upload-document-page .doc-select__item-text {
            flex: 1;
        }

        body.landing-page.upload-document-page .doc-select__check {
            display: none;
            flex-shrink: 0;
            margin-left: 8px;
        }

        body.landing-page.upload-document-page .doc-select__item--active .doc-select__check {
            display: flex;
            align-items: center;
        }

        /* ─── Drop Zone ─── */
        body.landing-page.upload-document-page .doc-dropzone {
            border: 2px dashed #c8e063;
            border-radius: 12px;
            min-height: 185px;
            padding: 22px 12px;
            text-align: center;
            background: #fafdf2;
            cursor: pointer;
            transition: border-color 0.2s ease, background 0.2s ease;
            position: relative;
        }

        body.landing-page.upload-document-page .doc-dropzone:hover {
            border-color: #b8d053;
            background: #f6fae8;
        }

        body.landing-page.upload-document-page .doc-dropzone__input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        body.landing-page.upload-document-page .doc-dropzone__placeholder {
            pointer-events: none;
        }

        body.landing-page.upload-document-page .doc-dropzone__icon-circle {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            background: #c8e063;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        body.landing-page.upload-document-page .doc-dropzone__title {
            font-size: 15px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
        }

        body.landing-page.upload-document-page .doc-dropzone__subtitle {
            font-size: 12px;
            color: #666;
            margin: 0;
        }

        body.landing-page.upload-document-page .doc-dropzone__preview {
            position: relative;
            z-index: 2;
        }

        body.landing-page.upload-document-page .doc-dropzone__preview-img {
            max-height: 125px;
            max-width: 100%;
            border-radius: 8px;
            border: 1px solid #ddd;
            object-fit: contain;
            background: #fff;
            padding: 4px;
        }

        body.landing-page.upload-document-page .doc-dropzone__filename {
            font-size: 12px;
            font-weight: 600;
            color: #333;
            margin-top: 8px;
        }

        body.landing-page.upload-document-page .doc-dropzone__change-hint {
            font-size: 11px;
            color: #688009;
            font-weight: 600;
            display: block;
            margin-top: 2px;
        }

        @media (max-width: 640px) {
            body.landing-page.upload-document-page .upload-doc-card { padding: 18px 14px; }
            body.landing-page.upload-document-page .document-sides { grid-template-columns: 1fr; gap: 12px; }
            body.landing-page.upload-document-page .doc-dropzone { min-height: 160px; padding: 18px 10px; }
        }
    </style>

    <script>
        const documentFrontImage = document.getElementById('documentFrontImage');
        const documentBackImage = document.getElementById('documentBackImage');
        const hiddenInput = document.getElementById('docTypeSelect');
        const verifyBtn = document.getElementById('verifyBtn');
        const documentSides = document.querySelector('.document-sides');
        const backDocumentSide = document.getElementById('backDocumentSide');
        const frontLabel = document.getElementById('frontLabel');

        // Custom Dropdown Logic
        const dropdown = document.getElementById('docTypeDropdown');
        const triggerBtn = document.getElementById('docTrigger');
        const label = document.getElementById('selectedDocLabel');
        const items = dropdown.querySelectorAll('.doc-select__item');

        function openDropdown(focusItem = false) {
            dropdown.classList.add('open');
            triggerBtn.setAttribute('aria-expanded', 'true');
            if (focusItem) {
                (dropdown.querySelector('.doc-select__item--active') || items[0]).focus();
            }
        }

        function closeDropdown(returnFocus = false) {
            dropdown.classList.remove('open');
            triggerBtn.setAttribute('aria-expanded', 'false');
            if (returnFocus) triggerBtn.focus();
        }

        triggerBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdown.classList.contains('open') ? closeDropdown() : openDropdown();
        });

        items.forEach(item => {
            item.addEventListener('click', function (e) {
                e.stopPropagation();
                items.forEach(i => {
                    i.classList.remove('doc-select__item--active');
                    i.setAttribute('aria-selected', 'false');
                });
                this.classList.add('doc-select__item--active');
                this.setAttribute('aria-selected', 'true');
                label.innerText = this.querySelector('.doc-select__item-text').innerText.trim();
                hiddenInput.value = this.dataset.value;
                updateDocumentSides();
                closeDropdown(true);
            });

            item.addEventListener('keydown', function (e) {
                const currentIndex = Array.from(items).indexOf(this);
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();
                    const direction = e.key === 'ArrowDown' ? 1 : -1;
                    items[(currentIndex + direction + items.length) % items.length].focus();
                } else if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                } else if (e.key === 'Escape' || e.key === 'Tab') {
                    closeDropdown(e.key === 'Escape');
                }
            });
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                closeDropdown();
            }
        });

        // Keyboard navigation
        triggerBtn.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDropdown();
            }
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                e.preventDefault();
                openDropdown(true);
            }
        });

        function updateButtonState() {
            const hasFront = documentFrontImage.files && documentFrontImage.files.length > 0;
            const needsBack = hiddenInput.value === 'nic';
            const hasBack = documentBackImage.files && documentBackImage.files.length > 0;
            verifyBtn.disabled = !hasFront || (needsBack && !hasBack);
        }

        function bindDocumentPreview(input, contentId, previewContainerId, previewId, fileNameId) {
            const content = document.getElementById(contentId);
            const previewContainer = document.getElementById(previewContainerId);
            const preview = document.getElementById(previewId);
            const fileName = document.getElementById(fileNameId);
            input.addEventListener('change', function () {
                if (!this.files || !this.files[0]) {
                    content.style.display = 'block';
                    previewContainer.style.display = 'none';
                    updateButtonState();
                    return;
                }
                const file = this.files[0];
                fileName.innerText = file.name;
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    content.style.display = 'none';
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
                updateButtonState();
            });
        }

        function updateDocumentSides() {
            const isPassport = hiddenInput.value === 'passport';
            const needsBack = hiddenInput.value === 'nic';
            backDocumentSide.classList.toggle('document-side--hidden', !needsBack);
            documentSides.classList.toggle('document-sides--single', !needsBack);
            frontLabel.innerText = isPassport
                ? 'Passport identity page'
                : (hiddenInput.value === 'driving_license' ? 'Driving licence front' : 'NIC front');
            updateButtonState();
        }

        async function prepareDocumentUpload(file) {
            // Camera photos are often 8–20 MB. Gemini receives the image data
            // inline, so a sensibly sized image substantially reduces both the
            // browser upload and the model processing time while retaining
            // enough detail for document text.
            if (!file || !file.type.startsWith('image/')) return file;

            try {
                const image = await new Promise((resolve, reject) => {
                    const objectUrl = URL.createObjectURL(file);
                    const preview = new Image();
                    preview.onload = () => {
                        URL.revokeObjectURL(objectUrl);
                        resolve(preview);
                    };
                    preview.onerror = () => {
                        URL.revokeObjectURL(objectUrl);
                        reject(new Error('Unable to prepare image'));
                    };
                    preview.src = objectUrl;
                });
                const longestSide = Math.max(image.naturalWidth, image.naturalHeight);
                if (longestSide <= 1800 && file.size <= 2 * 1024 * 1024) return file;

                const scale = Math.min(1, 1800 / longestSide);
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
                const context = canvas.getContext('2d');
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.drawImage(image, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.86));
                if (!blob) return file;

                const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                return new File([blob], name, { type: 'image/jpeg', lastModified: file.lastModified });
            } catch (_) {
                // Keep the original image if this browser cannot resize it.
                return file;
            }
        }

        bindDocumentPreview(documentFrontImage, 'frontContent', 'frontPreviewContainer', 'frontPreview', 'frontFileName');
        bindDocumentPreview(documentBackImage, 'backContent', 'backPreviewContainer', 'backPreview', 'backFileName');
        updateDocumentSides();

        // Toast Notification System
        function showToast(message, type = 'info', title = null) {
            let container = document.querySelector('.toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = `toast show toast-${type}`;
            
            let defaultTitle = type === 'success' ? 'Document Verified' : (type === 'error' ? 'Verification Notice' : 'System Notice');
            let toastTitle = title || defaultTitle;

            let iconSvg = '';
            if (type === 'error') {
                iconSvg = `<div class="toast-icon-badge toast-icon-error"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg></div>`;
            } else if (type === 'success') {
                iconSvg = `<div class="toast-icon-badge toast-icon-success"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>`;
            } else {
                iconSvg = `<div class="toast-icon-badge toast-icon-info"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg></div>`;
            }

            toast.innerHTML = `
                ${iconSvg}
                <div class="toast-body-content">
                    <span class="toast-title">${toastTitle}</span>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()" aria-label="Close notice">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-12px) scale(0.96)';
                setTimeout(() => toast.remove(), 320);
            }, 4500);
        }

        verifyBtn.addEventListener('click', async function(e) {
            e.preventDefault();

            const needsBack = hiddenInput.value === 'nic';
            if (!documentFrontImage.files || !documentFrontImage.files[0] || (needsBack && (!documentBackImage.files || !documentBackImage.files[0]))) {
                const missingDocumentMessage = needsBack
                    ? 'Please add both the front and back of the NIC.'
                    : (hiddenInput.value === 'passport' ? 'Please add the passport identity page.' : 'Please add the front of the driving licence.');
                showToast(missingDocumentMessage, 'error');
                return;
            }

            const originalText = this.innerText;
            this.innerText = "Preparing document photos...";
            this.disabled = true;

            try {
                const [frontUpload, backUpload] = await Promise.all([
                    prepareDocumentUpload(documentFrontImage.files[0]),
                    needsBack ? prepareDocumentUpload(documentBackImage.files[0]) : Promise.resolve(null),
                ]);
                this.innerText = "Extracting text from document...";
                const formData = new FormData();
                formData.append('document_type', hiddenInput.value);
                formData.append('document_front_image', frontUpload);
                if (needsBack) formData.append('document_back_image', backUpload);

                const response = await fetch("{{ route('visitor.verify_vision') }}", {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": "{{ csrf_token() }}",
                        "Accept": "application/json"
                    },
                    body: formData
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok || !data.success) {
                    throw new Error(data.error || "Gemini document verification failed.");
                }

                showToast('Document verified successfully! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect_url || "{{ route('visitor.photo_capture') }}";
                }, 800);

            } catch (error) {
                console.error(error);
                this.innerText = originalText;
                this.disabled = false;
                showToast(error.message || 'Error processing document image. Please try again.', 'error');
            }
        });
    </script>
</body>
</html>
