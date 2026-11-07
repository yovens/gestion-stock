@extends('layouts.admin')

@section('title', $report['title'])

@php
    $F = \App\Support\Format::class;
    $cell = function ($row, $k) use ($report, $F) {
        $v = $row[$k] ?? '';
        return in_array($k, $report['money'], true) && is_numeric($v) ? $F::money($v) : (is_float($v) ? $F::qty($v) : $v);
    };
@endphp

@section('actions')
    <a href="{{ route('reports.index') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Tous les rapports</a>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="rounded-lg px-3 py-2 text-sm font-medium text-emerald-700 hover:bg-emerald-50">Exporter Excel</a>
    <button onclick="window.print()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Imprimer / PDF</button>
@endsection

@section('content')
<div class="space-y-4">
    @if ($report['period'])
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm print:hidden">
            <label>Du <input type="date" name="du" value="{{ $from->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
            <label>au <input type="date" name="au" value="{{ $to->toDateString() }}" class="rounded-lg border-slate-300 text-sm"></label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white">Afficher</button>
            <div class="flex gap-1">
                @foreach (['Aujourd\'hui' => [today(), today()], 'Ce mois' => [now()->startOfMonth(), today()], 'Mois dernier' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()], 'Cette année' => [now()->startOfYear(), today()]] as $l => [$a, $b])
                    <a href="{{ request()->fullUrlWithQuery(['du' => $a->toDateString(), 'au' => $b->toDateString()]) }}" class="rounded-lg bg-slate-100 px-3 py-2 hover:bg-slate-200">{{ $l }}</a>
                @endforeach
            </div>
        </form>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 print:border-0 print:p-0">
        <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
            <div>
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ $store }}</div>
                <h2 class="text-lg font-bold">{{ $report['title'] }}</h2>
            </div>
            <div class="text-sm text-slate-500">{{ $report['period'] ? 'du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y') : 'au '.now()->format('d/m/Y H:i') }}</div>
        </div>
        @if (! empty($report['note']))<p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ $report['note'] }}</p>@endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm tabular-nums">
                <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500">
                    <tr>@foreach ($report['columns'] as $k => $label)<th class="px-2 py-2 {{ in_array($k, $report['money'], true) ? 'text-right' : '' }}">{{ $label }}</th>@endforeach</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['rows'] as $row)
                        @php $strong = isset($row['label']) && (str_starts_with($row['label'], '=') ); @endphp
                        <tr class="{{ $strong ? 'bg-slate-50 font-semibold' : '' }}">
                            @foreach (array_keys($report['columns']) as $k)
                                <td class="px-2 py-1.5 {{ in_array($k, $report['money'], true) ? 'text-right' : '' }} {{ in_array($k, $report['money'], true) && is_numeric($row[$k] ?? null) && $row[$k] < 0 ? 'text-rose-600' : '' }}">
                                    @if ($k === 'id' && ($report['link'] ?? null))
                                        <a href="{{ route($report['link'], $row[$k]) }}" target="_blank" class="text-emerald-700 hover:underline">{{ $row[$k] }}</a>
                                    @else
                                        {{ $cell($row, $k) }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($report['columns']) }}" class="py-10 text-center text-slate-500">Aucune donnée.</td></tr>
                    @endforelse
                </tbody>
                @if (! empty($report['totals']) && count($report['rows']))
                    <tfoot><tr class="border-t-2 border-slate-300 font-bold">
                        @foreach (array_keys($report['columns']) as $k)
                            <td class="px-2 py-2 {{ in_array($k, $report['money'], true) ? 'text-right' : '' }}">{{ isset($report['totals'][$k]) ? $cell($report['totals'], $k) : '' }}</td>
                        @endforeach
                    </tr></tfoot>
                @endif
            </table>
        </div>
        <p class="mt-6 hidden text-xs text-slate-400 print:block">Imprimé le {{ now()->format('d/m/Y à H:i') }} par {{ auth()->user()->name }}</p>
    </div>
</div>
@endsection
