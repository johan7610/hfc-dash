@extends('layouts.corex-app')

@section('corex-content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-5"
     x-data="companyDocumentEditor({
        initialContent: @js($document->content ?? ''),
     })">

    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <a href="{{ route('admin.company-documents.index') }}" class="inline-flex items-center gap-1 text-xs no-underline" style="color: rgba(255,255,255,0.7);">
            ← Back to Company Documents
        </a>
        <h1 class="text-xl font-bold text-white leading-tight mt-1">{{ $document->title }}</h1>
        <p class="text-sm text-white/60">
            {{ $document->typeLabel() }} · {{ $document->content_format }} format
            @if($document->is_published)
                · <span style="color:#10b981;">Published</span>
                — <a href="{{ $document->publicUrl() }}" target="_blank" rel="noopener" style="color:#10b981; text-decoration:underline;">View public page</a>
            @else
                · <span style="color:#f59e0b;">Draft (not yet published)</span>
            @endif
        </p>
    </div>

    @if(session('success'))
    <div class="rounded-md px-4 py-3 text-sm font-medium" style="background: color-mix(in srgb, var(--ds-green) 10%, transparent); border:1px solid color-mix(in srgb, var(--ds-green) 30%, transparent); color: var(--text-primary);">
        {{ session('success') }}
    </div>
    @endif

    <form method="POST" action="{{ route('admin.company-documents.update', $document->id) }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-md p-4" style="background:var(--surface); border:1px solid var(--border);">
            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Title</label>
            <input type="text" name="title" value="{{ old('title', $document->title) }}" maxlength="200" required
                   class="w-full px-3 py-2 text-sm rounded"
                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {{-- Editor --}}
            <div class="rounded-md p-4" style="background:var(--surface); border:1px solid var(--border);">
                <div class="flex items-center justify-between mb-2">
                    <label class="text-xs font-semibold" style="color:var(--text-secondary);">Content (Markdown)</label>
                    <span class="text-xs" style="color:var(--text-muted);" x-text="contentLength + ' chars'"></span>
                </div>
                <textarea name="content" x-model="content" rows="22"
                          class="w-full px-3 py-2 text-sm rounded font-mono"
                          style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary); resize: vertical;"
                          placeholder="# Privacy Policy&#10;&#10;Effective date: ...&#10;&#10;## 1. Introduction&#10;&#10;..."></textarea>
                <p class="text-[10px] mt-2" style="color:var(--text-muted);">
                    Markdown supported: headings (#, ##), lists (-, 1.), bold (**text**), italic (*text*), links ([text](url)).
                </p>
            </div>

            {{-- Live preview --}}
            <div class="rounded-md p-4" style="background:var(--surface); border:1px solid var(--border); overflow-y:auto; max-height:600px;">
                <div class="text-xs font-semibold mb-3" style="color:var(--text-secondary);">Preview</div>
                <div class="prose prose-sm max-w-none" style="color: var(--text-primary);" x-html="previewHtml"></div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="corex-btn-outline text-sm">Save draft</button>
            <button type="submit" name="publish" value="1" class="corex-btn-primary text-sm">Save &amp; publish</button>
            <a href="{{ route('admin.company-documents.index') }}" class="text-xs" style="color:var(--text-muted);">Cancel</a>
        </div>
    </form>

</div>

<script>
function companyDocumentEditor(init) {
    return {
        content: init.initialContent || '',
        get contentLength() { return this.content.length; },
        get previewHtml() {
            // Client-side preview only — server re-renders via Str::markdown on save.
            // Lightweight transform good enough for live editing.
            let h = this.escapeHtml(this.content);
            // Headings
            h = h.replace(/^### (.*)$/gm, '<h3>$1</h3>');
            h = h.replace(/^## (.*)$/gm, '<h2>$1</h2>');
            h = h.replace(/^# (.*)$/gm, '<h1>$1</h1>');
            // Bold + italic
            h = h.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            h = h.replace(/\*(.+?)\*/g, '<em>$1</em>');
            // Links
            h = h.replace(/\[(.+?)\]\((.+?)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
            // Paragraphs
            h = h.split(/\n\s*\n/).map(p => p.trim() ? (p.startsWith('<h') ? p : '<p>' + p.replace(/\n/g, '<br>') + '</p>') : '').join('\n');
            return h;
        },
        escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        },
    };
}
</script>
@endsection
