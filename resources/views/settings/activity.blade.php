@extends('layouts.admin')

@section('title', 'Journal d\'activité')

@section('content')
<div class="space-y-4">
    <div class="flex flex-wrap gap-2 text-sm">
        <a href="{{ route('activity.index') }}" class="rounded-full px-3 py-1 font-medium {{ request('journal') ? 'bg-white ring-1 ring-slate-200' : 'bg-slate-900 text-white' }}">Tout</a>
        @foreach ($logs as $l)
            <a href="{{ route('activity.index', ['journal' => $l]) }}" class="rounded-full px-3 py-1 font-medium {{ request('journal') === $l ? 'bg-slate-900 text-white' : 'bg-white ring-1 ring-slate-200' }}">{{ ucfirst($l) }}</a>
        @endforeach
    </div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Date</th><th class="py-3">Qui</th><th class="py-3">Action</th><th class="px-4 py-3">Détail</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($activities as $a)
                    @php
                        // activitylog v5 : les changements sont dans attribute_changes (v4 : properties)
                        $changes = collect($a->attribute_changes ?? $a->properties ?? []);
                        $old = $changes['old'] ?? []; $new = $changes['attributes'] ?? [];
                    @endphp
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-4 py-2.5">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-2.5">{{ $a->causer?->name ?? 'Système' }}</td>
                        <td class="py-2.5">
                            <span class="text-xs uppercase text-slate-400">{{ $a->log_name }}</span><br>
                            @php $label = ['created' => 'Création', 'updated' => 'Modification', 'deleted' => 'Suppression'][$a->description] ?? $a->description; @endphp
                            {{ $label }}
                            @if ($a->subject && isset($a->subject->name)) · <strong>{{ $a->subject->name }}</strong>@endif
                        </td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">
                            @if ($new)
                                @foreach ($new as $field => $value)
                                    <div>{{ $field }} : @if (array_key_exists($field, $old))<span class="text-rose-600 line-through">{{ is_scalar($old[$field]) ? $old[$field] : json_encode($old[$field]) }}</span> → @endif<span class="text-emerald-700">{{ is_scalar($value) ? $value : json_encode($value) }}</span></div>
                                @endforeach
                            @else
                                @foreach (collect($a->properties ?? [])->except(['old', 'attributes']) as $k => $v)
                                    <div>{{ $k }} : {{ is_scalar($v) ? $v : json_encode($v) }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">Aucune activité enregistrée.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if ($activities->hasPages())<div class="border-t border-slate-200 px-4 py-3">{{ $activities->links() }}</div>@endif
    </div>
</div>
@endsection