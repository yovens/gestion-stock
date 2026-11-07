@extends('layouts.admin')

@section('title', 'Rapports')

@section('content')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($reports as $key => [$title, $description])
        <a href="{{ route('reports.show', $key) }}" class="group rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-emerald-400 hover:shadow">
            <div class="font-semibold group-hover:text-emerald-700">{{ $title }}</div>
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
            <p class="mt-3 text-xs font-medium text-slate-400">Écran · Impression PDF · Excel</p>
        </a>
    @endforeach
</div>
@endsection
