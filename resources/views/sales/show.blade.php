@extends('layouts.admin')

@section('title', 'Vente '.$sale->reference)

@php
    $F = \App\Support\Format::class;
    $badge = ['completed' => 'bg-emerald-100 text-emerald-800', 'partially_refunded' => 'bg-amber-100 text-amber-800', 'refunded' => 'bg-slate-200 text-slate-700', 'cancelled' => 'bg-rose-100 text-rose-700', 'held' => 'bg-sky-100 text-sky-800'];
    $canReturn = auth()->user()->can('sales.return') && in_array($sale->status, ['completed', 'partially_refunded']);
@endphp

@section('actions')
    @if ($sale->status !== 'held')
        <a href="{{ route('pos.receipt', $sale) }}" target="_blank" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100">Réimprimer le reçu</a>
    @endif
@endsection

@section('content')
<div class="mx-auto grid max-w-6xl gap-6 xl:grid-cols-3" x-data="{ panel: null }">
    <div class="space-y-6 xl:col-span-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[$sale->status] ?? '' }}">{{ $statuses[$sale->status] ?? $sale->status }}</span>
                    <div class="mt-2 text-sm text-slate-600">
                        {{ $sale->created_at->format('d/m/Y à H:i') }} · {{ $sale->cashier?->name }} · {{ $sale->cashSession?->register?->name }}<br>
                        Client : @if ($sale->customer)<a href="{{ route('customers.show', $sale->customer) }}" class="font-medium text-emerald-700 hover:underline">{{ $sale->customer->name }}</a>@else comptoir @endif
                        · {{ $sale->sale_type === 'wholesale' ? 'Vente en gros' : 'Vente au détail' }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs uppercase text-slate-500">Total</div>
                    <div class="text-3xl font-bold tabular-nums {{ $sale->status === 'cancelled' ? 'text-slate-400 line-through' : '' }}">{{ $F::money($sale->total) }}</div>
                </div>
            </div>
            @if ($sale->status === 'cancelled')
                <div class="mt-4 rounded-lg bg-rose-50 p-3 text-sm text-rose-800">Annulée le {{ $sale->cancelled_at?->format('d/m/Y H:i') }} par {{ $sale->canceller?->name }} : « {{ $sale->cancel_reason }} »</div>
            @endif

            <table class="mt-6 w-full text-sm tabular-nums">
                <thead class="border-y border-slate-200 text-left text-xs uppercase text-slate-500">
                    <tr><th class="py-2">Article</th><th class="py-2 text-right">Qté</th><th class="py-2 text-right">Prix</th><th class="py-2 text-right">Remise</th><th class="py-2 text-right">Total</th>@can('reports.profit')<th class="py-2 text-right">Marge</th>@endcan</tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->items as $i)
                        <tr>
                            <td class="py-2">
                                {{ $i->product_name }} @if ($i->price_type === 'wholesale')<span class="rounded bg-amber-100 px-1 text-[10px] font-bold text-amber-800">GROS</span>@endif
                                <span class="block text-xs text-slate-400">lot(s) : {{ $i->batchAllocations->map(fn ($a) => ($a->batch->batch_number ?: '#'.$a->batch_id).($a->batch->expiry_date ? ' exp. '.$a->batch->expiry_date->format('d/m/y') : ''))->join(', ') ?: '—' }}</span>
                                @if ((float) $i->returned_quantity > 0)<span class="block text-xs font-semibold text-amber-700">{{ $F::qty($i->returned_quantity) }} retourné(s)</span>@endif
                            </td>
                            <td class="py-2 text-right">{{ $F::qty($i->quantity) }} {{ $i->unit_name }}</td>
                            <td class="py-2 text-right">{{ $F::money($i->unit_price) }}</td>
                            <td class="py-2 text-right text-slate-500">{{ (float) $i->discount_amount > 0 ? $F::money($i->discount_amount) : '' }}</td>
                            <td class="py-2 text-right font-medium">{{ $F::money($i->total) }}</td>
                            @can('reports.profit')<td class="py-2 text-right text-slate-500">{{ $F::money((float) $i->total - (float) $i->unit_cost * (float) $i->quantity) }}</td>@endcan
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="text-right">
                    <tr class="border-t border-slate-200"><td colspan="4" class="pt-3">Sous-total</td><td class="pt-3">{{ $F::money($sale->subtotal) }}</td></tr>
                    @if ((float) $sale->discount_amount > 0)<tr><td colspan="4">Remises</td><td>− {{ $F::money($sale->discount_amount) }}</td></tr>@endif
                    <tr class="font-bold"><td colspan="4">Total</td><td>{{ $F::money($sale->total) }}</td></tr>
                    @can('reports.profit')<tr class="text-slate-500"><td colspan="4">Coût des marchandises · bénéfice</td><td>{{ $F::money($sale->cost_total) }} · {{ $F::money($sale->grossProfit()) }}</td></tr>@endcan
                </tfoot>
            </table>
        </div>

        @if ($sale->returns->isNotEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-6">
                <h2 class="font-semibold">Retours</h2>
                @foreach ($sale->returns as $r)
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-sm">
                        <div class="flex justify-between"><strong>{{ $r->reference }}</strong><span class="font-semibold">{{ $F::money($r->total) }}</span></div>
                        <div class="text-xs text-slate-500">{{ $r->created_at->format('d/m/Y H:i') }} · {{ $r->user?->name }} · {{ $r->refund_method === 'cash' ? 'remboursé en espèces' : 'déduit du compte client' }} · {{ $r->restock ? 'remis en stock' : 'non remis en stock' }} · « {{ $r->reason }} »</div>
                        <ul class="mt-1 text-xs">@foreach ($r->items as $ri)<li>{{ $F::qty($ri->quantity) }} × {{ $ri->saleItem->product_name }}</li>@endforeach</ul>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm">
            <h2 class="font-semibold">Paiement</h2>
            <ul class="mt-3 space-y-1.5">
                @foreach ($sale->payments as $p)
                    <li class="flex justify-between"><span>{{ $methods[$p->method] ?? $p->method }} {{ $p->currency === 'USD' ? '($'.number_format((float) $p->amount, 2).')' : '' }} <span class="text-xs text-slate-400">{{ $p->transaction_reference }}</span></span><span class="tabular-nums">{{ $F::money($p->amount_htg) }}</span></li>
                @endforeach
                @if ((float) $sale->change_amount > 0)<li class="flex justify-between text-slate-500"><span>Monnaie rendue</span><span class="tabular-nums">− {{ $F::money($sale->change_amount) }}</span></li>@endif
                @if ((float) $sale->due_amount > 0)<li class="flex justify-between font-semibold text-rose-600"><span>À crédit</span><span class="tabular-nums">{{ $F::money($sale->due_amount) }}</span></li>@endif
            </ul>
        </div>

        @can('sales.cancel')
            @if ($sale->status === 'completed')
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <button @click="panel = panel === 'cancel' ? null : 'cancel'" class="w-full text-left font-semibold text-rose-700">Annuler la vente</button>
                    <form x-show="panel === 'cancel'" x-cloak method="POST" action="{{ route('sales.cancel', $sale) }}" onsubmit="return confirm('Annuler toute la vente ? Le stock sera remis et le crédit client effacé.')" class="mt-3 space-y-2">
                        @csrf
                        <input name="reason" required maxlength="255" placeholder="Raison (obligatoire)" class="w-full rounded-lg border-slate-300 text-sm">
                        <p class="text-xs text-slate-500">Pensez à rendre l'argent au client : l'écart apparaîtra sinon à la fermeture de caisse.</p>
                        <button class="w-full rounded-lg bg-rose-600 py-2 text-sm font-semibold text-white hover:bg-rose-700">Confirmer l'annulation</button>
                    </form>
                </div>
            @endif
        @endcan

        @if ($canReturn)
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <button @click="panel = panel === 'return' ? null : 'return'" class="w-full text-left font-semibold text-amber-700">Retour de marchandise</button>
                <form x-show="panel === 'return'" x-cloak method="POST" action="{{ route('sales.return', $sale) }}" class="mt-3 space-y-3 text-sm">
                    @csrf
                    @foreach ($sale->items as $i)
                        @if ($i->returnableQuantity() > 0)
                            <label class="flex items-center justify-between gap-2">
                                <span class="min-w-0 truncate">{{ $i->product_name }} <span class="text-xs text-slate-400">(max {{ $F::qty($i->returnableQuantity()) }} {{ $i->unit_name }})</span></span>
                                <input type="number" name="items[{{ $i->id }}]" min="0" max="{{ $i->returnableQuantity() }}" step="0.001" value="0" class="w-20 rounded-lg border-slate-300 text-right text-sm">
                            </label>
                        @endif
                    @endforeach
                    <select name="refund_method" class="w-full rounded-lg border-slate-300 text-sm">
                        <option value="cash">Rembourser en espèces (depuis ma caisse)</option>
                        @if ($sale->customer)<option value="customer_balance">Déduire du compte du client</option>@endif
                    </select>
                    <label class="flex items-center gap-2"><input type="hidden" name="restock" value="0"><input type="checkbox" name="restock" value="1" checked class="rounded border-slate-300"> Remettre en stock (marchandise en bon état)</label>
                    <input name="reason" required maxlength="255" placeholder="Raison du retour" class="w-full rounded-lg border-slate-300 text-sm">
                    <button class="w-full rounded-lg bg-amber-500 py-2 font-semibold text-white hover:bg-amber-600">Enregistrer le retour</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
