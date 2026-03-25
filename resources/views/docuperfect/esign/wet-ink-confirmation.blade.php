<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wet-Ink Signing — {{ $document->name ?? 'Document' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="/css/corex-document.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { box-sizing: border-box; }
        [x-cloak] { display: none !important; }

        body {
            margin: 0;
            padding: 0;
            background: #f1f5f9;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
        }

        /* Top bar */
        .wet-ink-topbar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 40;
        }
        .wet-ink-topbar-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .wet-ink-topbar-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 1px;
        }
        .wet-ink-topbar-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            text-decoration: none;
        }
        .wet-ink-topbar-back:hover { color: #1e293b; }

        /* Two-column layout */
        .wet-ink-layout {
            display: flex;
            gap: 0;
            min-height: calc(100vh - 50px);
        }

        /* Left: document preview */
        .wet-ink-doc-panel {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: #e2e8f0;
        }
        .wet-ink-doc-area {
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            border-radius: 4px;
            overflow: hidden;
        }

        /* Right: action panel */
        .wet-ink-action-panel {
            width: 380px;
            min-width: 380px;
            background: white;
            border-left: 1px solid #e2e8f0;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Instruction card */
        .instruction-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .instruction-card h3 {
            font-size: 13px;
            font-weight: 600;
            color: #1e40af;
            margin: 0 0 8px 0;
        }
        .instruction-card ol {
            margin: 0;
            padding-left: 20px;
            font-size: 13px;
            color: #1d4ed8;
            line-height: 1.7;
        }

        /* Status badges */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        .status-uploaded {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        /* Buttons */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            color: white;
            background: #0d9488;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
        }
        .btn-primary:hover { background: #0f766e; }
        .btn-primary:disabled, .btn-primary.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 11px 20px;
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            background: white;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Upload zone */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 24px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #0d9488;
            background: #f0fdfa;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
        }

        .section-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        /* Responsive: stack on narrow screens */
        @media (max-width: 900px) {
            .wet-ink-layout { flex-direction: column; }
            .wet-ink-action-panel {
                width: 100%;
                min-width: 0;
                border-left: none;
                border-top: 1px solid #e2e8f0;
            }
        }

        /* Print: hide everything except document */
        @media print {
            .wet-ink-topbar, .wet-ink-action-panel { display: none !important; }
            .wet-ink-layout { display: block; }
            .wet-ink-doc-panel { padding: 0; background: white; }
            .wet-ink-doc-area {
                max-width: 100%; margin: 0; box-shadow: none; border-radius: 0;
            }
            .corex-document-wrapper { zoom: 0.82 !important; }
            @page { size: A4; margin: 18mm 20mm; }
        }
    </style>
</head>
<body>

{{-- Top bar --}}
<div class="wet-ink-topbar">
    <a href="{{ route('docuperfect.esign.create') }}" class="wet-ink-topbar-back">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to E-Sign
    </a>
    <div style="text-align:center;">
        <div class="wet-ink-topbar-title">{{ $document->name ?? 'Document' }}</div>
        <div class="wet-ink-topbar-subtitle">Wet-Ink Signing</div>
    </div>
    <div style="width:120px;"></div>
</div>

<div class="wet-ink-layout" x-data="wetInkAgent()">

    {{-- Left: Document preview --}}
    <div class="wet-ink-doc-panel">
        <div class="wet-ink-doc-area">
            @if($mergedHtml)
                {!! $mergedHtml !!}
            @else
                <div style="text-align:center; padding:60px 24px; color:#94a3b8;">
                    <p style="font-size:18px; font-weight:600; margin-bottom:8px;">Document preview not available</p>
                    <p style="font-size:14px;">Use the Download PDF button to get the document.</p>
                </div>
            @endif
        </div>
    </div>

    {{-- Right: Action panel --}}
    <div class="wet-ink-action-panel">

        {{-- Instructions --}}
        <div class="instruction-card">
            <h3>Wet-Ink Signing</h3>
            <ol>
                <li>Download and print the document</li>
                <li>Have all parties sign in ink</li>
                <li>Scan or photograph the signed pages</li>
                <li>Upload the signed copy below</li>
            </ol>
        </div>

        {{-- Step 1: Download --}}
        <div>
            <div class="section-label">Step 1 — Download</div>
            @if($document)
                <a href="{{ route('docuperfect.esign.downloadDocumentPdf', $document->id) }}"
                   class="btn-primary"
                   @click="downloadClicked = true">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a2 2 0 002 2h14a2 2 0 002-2v-3"/></svg>
                    Download PDF
                </a>
                <button type="button"
                        onclick="document.title='{{ addslashes($document->name ?? 'Document') }}'; window.print();"
                        class="btn-secondary" style="margin-top:8px;">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print
                </button>
            @endif
        </div>

        {{-- Step 2: Upload --}}
        <div>
            <div class="section-label">Step 2 — Upload Signed Copy</div>

            @if($agentRequest && $agentRequest->wet_ink_status === \App\Models\Docuperfect\SignatureRequest::WET_INK_UPLOADED_PENDING_REVIEW)
                <div class="status-badge status-uploaded" style="margin-bottom:12px;">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Uploaded — Pending Review
                </div>
            @endif

            @if($agentToken)
            <form action="{{ route('signatures.external.upload', $agentToken) }}"
                  method="POST"
                  enctype="multipart/form-data"
                  @submit="uploading = true">
                @csrf

                <div class="upload-zone"
                     :class="dragover ? 'dragover' : ''"
                     @click="$refs.fileInput.click()"
                     @dragover.prevent="dragover = true"
                     @dragleave.prevent="dragover = false"
                     @drop.prevent="handleDrop($event)">
                    <input type="file" name="files[]" x-ref="fileInput" multiple
                           accept=".pdf,.jpg,.jpeg,.png"
                           @change="handleFiles($event)"
                           class="hidden" style="display:none;">
                    <svg width="32" height="32" style="margin:0 auto 8px;" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <p style="font-size:13px; font-weight:500; color:#64748b;">Drop files here or click to browse</p>
                    <p style="font-size:11px; color:#94a3b8; margin-top:4px;">PDF, JPG, PNG — max 20MB each</p>
                </div>

                {{-- File list --}}
                <template x-if="selectedFiles.length > 0">
                    <div style="margin-top:10px; display:flex; flex-direction:column; gap:6px;">
                        <template x-for="(file, idx) in selectedFiles" :key="idx">
                            <div class="file-item">
                                <div style="display:flex; align-items:center; gap:6px;">
                                    <svg width="14" height="14" fill="none" stroke="#94a3b8" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    <span x-text="file.name" style="color:#334155;"></span>
                                    <span x-text="(file.size/1024/1024).toFixed(1)+' MB'" style="color:#94a3b8; font-size:11px;"></span>
                                </div>
                                <button type="button" @click="removeFile(idx)" style="color:#ef4444; cursor:pointer; background:none; border:none; padding:2px;">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>

                <button type="submit"
                        :disabled="selectedFiles.length === 0 || uploading"
                        class="btn-primary"
                        style="margin-top:12px;">
                    <span x-show="!uploading">Upload Signed Document</span>
                    <span x-show="uploading" x-cloak>Uploading…</span>
                </button>
            </form>
            @else
                <p style="font-size:13px; color:#94a3b8;">Upload will be available once the document is prepared.</p>
            @endif
        </div>

        {{-- Quick links --}}
        <div style="margin-top:auto; padding-top:16px; border-top:1px solid #e2e8f0;">
            @if($document)
                <a href="{{ route('docuperfect.documents.edit', $document->id) }}"
                   style="display:block; font-size:13px; color:#64748b; text-decoration:none; margin-bottom:6px;">
                    View in Documents →
                </a>
            @endif
            <a href="{{ route('docuperfect.esign.create') }}"
               style="display:block; font-size:13px; color:#64748b; text-decoration:none;">
                Create Another Document →
            </a>
        </div>
    </div>
</div>

<script>
function wetInkAgent() {
    return {
        downloadClicked: false,
        dragover: false,
        selectedFiles: [],
        uploading: false,
        handleFiles(event) {
            this.selectedFiles = [...this.selectedFiles, ...Array.from(event.target.files)];
        },
        handleDrop(event) {
            this.dragover = false;
            this.selectedFiles = [...this.selectedFiles, ...Array.from(event.dataTransfer.files)];
        },
        removeFile(index) {
            this.selectedFiles.splice(index, 1);
        },
    };
}
</script>
</body>
</html>
