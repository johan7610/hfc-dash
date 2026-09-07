{{--
    AT-392, Johan 2026-09-07 — full CRUD for the applicant's own documents:
    view (open what they uploaded), replace (swap for a corrected file),
    remove (archive — Document::delete() is a soft delete, never
    destructive). Shared by show.blade.php and already-submitted.blade.php
    so the two never drift.

    AT-392, Johan 2026-09-07 (2nd pass) — "submitted docs are submitted.
    they can add, but not replace or remove." Evidentiary rule: once the
    agent has received the application, a document they've already seen
    must not be quietly swapped or pulled. Locked at the SERVER
    (RentalApplicationSigningController::assertDocumentsNotLocked) —
    this view-layer hiding is only so the applicant sees why, not the
    enforcement itself; a disabled button with no text is a support call.
--}}
@if($application->documents->isNotEmpty())
<ul class="text-sm text-slate-600 mb-3 space-y-2">
    @foreach($application->documents as $doc)
        <li class="flex items-center justify-between gap-2 border border-slate-100 rounded-lg px-3 py-2">
            <a href="{{ route('rental-applications.public.documents.view', [$application->token, $doc->id]) }}"
               target="_blank" rel="noopener" class="text-slate-700 hover:underline">
                ✓ {{ $doc->original_name }}
            </a>
            @if($application->isSubmitted())
                <span class="text-xs text-slate-400 flex-shrink-0">Submitted — locked</span>
            @else
                <div class="flex items-center gap-2 flex-shrink-0">
                    <form method="POST" action="{{ route('rental-applications.public.documents.replace', [$application->token, $doc->id]) }}"
                          enctype="multipart/form-data">
                        @csrf
                        <label class="text-xs font-medium text-slate-500 hover:text-slate-700 cursor-pointer">
                            Replace
                            <input type="file" name="replacement_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                   class="hidden" onchange="this.form.submit()">
                        </label>
                    </form>
                    <form method="POST" action="{{ route('rental-applications.public.documents.remove', [$application->token, $doc->id]) }}"
                          onsubmit="return confirm('Remove {{ addslashes($doc->original_name) }}?');">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-red-500 hover:text-red-700">Remove</button>
                    </form>
                </div>
            @endif
        </li>
    @endforeach
</ul>
@if($application->isSubmitted())
    <p class="text-xs text-slate-500 mb-3">
        The documents above were submitted with your application and can't be changed. Need to send something else? Add it below — your agent will see it as a new document.
    </p>
@endif
@endif

@error('replacement_file')
    <p class="text-xs text-red-600 mb-2">{{ $message }}</p>
@enderror
