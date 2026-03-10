@extends('layouts.corex')

@section('corex-content')
@php
  $archived = (string)request()->get('archived') === '1';
  $conversation = $conversation ?? ($activeConversation ?? null);
  $conversation_id = $conversation_id ?? ($conversation?->id ?? (int)request()->get('conversation_id'));
@endphp

<style>
  /* ELLIE_CHATGPT_UI_2026 — design-system aligned */
  .ellie-app { height: calc(100vh - 130px); min-height: 400px; display: flex; flex-direction: column; }
  .ellie-top { display:flex; align-items:center; gap:12px; margin-bottom: 16px; flex: 0 0 auto; }
  .ellie-top img { width: 36px; height: 36px; border-radius: 9999px; }
  .ellie-top .ellie-title { font-weight: 800; font-size: 18px; color: var(--text-primary); }
  .ellie-top .ellie-subtitle { font-size: 12px; color: var(--text-muted); }

  .ellie-row { flex: 1 1 auto; min-height: 0; display: grid; grid-template-columns: 320px 1fr; gap: 16px; overflow: hidden; }

  .ellie-pane { background: var(--surface); border: 1px solid var(--border); border-radius: 6px; overflow: hidden;
                display:flex; flex-direction:column; min-height:0; }

  .ellie-pane-h { padding: 12px 16px; border-bottom: 1px solid var(--border);
                  display:flex; align-items:center; justify-content:space-between; gap: 10px; flex: 0 0 auto; background: var(--surface); }
  .ellie-pane-h .title { font-weight: 700; color: var(--text-primary); }
  .ellie-btn { border:0; border-radius: 6px; padding: 7px 12px; cursor:pointer; font-weight: 600; font-size: 13px;
               background: var(--brand-button, #0ea5e9); color:#fff; transition: all 300ms; }
  .ellie-btn:hover { opacity: 0.9; }
  .ellie-btn.secondary { background: var(--surface-2); color: var(--text-primary); }
  .ellie-btn.secondary:hover { opacity: 0.8; }

  .ellie-scroll { flex: 1 1 auto; min-height:0; overflow:auto; background: var(--bg); }

  .ellie-list a { display:block; padding: 12px 16px; border-bottom: 1px solid var(--border); text-decoration:none;
                  color: var(--text-primary); transition: background 300ms; }
  .ellie-list a:hover { background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 8%, transparent); }
  .ellie-list a.active { background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 14%, transparent); }
  .ellie-list .conv-title { font-weight: 600; font-size: 14px; }
  .ellie-list .meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

  .ellie-messages { padding: 20px; display:flex; flex-direction:column; gap: 12px; }
  .bubble { max-width: 72%; padding: 12px 14px; border-radius: 6px; line-height: 1.45; white-space: pre-wrap; word-wrap: break-word; font-size: 14px; }
  .bubble.user { margin-left: auto; background: var(--brand-button, #0ea5e9); color: #fff; }
  .bubble.assistant { margin-right: auto; background: var(--surface); border: 1px solid var(--border); color: var(--text-primary); }
  .bubble .role { font-size: 11px; color: var(--text-muted); margin-bottom: 4px; font-weight: 600; }

  .ellie-compose { flex: 0 0 auto; padding: 14px 16px; border-top: 1px solid var(--border); background: var(--surface); }
  .ellie-form { display:flex; gap: 10px; align-items: flex-end; }
  .ellie-input { flex: 1 1 auto; min-height: 44px; max-height: 140px; resize: vertical;
                 border: 1px solid var(--border) !important; border-radius: 6px !important; padding: 10px 12px;
                 outline: none; font-size: 14px; background: var(--surface-2) !important; color: var(--text-primary) !important;
                 transition: border-color 300ms; }
  .ellie-input:focus { border-color: var(--brand-button, #0ea5e9) !important; box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-button, #0ea5e9) 20%, transparent) !important; }
  .ellie-input::placeholder { color: var(--text-muted) !important; }
  .ellie-send { border:0; border-radius: 6px; padding: 11px 16px; cursor:pointer; font-weight:700; font-size: 14px;
                background: var(--brand-button, #0ea5e9); color:#fff; transition: all 300ms; }
  .ellie-send:hover { opacity: 0.9; }

  .ellie-empty { padding: 20px; color: var(--text-secondary); font-size: 14px; line-height: 1.5; }

  @media (max-width: 980px) {
    .ellie-row { grid-template-columns: 1fr; }
  }
</style>

<div class="ellie-app">

  <div class="ellie-top">
    <img src="/images/ellie-32-circle.png" alt="Ellie">
    <div>
      <div class="ellie-title">Ellie, Your AI Assistant</div>
      <div class="ellie-subtitle">Logged in as {{ auth()->user()->name }}</div>
    </div>
  </div>

  <div class="ellie-row">

    <!-- LEFT: Conversations -->
    <div class="ellie-pane">
      <div class="ellie-pane-h">
        <div class="title">Conversations</div>
        <div style="display:flex; gap:6px; align-items:center;">
          @if(request()->get('archived') == '1')
            <a class="ellie-btn secondary" href="/ellie">Hide Archived</a>
          @else
            <a class="ellie-btn secondary" href="/ellie?archived=1">Show Archived</a>
          @endif
          <a class="ellie-btn" href="/ellie?new=1{{ request()->get('archived') ? '&archived=1' : '' }}">+ New</a>
        </div>
      </div>

      <div class="ellie-scroll ellie-list">
        @forelse($conversations as $c)
          <a href="/ellie?conversation_id={{ $c->id }}{{ request()->get('archived') ? '&archived=1' : '' }}"
             class="{{ (int)$conversation_id === (int)$c->id ? 'active' : '' }}">
            <div class="conv-title">{{ $c->title ?? ('Conversation #'.$c->id) }}</div>
            <div class="meta">{{ $c->updated_at }}</div>
          </a>
        @empty
          <div class="ellie-empty">No conversations yet.</div>
        @endforelse
      </div>
    </div>

    <!-- RIGHT: Chat -->
    <div class="ellie-pane">
      <div class="ellie-pane-h">
        <div class="title">{{ $conversation->title ?? 'New Conversation' }}</div>
        <div style="display:flex; gap:6px; align-items:center;">
            @if(!empty($conversation_id))
              <form method="POST" action="/ellie/rename" style="margin:0; display:flex; gap:6px; align-items:center;">
                @csrf
                <input type="hidden" name="conversation_id" value="{{ $conversation_id }}">
                <input type="hidden" name="return_archived" value="{{ $archived ? '1' : '' }}">
                <input type="text" name="title" value="{{ $conversation->title ?? '' }}" placeholder="Rename…" class="ellie-input" style="min-width:200px; min-height:unset; max-height:unset; resize:none; padding:6px 10px;" />
                <button type="submit" class="ellie-btn secondary">Rename</button>
              </form>

              @if(($conversation->status ?? '') === 'archived')
                <form method="POST" action="/ellie/unarchive" style="margin:0;">
                  @csrf
                  <input type="hidden" name="conversation_id" value="{{ $conversation_id }}">
                  <button type="submit" class="ellie-btn secondary">Unarchive</button>
                </form>
              @else
                <form method="POST" action="/ellie/archive" style="margin:0;">
                  @csrf
                  <input type="hidden" name="conversation_id" value="{{ $conversation_id }}">
                  <input type="hidden" name="return_archived" value="{{ $archived ? '1' : '' }}">
                  <button type="submit" class="ellie-btn secondary">Archive</button>
                </form>
              @endif
            @endif
        </div>
      </div>

      <div id="ellieScroll" class="ellie-scroll">
        <div class="ellie-messages" id="ellieMsgs">
          @forelse($messages as $m)
            @php $isUser = ($m->role ?? '') === 'user'; @endphp
            <div class="bubble {{ $isUser ? 'user' : 'assistant' }}">
              <div class="role">{{ $isUser ? 'You' : 'Ellie' }}</div>
              {{ $m->content }}
            </div>
          @empty
            <div class="ellie-empty">Hi 👋 I'm Ellie. Ask me anything about your performance, targets, listings, or next actions.</div>
          @endforelse
        </div>
      </div>

      <div class="ellie-compose">
        <form method="POST" action="/ellie/send" class="ellie-form" id="ellieSendForm">
          @csrf
          <input type="hidden" name="conversation_id" value="{{ $conversation_id ?? '' }}">
          <textarea name="message" class="ellie-input" placeholder="Message Ellie..." autocomplete="off"></textarea>
          <button class="ellie-send" type="submit">Send</button>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
  // ELLIE_SEND_REDIRECT_2026: send via fetch then redirect back to /ellie so the left list updates immediately
  (function(){
    const form = document.getElementById('ellieSendForm');
    if(!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      const msg = (fd.get('message') || '').toString().trim();
      if(!msg) return;

      try{
        const res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: fd
        });

        const data = await res.json();

        if(data && data.ok){
          const url = new URL('/ellie', window.location.origin);
          url.searchParams.set('conversation_id', data.conversation_id);
          if("{{ $archived ? '1' : '' }}" === "1") url.searchParams.set('archived','1');
          window.location.href = url.toString();
          return;
        }

        window.location.reload();
      }catch(err){
        window.location.reload();
      }
    });
  })();
</script>
@endsection
