{{--
    Tuile KPI.  @include('dashboard._kpi', ['label' => ..., 'value' => ..., 'sub' => ..., 'delta' => float|null, 'compare' => ..., 'invert' => false])
    invert = true quand une hausse est une mauvaise nouvelle (dépenses).
--}}
@php
    $delta = $delta ?? null;
    $invert = $invert ?? false;
    $good = $delta !== null && (($delta >= 0) xor $invert);
@endphp
<div class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="text-sm font-medium text-slate-500">{{ $label }}</div>
    <div class="mt-2 text-2xl font-bold tracking-tight text-slate-900 tabular-nums">{{ $value }}</div>
    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
        @if ($delta !== null)
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold {{ $good ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                <span aria-hidden="true">{{ $delta >= 0 ? '▲' : '▼' }}</span>
                {{ \App\Support\Format::percent(abs($delta)) }}
                <span class="sr-only">{{ $delta >= 0 ? 'en hausse' : 'en baisse' }}</span>
            </span>
            <span class="text-slate-500">vs {{ $compare }}</span>
        @endif
        @if (! empty($sub))<span class="text-slate-500">{{ $sub }}</span>@endif
    </div>
</div>
