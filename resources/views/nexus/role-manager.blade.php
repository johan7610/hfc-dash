@extends('layouts.nexus')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6" x-data="{ activeTab: 'permissions' }">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <h2 class="text-xl font-bold text-white leading-tight">Role &amp; Permissions Manager</h2>
        <div class="text-sm text-white/60">Manage role-based permissions for each section of the system.</div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3">{{ session('success') }}</div>
    @endif

    <div class="flex gap-1 bg-slate-100 dark:bg-slate-900 rounded-lg p-1 w-fit">
        <button @click="activeTab = 'permissions'"
                :class="activeTab === 'permissions'
                    ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm'
                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2 rounded-md text-sm font-medium transition-all">
            Permissions Matrix
        </button>
        <button @click="activeTab = 'users'"
                :class="activeTab === 'users'
                    ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 shadow-sm'
                    : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                class="px-4 py-2 rounded-md text-sm font-medium transition-all">
            User Roles
        </button>
    </div>

    {{-- TAB 1: Permissions Matrix --}}
    <div x-show="activeTab === 'permissions'" x-cloak>
        <form method="POST" action="{{ route('nexus.role-manager.save') }}">
            @csrf
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <h3 class="ds-section-header">Section Permissions</h3>
                    <button type="submit" class="nexus-btn-primary text-sm">Save Changes</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm ds-table">
                        <thead>
                            <tr class="border-b text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40">
                                <th class="text-left py-3 px-4 sticky left-0 bg-slate-50 dark:bg-slate-900/40 z-10 min-w-[260px]">
                                    Permission
                                </th>
                                @foreach($roles as $role)
                                    <th class="text-center py-3 px-3 min-w-[120px]">
                                        @php
                                            $roleBadge = match($role) {
                                                'admin' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
                                                'branch_manager' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                                                default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $roleBadge }}">
                                            {{ str_replace('_', ' ', $role) }}
                                        </span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php $lastSection = ''; @endphp
                            @foreach($permissions as $perm)
                                @if($perm->section !== $lastSection)
                                    @php $lastSection = $perm->section; @endphp
                                    <tr class="bg-slate-50/80 dark:bg-slate-900/20">
                                        <td colspan="{{ count($roles) + 1 }}" class="py-2 px-4">
                                            <span class="text-xs font-bold text-[#00b4d8] uppercase tracking-wider">
                                                {{ str_replace('-', ' ', $perm->section) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endif
                                <tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50/80 dark:hover:bg-slate-900/30">
                                    <td class="py-2.5 px-4 font-medium text-slate-900 dark:text-slate-100 sticky left-0 bg-white dark:bg-slate-950 z-10">
                                        {{ $perm->label }}
                                    </td>
                                    @foreach($roles as $role)
                                        <td class="py-2.5 px-3 text-center">
                                            <label class="inline-flex items-center justify-center cursor-pointer">
                                                <input type="hidden"
                                                       name="permissions[{{ $perm->key }}][{{ $role }}]"
                                                       value="0">
                                                <input type="checkbox"
                                                       name="permissions[{{ $perm->key }}][{{ $role }}]"
                                                       value="1"
                                                       {{ isset($granted[$perm->key][$role]) ? 'checked' : '' }}
                                                       class="w-4 h-4 rounded border-slate-300 dark:border-slate-700 text-[#00b4d8] focus:ring-[#00b4d8] cursor-pointer">
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            @if($permissions->isEmpty())
                                <tr>
                                    <td colspan="{{ count($roles) + 1 }}" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                        No permissions defined yet. Run the seeder to populate default permissions.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    {{-- TAB 2: User Roles --}}
    <div x-show="activeTab === 'users'" x-cloak>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="ds-section-header">User Roles</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm ds-table">
                    <thead>
                        <tr class="border-b text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40">
                            <th class="text-left py-3 px-4">Name</th>
                            <th class="text-left py-3 px-4">Email</th>
                            <th class="text-left py-3 px-4">Current Role</th>
                            @if(auth()->user()->isEffectiveAdmin())
                                <th class="text-left py-3 px-4">Change Role</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($users as $u)
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/30">
                                <td class="py-2.5 px-4 font-medium text-slate-900 dark:text-slate-100">{{ $u->name }}</td>
                                <td class="py-2.5 px-4 text-slate-600 dark:text-slate-300">{{ $u->email }}</td>
                                <td class="py-2.5 px-4">
                                    @php
                                        $roleBadge = match($u->role) {
                                            'admin' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
                                            'branch_manager' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                                            default => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $roleBadge }}">{{ str_replace('_', ' ', $u->role) }}</span>
                                </td>
                                @if(auth()->user()->isEffectiveAdmin())
                                    <td class="py-2.5 px-4">
                                        <form method="POST" action="{{ route('nexus.role-manager.user-role') }}" class="flex items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                                            <select name="role" class="text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 py-1.5 px-2" style="max-width:160px">
                                                <option value="agent" {{ $u->role === 'agent' ? 'selected' : '' }}>Agent</option>
                                                <option value="branch_manager" {{ $u->role === 'branch_manager' ? 'selected' : '' }}>Branch Manager</option>
                                                <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                            </select>
                                            <button type="submit" class="nexus-btn-primary text-xs" style="padding:6px 14px;min-height:auto;min-width:auto">Save</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
