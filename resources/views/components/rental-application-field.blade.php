@props(['name', 'label', 'value' => null, 'type' => 'text'])
<div>
    <label class="block text-xs text-slate-500 mb-1">{{ $label }}</label>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
</div>
