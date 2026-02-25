@extends('layouts.nexus')

@section('nexus-content')
<style>
#pdf-splitter-root, #pdf-splitter-root * { box-sizing: border-box; }

#pdf-splitter-root {
    --ink:    #0f172a;
    --muted:  #64748b;
    --border: rgba(15,23,42,0.12);
    --card:   #ffffff;
    --navy:   #0b2a45;

    padding: 28px 22px;
    color: var(--ink);
}

#pdf-splitter-root .wrap {
    max-width: 680px;
    margin: 0 auto;
}

#pdf-splitter-root .page-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: var(--navy);
    margin: 0 0 6px;
}

#pdf-splitter-root .page-sub {
    font-size: 0.875rem;
    color: var(--muted);
    margin: 0 0 24px;
}

#pdf-splitter-root .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 28px 32px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.08);
}

#pdf-splitter-root .alert {
    padding: 12px 16px;
    border-radius: 7px;
    font-size: 0.875rem;
    margin-bottom: 20px;
}

#pdf-splitter-root .alert-success {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #166534;
}

#pdf-splitter-root .alert-error {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #991b1b;
}

#pdf-splitter-root .field { margin-bottom: 20px; }

#pdf-splitter-root label {
    display: block;
    font-size: 0.825rem;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

#pdf-splitter-root input[type="text"],
#pdf-splitter-root input[type="file"] {
    width: 100%;
    padding: 10px 13px;
    border: 1px solid var(--border);
    border-radius: 6px;
    font-size: 0.9rem;
    color: var(--ink);
    background: #f8fafc;
    outline: none;
    transition: border-color 0.15s;
}

#pdf-splitter-root input[type="text"]:focus {
    border-color: var(--navy);
    background: #fff;
}

#pdf-splitter-root .field-error {
    font-size: 0.8rem;
    color: #b91c1c;
    margin-top: 4px;
}

#pdf-splitter-root .btn-submit {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 28px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
}

#pdf-splitter-root .btn-submit:hover {
    background: #0a233a;
}
</style>

<div id="pdf-splitter-root">
    <div class="wrap">

        <h1 class="page-title">PDF Pack Splitter</h1>
        <p class="page-sub">Upload a pack PDF and set a base name. OCR runs automatically — you'll review and correct labels before the ZIP is generated.</p>

        {{-- Status message --}}
        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:18px;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form method="POST"
                  action="{{ route('tools.pdf_splitter.run') }}"
                  enctype="multipart/form-data">
                @csrf

                <div class="field">
                    <label for="base_name">Base Name</label>
                    <input type="text"
                           id="base_name"
                           name="base_name"
                           value="{{ old('base_name') }}"
                           maxlength="120"
                           placeholder="e.g. OceanView_Pack">
                    @error('base_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label for="pdf">PDF File <span style="font-weight:400;color:var(--muted)">(max 50 MB)</span></label>
                    <input type="file"
                           id="pdf"
                           name="pdf"
                           accept="application/pdf">
                    @error('pdf')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn-submit">
                    Upload PDF
                </button>
            </form>
        </div>

    </div>
</div>
@endsection


@if (session('splitter_download_url'))
    <iframe src="{{ session('splitter_download_url') }}" style="display:none; width:0; height:0; border:0;"></iframe>
@endif

