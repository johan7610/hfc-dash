@extends('layouts.nexus')

@section('nexus-content')
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">P24 Suburb Mappings</h1>
        <p class="text-sm text-gray-500 mt-1">Manage Property24 suburb IDs for the presentation search button. Only confirmed IDs will use direct P24 search; unconfirmed suburbs fall back to text-based search.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Add new suburb --}}
    <div class="nexus-panel mb-6">
        <div class="nexus-panel-header">
            <h3 class="nexus-panel-title">Add New Suburb</h3>
        </div>
        <div class="nexus-panel-body">
            <form method="POST" action="{{ route('admin.p24-suburbs.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Suburb Name</label>
                    <input type="text" name="name" required class="border rounded px-3 py-1.5 text-sm w-44" placeholder="e.g. Margate">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">P24 ID</label>
                    <input type="number" name="p24_id" class="border rounded px-3 py-1.5 text-sm w-28" placeholder="e.g. 6348">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Region</label>
                    <input type="text" name="region" value="kzn-south-coast" class="border rounded px-3 py-1.5 text-sm w-36">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Surrounding IDs</label>
                    <input type="text" name="surrounding_ids" class="border rounded px-3 py-1.5 text-sm w-36" placeholder="6357,6358">
                </div>
                <div class="flex items-center gap-2">
                    <input type="hidden" name="confirmed" value="0">
                    <input type="checkbox" name="confirmed" value="1" id="new_confirmed" class="rounded">
                    <label for="new_confirmed" class="text-xs font-semibold text-gray-600">Confirmed</label>
                </div>
                <button type="submit" class="px-4 py-1.5 bg-[#0b2a4a] text-white text-sm font-semibold rounded hover:bg-[#081f36]">Add</button>
            </form>
        </div>
    </div>

    {{-- Existing suburbs table --}}
    <div class="nexus-panel">
        <div class="nexus-panel-header">
            <h3 class="nexus-panel-title">Current Suburbs ({{ $suburbs->count() }})</h3>
        </div>
        <div class="nexus-panel-body p-0">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="text-left px-4 py-2 font-semibold text-gray-600">Name</th>
                        <th class="text-left px-4 py-2 font-semibold text-gray-600">Slug</th>
                        <th class="text-right px-4 py-2 font-semibold text-gray-600">P24 ID</th>
                        <th class="text-left px-4 py-2 font-semibold text-gray-600">Region</th>
                        <th class="text-left px-4 py-2 font-semibold text-gray-600">Surrounding</th>
                        <th class="text-center px-4 py-2 font-semibold text-gray-600">Confirmed</th>
                        <th class="text-right px-4 py-2 font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suburbs as $suburb)
                    <tr class="border-b hover:bg-gray-50" id="row-{{ $suburb->id }}">
                        <form method="POST" action="{{ route('admin.p24-suburbs.update', $suburb) }}">
                            @csrf
                            @method('PUT')
                            <td class="px-4 py-2">
                                <input type="text" name="name" value="{{ $suburb->name }}" class="border rounded px-2 py-1 text-sm w-full">
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $suburb->slug }}</td>
                            <td class="px-4 py-2">
                                <input type="number" name="p24_id" value="{{ $suburb->p24_id }}" class="border rounded px-2 py-1 text-sm w-20 text-right">
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" name="region" value="{{ $suburb->region }}" class="border rounded px-2 py-1 text-sm w-32">
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" name="surrounding_ids" value="{{ is_array($suburb->surrounding_ids) ? implode(',', $suburb->surrounding_ids) : '' }}" class="border rounded px-2 py-1 text-sm w-28" placeholder="6357,6358">
                            </td>
                            <td class="px-4 py-2 text-center">
                                <input type="hidden" name="confirmed" value="0">
                                <input type="checkbox" name="confirmed" value="1" {{ $suburb->confirmed ? 'checked' : '' }} class="rounded">
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="submit" class="px-3 py-1 bg-[#0b2a4a] text-white text-xs font-semibold rounded hover:bg-[#081f36]">Save</button>
                        </form>
                                    <form method="POST" action="{{ route('admin.p24-suburbs.destroy', $suburb) }}" class="inline" onsubmit="return confirm('Delete {{ $suburb->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1 bg-red-50 text-red-600 text-xs font-semibold rounded hover:bg-red-100">Del</button>
                                    </form>
                                </div>
                            </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-gray-400 italic">No suburbs configured. Add one above or run the seeder.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
