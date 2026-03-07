@extends('layouts.corex')

@section('corex-content')
<div style="max-width:900px; margin:0 auto; padding:24px;">
    <h1 style="font-size:1.5rem; font-weight:bold; margin-bottom:8px;">Test Render: {{ $template->name }}</h1>
    <p style="font-size:0.875rem; color:#6b7280; margin-bottom:24px;">{{ $template->page_count }} pages, {{ count($fields) }} fields</p>

    @foreach($pageImages as $pageIndex => $imageUrl)
    <div style="margin-bottom:32px;">
        <div style="font-size:0.75rem; color:#6b7280; margin-bottom:4px;">Page {{ $pageIndex + 1 }}</div>
        {{-- Container: matches editor .dp-page-container exactly --}}
        <div style="position:relative; width:100%; max-width:800px; overflow:visible;">
            {{-- Image: matches editor .dp-page-img exactly --}}
            <img src="{{ $imageUrl }}" alt="Page {{ $pageIndex + 1 }}"
                 style="width:100%; display:block; user-select:none;" draggable="false">
            {{-- Fields: matches editor .dp-field positioning --}}
            @foreach($fields as $field)
                @if(($field['pageIndex'] ?? -1) === $pageIndex)
                <div style="position:absolute; left:{{ $field['position']['x'] ?? 0 }}%; top:{{ $field['position']['y'] ?? 0 }}%; width:{{ $field['size']['width'] ?? 10 }}%; height:{{ $field['size']['height'] ?? 2 }}%; border:1px dashed rgba(59,130,246,0.6); background:rgba(59,130,246,0.15); box-sizing:border-box; display:flex; align-items:center; padding:0 4px; overflow:hidden;"
                     title="{{ $field['named_field_name'] ?? $field['id'] }} ({{ $field['assigneeRole'] ?? 'unknown' }})">
                    <span style="font-size:9px; color:#1e40af; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                        {{ $field['named_field_name'] ?? $field['id'] }}
                    </span>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endforeach
</div>
@endsection
