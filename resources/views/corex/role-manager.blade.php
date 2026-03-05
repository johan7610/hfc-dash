@extends('layouts.corex')

@section('corex-content')
<div x-data="roleManager()" x-cloak>

    {{-- Page header --}}
    <x-page-header title="Role & Permissions Manager">
        <x-slot:actions>
            <button x-show="activeTab === 'permissions'"
                    @click="saveMatrix()"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-white transition-colors"
                    style="background:#0b2a4a;"
                    onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                Save Changes
            </button>
            <button x-show="activeTab === 'roles'" @click="openAddRole()"
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-white transition-colors"
                    style="background:#0b2a4a;"
                    onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                + Add New Role
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="px-4 lg:px-6 space-y-4 pb-2">

        <p class="text-sm text-slate-500 -mt-2">Manage roles, permissions, and user assignments. The System Owner role always has full access.</p>

        @if(session('success'))
            <div class="rounded-xl border px-4 py-3 text-sm font-medium" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border px-4 py-3 text-sm font-medium" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Tabs --}}
        <div class="flex gap-1 rounded-xl p-1 w-fit" style="background:#f1f5f9;">
            <button type="button" @click="activeTab = 'permissions'"
                    :style="activeTab === 'permissions' ? 'background:#0b2a4a;color:#fff;' : 'background:transparent;color:#64748b;'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all">
                Permissions Matrix
            </button>
            <button type="button" @click="activeTab = 'users'"
                    :style="activeTab === 'users' ? 'background:#0b2a4a;color:#fff;' : 'background:transparent;color:#64748b;'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all">
                User Roles
            </button>
            <button type="button" @click="activeTab = 'roles'"
                    :style="activeTab === 'roles' ? 'background:#0b2a4a;color:#fff;' : 'background:transparent;color:#64748b;'"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-all">
                Roles
            </button>
        </div>

        {{-- ─────────────────────────────────────────────
             TAB 1: Permissions Matrix (Action-Level)
        ───────────────────────────────────────────── --}}
        <div x-show="activeTab === 'permissions'" x-cloak>
            <form id="perm-form" method="POST" action="{{ route('corex.role-manager.save') }}" x-ref="permForm">
                @csrf

                {{-- Role switcher --}}
                <div class="flex items-center gap-3 mb-4 flex-wrap">
                    <label class="text-xs font-semibold text-slate-600">Editing role:</label>
                    <div class="flex gap-1 flex-wrap">
                        @foreach($roles as $role)
                        <button type="button"
                                @click="selectedRole = '{{ $role->name }}'"
                                :style="selectedRole === '{{ $role->name }}'
                                    ? 'background:{{ $role->color }};color:#fff;'
                                    : 'background:#f1f5f9;color:#64748b;'"
                                class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all">
                            {{ $role->label }}
                            @if($role->is_owner)
                                <span class="text-[10px] opacity-75">(all)</span>
                            @endif
                        </button>
                        @endforeach
                    </div>

                    {{-- Copy from another role --}}
                    <div class="flex items-center gap-2 ml-auto">
                        <label class="text-xs text-slate-500">Copy from:</label>
                        <select x-model="copyFromRole"
                                class="text-xs rounded-lg border border-slate-300 bg-white text-slate-800 py-1.5 px-2 focus:outline-none focus:border-[#00b4d8]">
                            <option value="">— select role —</option>
                            @foreach($roles as $role)
                                @if(!$role->is_owner)
                                <option value="{{ $role->name }}"
                                        x-bind:disabled="selectedRole === '{{ $role->name }}'"
                                        x-bind:class="selectedRole === '{{ $role->name }}' ? 'text-slate-300' : ''">
                                    {{ $role->label }}
                                </option>
                                @endif
                            @endforeach
                        </select>
                        <button type="button" @click="copyPermissions()"
                                :disabled="!copyFromRole"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                style="background:#0b2a4a;"
                                onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                            Copy
                        </button>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden flex flex-col">
                    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between flex-shrink-0" style="background:#f8fafc;">
                        <h3 class="font-semibold text-sm" style="color:#0b2a4a;">
                            Permissions for: <span x-text="selectedRoleLabel()" class="text-[#00b4d8]"></span>
                        </h3>
                        <span class="text-xs text-slate-400">{{ $permissions->count() }} permissions</span>
                    </div>

                    {{-- Scrollable matrix --}}
                    <div class="overflow-auto" style="max-height: calc(100vh - 22rem);">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b" style="background:#f8fafc;">
                                    <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20 min-w-[280px]"
                                        style="background:#f8fafc;color:#0b2a4a;">
                                        Module / Permission
                                    </th>
                                    <th class="text-center py-3 px-2 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20"
                                        style="background:#f8fafc;color:#0b2a4a;min-width:200px;">
                                        <span>Data Scope</span>
                                        <div class="text-[10px] font-normal normal-case tracking-normal text-slate-400 mt-0.5">None / Own / Branch / All</div>
                                    </th>
                                    <th class="text-center py-3 px-2 w-16 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20"
                                        style="background:#f8fafc;color:#0b2a4a;">Create</th>
                                    <th class="text-center py-3 px-2 w-16 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20"
                                        style="background:#f8fafc;color:#0b2a4a;">Edit</th>
                                    <th class="text-center py-3 px-2 w-16 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20"
                                        style="background:#f8fafc;color:#0b2a4a;">Archive</th>
                                    <th class="text-center py-3 px-2 w-16 font-semibold text-xs uppercase tracking-wider sticky top-0 z-20"
                                        style="background:#f8fafc;color:#0b2a4a;">Other</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($matrixSections as $sectionLabel => $modules)
                                    {{-- Section header --}}
                                    <tr>
                                        <td colspan="6"
                                            class="py-2 px-4 cursor-pointer select-none"
                                            style="background:#0b2a4a;"
                                            @click="toggleSection('{{ md5($sectionLabel) }}')">
                                            <div class="flex items-center gap-2">
                                                <svg :class="openSections['{{ md5($sectionLabel) }}'] ? 'rotate-90' : ''" class="w-3 h-3 text-white transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
                                                <span class="text-xs font-bold uppercase tracking-wider text-white">
                                                    {{ $sectionLabel }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>

                                    @foreach($modules as $moduleKey => $moduleData)
                                        {{-- Module sub-header --}}
                                        <tr x-show="openSections['{{ md5($sectionLabel) }}']"
                                            class="border-b border-slate-100" style="background:#f1f5f9;">
                                            <td class="py-2 px-4 min-w-[280px]">
                                                <span class="text-xs font-bold uppercase tracking-wider" style="color:#0b2a4a;">
                                                    {{ $moduleData['label'] }}
                                                </span>
                                            </td>
                                            <td class="w-16"></td>
                                            <td class="w-16"></td>
                                            <td class="w-16"></td>
                                            <td class="w-16"></td>
                                            <td class="w-16"></td>
                                        </tr>

                                        {{-- Access permissions (menu visibility) --}}
                                        @foreach($moduleData['access'] as $perm)
                                        <tr x-show="openSections['{{ md5($sectionLabel) }}']"
                                            class="border-b border-slate-50 hover:bg-slate-50 transition-colors">
                                            <td class="py-2 px-4 pl-8 text-slate-600 min-w-[280px]">
                                                <span class="inline-flex items-center gap-1.5">
                                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-slate-300"></span>
                                                    {{ $perm->label }}
                                                </span>
                                            </td>
                                            <td colspan="5" class="py-2 px-2 text-center">
                                                @foreach($roles as $role)
                                                <template x-if="selectedRole === '{{ $role->name }}'">
                                                    <label class="inline-flex items-center justify-center {{ $role->is_owner ? '' : 'cursor-pointer' }}">
                                                        @if($role->is_owner)
                                                            <input type="checkbox" checked disabled
                                                                   class="w-4 h-4 rounded border-slate-300 opacity-50 cursor-not-allowed"
                                                                   style="accent-color:#0b2a4a;">
                                                        @else
                                                            <input type="checkbox"
                                                                   x-model="matrix['{{ $perm->key }}']['{{ $role->name }}']"
                                                                   @change="dirty = true"
                                                                   class="w-4 h-4 rounded border-slate-300 cursor-pointer"
                                                                   style="accent-color:#00b4d8;">
                                                        @endif
                                                    </label>
                                                </template>
                                                @endforeach
                                            </td>
                                        </tr>
                                        @endforeach

                                        {{-- Action permissions (Scope + Create/Edit/Archive grid) --}}
                                        @if(count($moduleData['actions']) > 0)
                                        @php
                                            $actionMap = [];
                                            foreach ($moduleData['actions'] as $ap) {
                                                $parts = explode('.', $ap->key);
                                                $suffix = end($parts);
                                                $actionMap[$suffix] = $ap;
                                            }
                                            $standardActions = ['create','edit','archive'];
                                            $otherActions = array_diff(array_keys($actionMap), array_merge(['view'], $standardActions));
                                            $isShared = in_array($moduleKey, $sharedModules);
                                            $viewKey = $actionMap['view']->key ?? null;
                                        @endphp
                                        <tr x-show="openSections['{{ md5($sectionLabel) }}']"
                                            class="border-b border-slate-100 hover:bg-blue-50/30 transition-colors">
                                            <td class="py-2.5 px-4 pl-8 font-medium text-slate-800 min-w-[280px]">
                                                <span class="inline-flex items-center gap-1.5">
                                                    <svg class="w-3.5 h-3.5 text-[#00b4d8]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    Actions
                                                </span>
                                            </td>
                                            {{-- Scope column (replaces View checkbox) --}}
                                            <td class="py-2.5 px-2 text-center" style="min-width:200px;">
                                                @if($viewKey)
                                                    @foreach($roles as $role)
                                                    <template x-if="selectedRole === '{{ $role->name }}'">
                                                        <div class="inline-flex items-center gap-0.5">
                                                            @if($role->is_owner)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-semibold text-slate-400 bg-slate-100">
                                                                    All (Owner)
                                                                </span>
                                                            @elseif($isShared)
                                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded text-[10px] font-semibold text-emerald-600 bg-emerald-50">
                                                                    Shared — all users
                                                                </span>
                                                            @else
                                                                @foreach(['none','own','branch','all'] as $scopeVal)
                                                                <label class="inline-flex items-center gap-0.5 cursor-pointer px-1.5 py-1 rounded transition-colors text-[11px]"
                                                                       :class="scopeMatrix['{{ $viewKey }}']?.['{{ $role->name }}'] === '{{ $scopeVal }}'
                                                                           ? '{{ $scopeVal === 'none' ? 'bg-slate-200 text-slate-700 font-semibold' : ($scopeVal === 'own' ? 'bg-blue-100 text-blue-700 font-semibold' : ($scopeVal === 'branch' ? 'bg-amber-100 text-amber-700 font-semibold' : 'bg-emerald-100 text-emerald-700 font-semibold')) }}'
                                                                           : 'text-slate-400 hover:bg-slate-50'"
                                                                       >
                                                                    <input type="radio"
                                                                           name="scope_ui_{{ $viewKey }}_{{ $role->name }}"
                                                                           value="{{ $scopeVal }}"
                                                                           x-model="scopeMatrix['{{ $viewKey }}']['{{ $role->name }}']"
                                                                           @change="handleScopeChange('{{ $moduleKey }}', '{{ $role->name }}', '{{ $scopeVal }}')"
                                                                           class="sr-only">
                                                                    {{ ucfirst($scopeVal === 'none' ? 'None' : ($scopeVal === 'branch' ? 'Br' : ucfirst($scopeVal))) }}
                                                                </label>
                                                                @endforeach
                                                            @endif
                                                        </div>
                                                    </template>
                                                    @endforeach
                                                @else
                                                    <span class="text-slate-300">&mdash;</span>
                                                @endif
                                            </td>
                                            @foreach($standardActions as $action)
                                                <td class="py-2.5 px-2 text-center w-16">
                                                    @if(isset($actionMap[$action]))
                                                        @php $ap = $actionMap[$action]; @endphp
                                                        @foreach($roles as $role)
                                                        <template x-if="selectedRole === '{{ $role->name }}'">
                                                            <label class="inline-flex items-center justify-center {{ $role->is_owner ? '' : 'cursor-pointer' }}">
                                                                @if($role->is_owner)
                                                                    <input type="checkbox" checked disabled
                                                                           class="w-4 h-4 rounded border-slate-300 opacity-50 cursor-not-allowed"
                                                                           style="accent-color:#0b2a4a;">
                                                                @else
                                                                    <input type="checkbox"
                                                                           x-model="matrix['{{ $ap->key }}']['{{ $role->name }}']"
                                                                           @change="handleActionChange('{{ $moduleKey }}', '{{ $action }}', '{{ $role->name }}')"
                                                                           class="w-4 h-4 rounded border-slate-300 cursor-pointer"
                                                                           style="accent-color:#00b4d8;"
                                                                           :disabled="scopeMatrix['{{ $viewKey }}']?.['{{ $role->name }}'] === 'none'">
                                                                @endif
                                                            </label>
                                                        </template>
                                                        @endforeach
                                                    @else
                                                        <span class="text-slate-300">&mdash;</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            {{-- "Other" column for manage/send --}}
                                            <td class="py-2.5 px-2 text-center w-16">
                                                @if(count($otherActions) > 0)
                                                    @foreach($otherActions as $otherKey)
                                                        @php $op = $actionMap[$otherKey]; @endphp
                                                        @foreach($roles as $role)
                                                        <template x-if="selectedRole === '{{ $role->name }}'">
                                                            <label class="inline-flex items-center gap-1 {{ $role->is_owner ? '' : 'cursor-pointer' }}">
                                                                @if($role->is_owner)
                                                                    <input type="checkbox" checked disabled
                                                                           class="w-4 h-4 rounded border-slate-300 opacity-50 cursor-not-allowed"
                                                                           style="accent-color:#0b2a4a;">
                                                                @else
                                                                    <input type="checkbox"
                                                                           x-model="matrix['{{ $op->key }}']['{{ $role->name }}']"
                                                                           @change="dirty = true"
                                                                           class="w-4 h-4 rounded border-slate-300 cursor-pointer"
                                                                           style="accent-color:#00b4d8;">
                                                                @endif
                                                                <span class="text-[10px] text-slate-500">{{ ucfirst($otherKey) }}</span>
                                                            </label>
                                                        </template>
                                                        @endforeach
                                                    @endforeach
                                                @else
                                                    <span class="text-slate-300">&mdash;</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endif
                                    @endforeach
                                @endforeach

                                @if($permissions->isEmpty())
                                    <tr>
                                        <td colspan="6" class="py-12 text-center text-sm text-slate-400">
                                            No permissions defined. Run: <code class="font-mono bg-slate-100 px-1 rounded">php artisan db:seed --class=NexusPermissionSeeder</code>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Hidden inputs to submit ALL role data (not just the selected one) --}}
                <div class="hidden">
                    @foreach($roles as $role)
                        @foreach($permissions as $perm)
                            @if($perm->type === 'action' && str_ends_with($perm->key, '.view'))
                                {{-- For .view action permissions, submit based on scope (scope != 'none' means granted) --}}
                                <input type="hidden"
                                       name="permissions[{{ $perm->key }}][{{ $role->name }}]"
                                       :value="scopeMatrix['{{ $perm->key }}']?.['{{ $role->name }}'] && scopeMatrix['{{ $perm->key }}']['{{ $role->name }}'] !== 'none' ? '1' : '0'"
                                       x-bind:value="scopeMatrix['{{ $perm->key }}']?.['{{ $role->name }}'] && scopeMatrix['{{ $perm->key }}']['{{ $role->name }}'] !== 'none' ? '1' : '0'">
                                <input type="hidden"
                                       name="scopes[{{ $perm->key }}][{{ $role->name }}]"
                                       :value="scopeMatrix['{{ $perm->key }}']?.['{{ $role->name }}'] || 'none'"
                                       x-bind:value="scopeMatrix['{{ $perm->key }}']?.['{{ $role->name }}'] || 'none'">
                            @else
                                <input type="hidden"
                                       name="permissions[{{ $perm->key }}][{{ $role->name }}]"
                                       :value="matrix['{{ $perm->key }}']?.['{{ $role->name }}'] ? '1' : '0'"
                                       x-bind:value="matrix['{{ $perm->key }}']?.['{{ $role->name }}'] ? '1' : '0'">
                            @endif
                        @endforeach
                    @endforeach
                </div>

                {{-- Sticky bottom save bar --}}
                <div class="sticky bottom-0 z-20 -mx-4 lg:-mx-6 bg-white border-t border-slate-200 px-5 py-3 flex items-center justify-between mt-4"
                     style="box-shadow: 0 -2px 8px rgba(0,0,0,0.06);">
                    <div class="text-xs text-amber-600 font-medium" x-show="dirty" x-cloak>
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 mr-1"></span>
                        Unsaved changes
                    </div>
                    <div class="flex-1"></div>
                    <button type="button" @click="saveMatrix()"
                            class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-colors"
                            style="background:#0b2a4a;"
                            onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>

        {{-- ─────────────────────────────────────────────
             TAB 2: User Roles
        ───────────────────────────────────────────── --}}
        <div x-show="activeTab === 'users'" x-cloak>
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between" style="background:#f8fafc;">
                    <h3 class="font-semibold text-sm" style="color:#0b2a4a;">User Roles</h3>
                    <span class="text-xs text-slate-400">{{ $users->count() }} users</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b" style="background:#f8fafc;">
                                <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Name</th>
                                <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Email</th>
                                <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Agency</th>
                                <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Branch</th>
                                <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Current Role</th>
                                @if(auth()->user()->hasPermission('change_user_roles'))
                                    <th class="text-left py-3 px-4 font-semibold text-xs uppercase tracking-wider" style="color:#0b2a4a;">Change Role</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($users as $u)
                                @php
                                    $userRoleModel = $roles->firstWhere('name', $u->role);
                                    $badgeBg = $userRoleModel?->color ?? '#64748b';
                                    $roleLabel = $userRoleModel?->label ?? ucfirst(str_replace('_', ' ', $u->role ?? 'agent'));
                                    $branchName  = $branches->firstWhere('id', $u->branch_id)?->name ?? '—';
                                    $agencyName  = $agencies->firstWhere('id', $u->agency_id)?->name ?? '—';
                                @endphp
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-2.5 px-4 font-medium text-slate-800">{{ $u->name }}</td>
                                    <td class="py-2.5 px-4 text-slate-500">{{ $u->email }}</td>
                                    <td class="py-2.5 px-4 text-slate-500 text-xs">{{ $agencyName }}</td>
                                    <td class="py-2.5 px-4 text-slate-500 text-xs">{{ $branchName }}</td>
                                    <td class="py-2.5 px-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold text-white"
                                              style="background:{{ $badgeBg }};">
                                            {{ $roleLabel }}
                                        </span>
                                    </td>
                                    @if(auth()->user()->hasPermission('change_user_roles'))
                                        <td class="py-2.5 px-4">
                                            <form method="POST" action="{{ route('corex.role-manager.user-role') }}"
                                                  class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $u->id }}">
                                                <select name="role"
                                                        class="text-xs rounded-lg border border-slate-300 bg-white text-slate-800 py-1.5 px-2 focus:outline-none focus:border-[#00b4d8]"
                                                        style="max-width:160px;">
                                                    @foreach($roles as $role)
                                                        @if($role->is_owner && !auth()->user()->isOwnerRole())
                                                            @continue
                                                        @endif
                                                        <option value="{{ $role->name }}" {{ $u->role === $role->name ? 'selected' : '' }}>
                                                            {{ $role->label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit"
                                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition-colors"
                                                        style="background:#0b2a4a;"
                                                        onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                                                    Save
                                                </button>
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

        {{-- ─────────────────────────────────────────────
             TAB 3: Roles
        ───────────────────────────────────────────── --}}
        <div x-show="activeTab === 'roles'" x-cloak>
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between" style="background:#f8fafc;">
                    <h3 class="font-semibold text-sm" style="color:#0b2a4a;">Roles</h3>
                    <span class="text-xs text-slate-400">{{ $roles->count() }} roles</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($roles as $role)
                    <div class="px-5 py-4 flex items-center gap-4 hover:bg-slate-50 transition-colors">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-white min-w-[100px] justify-center"
                              style="background:{{ $role->color }};">
                            {{ $role->label }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono text-slate-400">{{ $role->name }}</span>
                                @if($role->is_owner)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                        OWNER
                                    </span>
                                @endif
                            </div>
                            @if($role->description)
                                <p class="text-xs text-slate-500 mt-0.5">{{ $role->description }}</p>
                            @endif
                        </div>
                        <div class="text-center px-3">
                            <div class="text-lg font-bold" style="color:#0b2a4a;">{{ $role->users_count }}</div>
                            <div class="text-[10px] text-slate-400 uppercase tracking-wider">Users</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="openEditRole({{ $role->id }}, {{ Js::from($role->only('name','label','description','color','sort_order','is_owner','can_be_deleted')) }})"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors">
                                Edit
                            </button>
                            @if(!$role->is_owner && $role->can_be_deleted)
                            <button type="button"
                                    @click="openDeleteRole({{ $role->id }}, {{ Js::from($role->label) }}, {{ $role->users_count }})"
                                    class="px-3 py-1.5 rounded-lg text-xs font-medium border border-red-200 text-red-600 hover:bg-red-50 transition-colors">
                                Delete
                            </button>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- ─────────────────────────────────────────────
         MODAL: Add / Edit Role
    ───────────────────────────────────────────── --}}
    <div x-show="showRoleModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @click.self="showRoleModal = false"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden" @click.stop>
            <div class="px-6 py-4 border-b border-slate-200" style="background:#f8fafc;">
                <h3 class="font-semibold text-sm" style="color:#0b2a4a;" x-text="editRoleId ? 'Edit Role' : 'Add New Role'"></h3>
            </div>
            <form :action="editRoleId ? '{{ url('corex/role-manager/roles') }}/' + editRoleId : '{{ route('corex.role-manager.roles.store') }}'"
                  method="POST" class="p-6 space-y-4">
                @csrf
                <template x-if="editRoleId">
                    <input type="hidden" name="_method" value="PUT">
                </template>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Label <span class="text-red-500">*</span></label>
                        <input type="text" name="label" x-model="roleForm.label"
                               @input="if(!editRoleId) roleForm.name = roleForm.label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-[#00b4d8]"
                               placeholder="Office Admin" required>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Slug</label>
                        <input type="text" name="name" x-model="roleForm.name"
                               :disabled="editRoleId !== null"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-[#00b4d8] disabled:bg-slate-100 disabled:text-slate-400 font-mono"
                               placeholder="office_admin">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Description</label>
                    <textarea name="description" x-model="roleForm.description" rows="2"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-[#00b4d8]"
                              placeholder="Can manage office operations..."></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Colour</label>
                        <div class="flex gap-2 flex-wrap">
                            @php
                                $colorPalette = ['#0d9488','#0b2a4a','#00b4d8','#0891b2','#16a34a','#dc2626','#9333ea','#ea580c','#64748b','#475569'];
                            @endphp
                            @foreach($colorPalette as $c)
                            <button type="button"
                                    @click="roleForm.color = '{{ $c }}'"
                                    :class="roleForm.color === '{{ $c }}' ? 'ring-2 ring-offset-1 ring-slate-800' : ''"
                                    class="w-7 h-7 rounded-full transition-all"
                                    style="background:{{ $c }};"></button>
                            @endforeach
                        </div>
                        <input type="hidden" name="color" x-model="roleForm.color">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" x-model="roleForm.sort_order"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:border-[#00b4d8]"
                               placeholder="0">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Preview</label>
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-white"
                          :style="'background:' + roleForm.color"
                          x-text="roleForm.label || 'New Role'"></span>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showRoleModal = false"
                            class="px-4 py-2 rounded-lg text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-white transition-colors"
                            style="background:#0b2a4a;"
                            onmouseover="this.style.background='#00b4d8'" onmouseout="this.style.background='#0b2a4a'">
                        <span x-text="editRoleId ? 'Update Role' : 'Create Role'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────
         MODAL: Delete Role (with reassignment)
    ───────────────────────────────────────────── --}}
    <div x-show="showDeleteModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
         @click.self="showDeleteModal = false"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
            <div class="px-6 py-4 border-b border-slate-200" style="background:#fef2f2;">
                <h3 class="font-semibold text-sm text-red-700">Delete Role</h3>
            </div>
            <form :action="'{{ url('corex/role-manager/roles') }}/' + deleteRoleId"
                  method="POST" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_method" value="DELETE">

                <p class="text-sm text-slate-700">
                    Are you sure you want to delete <strong x-text="deleteRoleLabel"></strong>?
                </p>

                <template x-if="deleteRoleUserCount > 0">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm text-amber-800 font-medium">
                            <span x-text="deleteRoleUserCount"></span> active user(s) have this role.
                        </p>
                        <p class="text-xs text-amber-700 mt-1">Reassign them to:</p>
                        <select name="reassign_to"
                                class="mt-2 w-full rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm focus:outline-none focus:border-[#00b4d8]">
                            @foreach($roles as $role)
                                @if(!$role->is_owner || auth()->user()->isOwnerRole())
                                <option value="{{ $role->name }}">{{ $role->label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </template>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="px-4 py-2 rounded-lg text-xs font-semibold border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-lg text-xs font-semibold text-white bg-red-600 hover:bg-red-700 transition-colors">
                        Delete Role
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

{{-- Toast notification --}}
<div x-show="copyToast" x-cloak
     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-2"
     class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl shadow-lg text-sm font-medium text-white"
     style="background:#0b2a4a;">
    <span x-text="copyToastMsg"></span>
</div>

<script>
function roleManager() {
    const grantedData = @json($granted);
    const scopeData = @json($scopeGranted);
    const rolesData = @json($rolesJson);
    const allPermKeys = @json($allPermKeys);
    const sharedModules = @json($sharedModules);

    // Module -> action keys mapping for cascading logic
    const moduleActions = @json($moduleActionsMap);

    // All .view keys
    const viewKeys = allPermKeys.filter(k => k.endsWith('.view'));

    // Initialize matrix: matrix[permKey][roleName] = true/false
    let matrix = {};
    allPermKeys.forEach(key => {
        matrix[key] = {};
        rolesData.forEach(r => {
            matrix[key][r.name] = !!(grantedData[key] && grantedData[key][r.name]);
        });
    });

    // Initialize scopeMatrix: scopeMatrix[viewKey][roleName] = 'none'|'own'|'branch'|'all'
    let scopeMatrix = {};
    viewKeys.forEach(key => {
        scopeMatrix[key] = {};
        rolesData.forEach(r => {
            if (r.is_owner) {
                scopeMatrix[key][r.name] = 'all';
            } else if (scopeData[key] && scopeData[key][r.name]) {
                scopeMatrix[key][r.name] = scopeData[key][r.name];
            } else if (grantedData[key] && grantedData[key][r.name]) {
                // Has permission but no scope set — default to 'all'
                scopeMatrix[key][r.name] = 'all';
            } else {
                scopeMatrix[key][r.name] = 'none';
            }
        });
    });

    return {
        activeTab: 'permissions',
        selectedRole: rolesData.find(r => !r.is_owner)?.name || rolesData[0]?.name || 'admin',
        matrix: matrix,
        scopeMatrix: scopeMatrix,
        dirty: false,
        openSections: {
            @foreach($matrixSections as $sectionLabel => $modules)
                '{{ md5($sectionLabel) }}': true,
            @endforeach
        },

        // Copy from role
        copyFromRole: '',
        copyToast: false,
        copyToastMsg: '',

        // Role modals
        showRoleModal: false,
        showDeleteModal: false,
        editRoleId: null,
        deleteRoleId: null,
        deleteRoleLabel: '',
        deleteRoleUserCount: 0,
        roleForm: { label: '', name: '', description: '', color: '#0d9488', sort_order: 0 },

        selectedRoleLabel() {
            const r = rolesData.find(r => r.name === this.selectedRole);
            return r ? r.label : this.selectedRole;
        },

        toggleSection(key) {
            this.openSections[key] = !this.openSections[key];
        },

        handleScopeChange(moduleKey, roleName, scopeVal) {
            this.dirty = true;
            const actions = moduleActions[moduleKey];
            if (!actions) return;

            const viewKey = actions['view'];
            const createKey = actions['create'];
            const editKey = actions['edit'];
            const archiveKey = actions['archive'];

            if (scopeVal === 'none') {
                // Setting scope to None → untick create/edit/archive
                if (createKey && this.matrix[createKey]) this.matrix[createKey][roleName] = false;
                if (editKey && this.matrix[editKey]) this.matrix[editKey][roleName] = false;
                if (archiveKey && this.matrix[archiveKey]) this.matrix[archiveKey][roleName] = false;
            }
        },

        handleActionChange(moduleKey, action, roleName) {
            this.dirty = true;
            const actions = moduleActions[moduleKey];
            if (!actions) return;

            const viewKey = actions['view'];

            if (action !== 'view') {
                const thisKey = actions[action];
                // Ticking create/edit/archive when scope is 'none' → auto-set scope to 'own'
                if (thisKey && this.matrix[thisKey]?.[roleName] && viewKey) {
                    if (this.scopeMatrix[viewKey]?.[roleName] === 'none') {
                        this.scopeMatrix[viewKey][roleName] = 'own';
                    }
                }
            }
        },

        copyPermissions() {
            const src = this.copyFromRole;
            const dst = this.selectedRole;
            if (!src || !dst || src === dst) return;

            // Find if destination is owner (shouldn't copy to owner)
            const dstRole = rolesData.find(r => r.name === dst);
            if (dstRole?.is_owner) return;

            // Copy checkbox matrix
            allPermKeys.forEach(key => {
                if (this.matrix[key]) {
                    this.matrix[key][dst] = !!(this.matrix[key][src]);
                }
            });

            // Copy scope matrix
            viewKeys.forEach(key => {
                if (this.scopeMatrix[key]) {
                    this.scopeMatrix[key][dst] = this.scopeMatrix[key][src] || 'none';
                }
            });

            this.dirty = true;
            this.copyFromRole = '';

            // Show toast
            const srcLabel = rolesData.find(r => r.name === src)?.label || src;
            this.copyToastMsg = `Copied permissions from ${srcLabel} — review and save`;
            this.copyToast = true;
            setTimeout(() => { this.copyToast = false; }, 4000);
        },

        saveMatrix() {
            this.$refs.permForm.submit();
        },

        openAddRole() {
            this.editRoleId = null;
            this.roleForm = { label: '', name: '', description: '', color: '#0d9488', sort_order: 0 };
            this.showRoleModal = true;
        },
        openEditRole(id, data) {
            this.editRoleId = id;
            this.roleForm = {
                label: data.label || '',
                name: data.name || '',
                description: data.description || '',
                color: data.color || '#0d9488',
                sort_order: data.sort_order || 0,
            };
            this.showRoleModal = true;
        },
        openDeleteRole(id, label, userCount) {
            this.deleteRoleId = id;
            this.deleteRoleLabel = label;
            this.deleteRoleUserCount = userCount;
            this.showDeleteModal = true;
        },
    };
}
</script>
@endsection
