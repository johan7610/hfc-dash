@extends('layouts.corex')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6"
     x-data="fieldGroupManager()"
     x-cloak>

    {{-- Header --}}
    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Field Groups</h2>
            <div class="text-sm text-white/60">Create reusable groups of named fields for quick placement in templates.</div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('docuperfect.settings.namedFields') }}" class="text-sm text-white/70 hover:text-white">Named Fields</a>
            <a href="{{ route('docuperfect.templates.index') }}" class="text-sm text-white/70 hover:text-white">Templates</a>
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-900 px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- LEFT: Group list --}}
        <div class="lg:col-span-5 space-y-3">
            <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">Existing Groups</div>
                    <div class="text-xs text-slate-500 dark:text-slate-400" x-text="groups.length + ' total'"></div>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    <template x-for="group in groups" :key="group.id">
                        <div class="px-4 py-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-900 cursor-pointer transition"
                             :class="{ 'bg-teal-50 dark:bg-teal-900/20 border-l-4 border-teal-500': editingId === group.id }"
                             @click="editGroup(group)">
                            <div>
                                <div class="text-sm font-medium text-slate-900 dark:text-slate-100" x-text="group.name"></div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    <span x-text="group.fields.length + ' field' + (group.fields.length !== 1 ? 's' : '')"></span>
                                    <span class="ml-2 px-1.5 py-0.5 rounded text-[10px] font-medium"
                                          :class="group.layout === 'horizontal' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'"
                                          x-text="group.layout"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click.stop="editGroup(group)"
                                        class="text-xs text-teal-600 hover:text-teal-800 font-medium">Edit</button>
                                <button @click.stop="deleteGroup(group)"
                                        class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                            </div>
                        </div>
                    </template>

                    <div x-show="groups.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
                        No field groups yet. Create one on the right.
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT: Create/Edit form --}}
        <div class="lg:col-span-7">
            <div class="ds-status-card p-5 space-y-4">
                <h3 class="ds-section-header" x-text="editingId ? 'Edit Group' : 'Create Group'"></h3>

                {{-- Group Name --}}
                <div>
                    <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Group Name</label>
                    <input x-model="form.name" type="text" required
                           class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                           placeholder="e.g. Lessor — Full">
                </div>

                {{-- Layout toggle --}}
                <div>
                    <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Layout</label>
                    <div class="flex gap-2">
                        <button @click="form.layout = 'vertical'"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                :class="form.layout === 'vertical' ? 'bg-teal-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'">
                            Vertical
                        </button>
                        <button @click="form.layout = 'horizontal'"
                                class="px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                :class="form.layout === 'horizontal' ? 'bg-teal-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'">
                            Horizontal
                        </button>
                    </div>
                </div>

                {{-- Field picker --}}
                <div>
                    <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Add Fields (click to add)</label>
                    <div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-3 max-h-52 overflow-y-auto space-y-3">
                        @foreach($pillarLabels as $key => $label)
                            @if(isset($namedFields[$key]) && $namedFields[$key]->count())
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ $label }}</div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($namedFields[$key] as $nf)
                                            <button type="button"
                                                    @click="addField({{ $nf->id }}, {{ json_encode($nf->name) }})"
                                                    class="px-2 py-1 rounded text-[11px] font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-teal-400 hover:text-teal-700 transition">
                                                {{ $nf->name }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Selected fields (sortable list) --}}
                <div>
                    <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">
                        Group Fields <span class="text-slate-400" x-text="'(' + form.fields.length + ')'"></span>
                    </label>
                    <div class="space-y-1.5 min-h-[40px]" x-ref="sortableList">
                        <template x-for="(field, index) in form.fields" :key="index">
                            <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700"
                                 draggable="true"
                                 @dragstart="dragStart(index, $event)"
                                 @dragover.prevent="dragOver(index, $event)"
                                 @drop.prevent="dragDrop(index)"
                                 @dragend="dragEnd()">
                                <span class="text-slate-400 cursor-grab text-xs">&#x2630;</span>
                                <span class="text-sm text-slate-900 dark:text-slate-100 flex-1" x-text="field.name"></span>
                                <input x-model="field.label_override" type="text"
                                       class="w-32 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 px-2 py-1 text-[11px]"
                                       placeholder="Label override">
                                <button @click="removeField(index)"
                                        class="text-red-400 hover:text-red-600 text-xs font-bold">&times;</button>
                            </div>
                        </template>
                        <div x-show="form.fields.length === 0" class="text-center text-xs text-slate-400 py-3">
                            Click fields above to add them to this group.
                        </div>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center gap-3 pt-2">
                    <button @click="saveGroup()"
                            :disabled="saving || !form.name || form.fields.length === 0"
                            class="corex-btn-primary text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-text="saving ? 'Saving...' : (editingId ? 'Update Group' : 'Create Group')"></span>
                    </button>
                    <button x-show="editingId" @click="resetForm()"
                            class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fieldGroupManager() {
    return {
        groups: @json($groupsData),
        editingId: null,
        saving: false,
        form: { name: '', layout: 'vertical', fields: [] },

        // Drag reorder state
        dragIdx: null,

        addField(id, name) {
            this.form.fields.push({ named_field_id: id, label_override: null, name: name });
        },

        removeField(index) {
            this.form.fields.splice(index, 1);
        },

        dragStart(index, e) {
            this.dragIdx = index;
            e.dataTransfer.effectAllowed = 'move';
        },
        dragOver(index, e) {
            e.dataTransfer.dropEffect = 'move';
        },
        dragDrop(index) {
            if (this.dragIdx === null || this.dragIdx === index) return;
            const item = this.form.fields.splice(this.dragIdx, 1)[0];
            this.form.fields.splice(index, 0, item);
            this.dragIdx = null;
        },
        dragEnd() { this.dragIdx = null; },

        editGroup(group) {
            this.editingId = group.id;
            this.form = {
                name: group.name,
                layout: group.layout,
                fields: JSON.parse(JSON.stringify(group.fields)),
            };
        },

        resetForm() {
            this.editingId = null;
            this.form = { name: '', layout: 'vertical', fields: [] };
        },

        async saveGroup() {
            this.saving = true;
            const payload = {
                name: this.form.name,
                layout: this.form.layout,
                fields: this.form.fields.map(f => ({
                    named_field_id: f.named_field_id,
                    label_override: f.label_override || null,
                })),
            };

            const url = this.editingId
                ? '{{ url("docuperfect/field-groups") }}/' + this.editingId
                : '{{ route("docuperfect.field-groups.store") }}';
            const method = this.editingId ? 'PUT' : 'POST';

            try {
                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!res.ok) throw new Error('Save failed');
                // Reload page to refresh data
                window.location.reload();
            } catch (e) {
                alert('Failed to save field group. Please try again.');
            } finally {
                this.saving = false;
            }
        },

        async deleteGroup(group) {
            if (!confirm('Archive field group "' + group.name + '"?')) return;

            try {
                const res = await fetch('{{ url("docuperfect/field-groups") }}/' + group.id, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                if (!res.ok) throw new Error('Delete failed');
                window.location.reload();
            } catch (e) {
                alert('Failed to archive field group.');
            }
        },
    };
}
</script>
@endsection
