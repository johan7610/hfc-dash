@if(isset($tvCode) && $tvCode)
    <div class="rounded-2xl border border-slate-700/60 bg-slate-900/40 p-4">
        <div class="font-bold text-slate-200 mb-2">TV Display Code</div>

        <div class="flex gap-3 items-center">
            <div class="font-mono text-3xl font-black tracking-[0.3em] text-white bg-slate-800 px-5 py-2 rounded-lg border border-slate-600 select-all">
                {{ $tvCode->code }}
            </div>

            <div class="text-xs text-slate-400 leading-snug">
                <div>Generated {{ $tvCode->created_at->diffForHumans() }}</div>
                @if($tvCode->creator)
                    <div>by {{ $tvCode->creator->name }}</div>
                @endif
                @if($tvCode->last_used_at)
                    <div class="text-green-400">Last used {{ $tvCode->last_used_at->diffForHumans() }}</div>
                @endif
            </div>
        </div>

        <div class="flex gap-2 mt-3">
            <form method="POST" action="{{ route('bm.tv-code.generate') }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">
                    New Code
                </button>
            </form>

            <form method="POST" action="{{ route('bm.tv-code.revoke') }}">
                @csrf
                <button type="submit" class="px-3 py-1.5 rounded bg-red-700 text-white text-sm font-semibold hover:bg-red-800"
                        onclick="return confirm('Revoke this code? TVs using it will stop working.')">
                    Revoke
                </button>
            </form>
        </div>

        <div class="text-xs text-slate-500 mt-2">
            Go to <span class="font-mono text-slate-300">{{ url('/tv') }}</span> on the TV and enter this code.
        </div>
    </div>
@else
    <div class="rounded-2xl border border-slate-700/60 bg-slate-900/40 p-4">
        <div class="font-bold text-slate-200 mb-2">TV Display Code</div>
        <div class="text-sm text-slate-400 mb-3">No active TV code for this branch.</div>

        <form method="POST" action="{{ route('bm.tv-code.generate') }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700">
                Generate TV Code
            </button>
        </form>

        <div class="text-xs text-slate-500 mt-2">
            Creates a 6-digit code for TV remote input at <span class="font-mono text-slate-300">{{ url('/tv') }}</span>
        </div>
    </div>
@endif
