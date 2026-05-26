{{--
    docuperfect.shared._document-body — single visual contract for the
    rendered document body, consumed by all three surfaces:

      • wizard_preview   — Step 4 right pane (XHR injects HTML into Alpine)
      • wizard_fill      — Step 5 right pane (same Alpine surface)
      • recipient_signing — /sign/{token} body (server-rendered → Alpine)

    Parameters:
      $viewerContext     string — one of: 'wizard_preview' | 'wizard_fill'
                                  | 'recipient_signing'. Drives the outer
                                  container class so context-specific CSS
                                  (e.g. "your block" highlight on signing)
                                  scopes correctly.
      $body              string — server-rendered HTML to wrap. Use this
                                  when the body content is computed
                                  server-side and embedded directly.
      $alpineXHtml       string — Alpine x-html expression (e.g.
                                  'webTemplateHtml' or 'previewHtml').
                                  Use this when the body content lives
                                  in Alpine state and is injected at
                                  runtime. Mutually exclusive with $body.
      $alpineRef         optional string — sets x-ref on the inner div
                                  so other Alpine handlers can locate it.
      $currentRecipient  optional SignatureRequest — only meaningful for
                                  'recipient_signing'. When set, the
                                  partial post-processes $body to stamp
                                  `data-your-block="true"` on the
                                  instance matching the recipient.

    Why one partial + CSS + renderer markup, not per-view styling: agencies
    design templates once; the system enforces the recipient-block visual
    contract uniformly. Step 4 / Step 5 / signing view MUST render
    identically — divergence is a regression.
--}}
@php
    $viewerContext = $viewerContext ?? 'wizard_preview';
    $contextClass = match ($viewerContext) {
        'recipient_signing' => 'recipient-signing-context',
        'wizard_fill'       => 'wizard-fill-context',
        default             => 'wizard-preview-context',
    };

    // For the recipient-signing context with a server-rendered body,
    // stamp the current recipient's instance with `data-your-block="true"`
    // so the CSS highlight rule matches it. Cheap regex/string pass —
    // the renderer guarantees the attribute spelling.
    $renderedBody = (string) ($body ?? '');
    if (
        $viewerContext === 'recipient_signing'
        && isset($currentRecipient)
        && $currentRecipient !== null
        && $renderedBody !== ''
    ) {
        $identity = $currentRecipient->role_identity ?? null;
        if (!empty($identity)) {
            $needle = 'data-recipient-instance="' . $identity . '"';
            $renderedBody = str_replace(
                $needle,
                $needle . ' data-your-block="true"',
                $renderedBody,
            );
        }
    }
@endphp

{{-- Load the shared visual contract on every consumer of this partial.
     Idempotent — multiple includes only ever fetch the URL once. --}}
<link rel="stylesheet" href="{{ asset('css/docuperfect-recipient-blocks.css') }}">

<div class="docuperfect-document-body {{ $contextClass }}"
     data-viewer-context="{{ $viewerContext }}">
    @if (!empty($alpineXHtml ?? null))
        {{-- Body lives in Alpine state — render an x-html host so Alpine
             swaps the inner HTML at runtime. The post-processing for
             `data-your-block` doesn't apply on this path; the renderer
             stamps the attribute server-side before the HTML lands in
             Alpine state. --}}
        <div
            @if (!empty($alpineRef ?? null)) x-ref="{{ $alpineRef }}" @endif
            x-html="{{ $alpineXHtml }}"
            data-recipient-block-host="1"></div>
    @else
        {!! $renderedBody !!}
    @endif
</div>
