@php
    $m = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
    $methods = \App\Models\SalePayment::METHODS;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $sale->reference }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font: 12px/1.35 'Courier New', ui-monospace, monospace; color: #000; }
        .ticket { width: 80mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .big { font-size: 15px; }
        .small { font-size: 10px; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .logo { max-width: 40mm; max-height: 20mm; }
        .item { margin-bottom: 3px; }
        .badge { border: 1px solid #000; padding: 0 3px; font-size: 9px; }
        .actions { text-align: center; margin: 12px; font-family: system-ui, sans-serif; }
        .actions button { padding: 8px 16px; margin: 0 4px; border: 0; border-radius: 8px; background: #0f172a; color: #fff; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .ticket { margin: 0; width: 100%; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="center">
        @if ($store['logo'])<img class="logo" src="{{ asset('storage/'.$store['logo']) }}" alt=""><br>@endif
        <div class="bold big">{{ $store['name'] }}</div>
        @if ($store['address'])<div>{{ $store['address'] }}</div>@endif
        @if ($store['phone'])<div>Tél : {{ $store['phone'] }}</div>@endif
        @if ($store['nif'])<div>NIF : {{ $store['nif'] }}</div>@endif
    </div>

    <div class="sep"></div>
    <div class="row"><span>Reçu</span><span class="bold">{{ $sale->reference }}</span></div>
    <div class="row"><span>Date</span><span>{{ $sale->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Caissier</span><span>{{ $sale->cashier->name }}</span></div>
    @if ($sale->customer)
        <div class="row"><span>Client</span><span>{{ $sale->customer->name }}</span></div>
    @endif
    @if ($sale->status === \App\Models\Sale::CANCELLED)
        <div class="center bold big" style="margin-top:4px">*** VENTE ANNULÉE ***</div>
    @endif

    <div class="sep"></div>
    @foreach ($sale->items as $item)
        <div class="item">
            <div>{{ $item->product_name }} @if ($item->price_type === 'wholesale')<span class="badge">GROS</span>@endif</div>
            <div class="row">
                <span>{{ $q($item->quantity) }} {{ $item->unit_name }} × {{ $m($item->unit_price) }}</span>
                <span>{{ $m((float) $item->total + (float) $item->discount_amount) }}</span>
            </div>
            @if ((float) $item->discount_amount > 0)
                <div class="row small"><span>&nbsp;&nbsp;Remise</span><span>-{{ $m($item->discount_amount) }}</span></div>
            @endif
        </div>
    @endforeach

    <div class="sep"></div>
    <div class="row"><span>Sous-total</span><span>{{ $m($sale->subtotal) }}</span></div>
    @if ((float) $sale->discount_amount > 0)
        <div class="row"><span>Remises</span><span>-{{ $m($sale->discount_amount) }}</span></div>
    @endif
    <div class="row bold big"><span>TOTAL HTG</span><span>{{ $m($sale->total) }}</span></div>
    @if ($sale->exchange_rate)
        <div class="row small"><span>Équivalent USD (taux {{ $q($sale->exchange_rate) }})</span><span>${{ $m((float) $sale->total / (float) $sale->exchange_rate) }}</span></div>
    @endif

    <div class="sep"></div>
    @foreach ($sale->payments as $p)
        <div class="row">
            <span>{{ $methods[$p->method] ?? $p->method }} @if ($p->currency === 'USD')(${{ $m($p->amount) }})@endif</span>
            <span>{{ $m($p->amount_htg) }}</span>
        </div>
        @if ($p->transaction_reference)<div class="small">&nbsp;&nbsp;Réf : {{ $p->transaction_reference }}</div>@endif
    @endforeach
    @if ((float) $sale->change_amount > 0)
        <div class="row bold"><span>Monnaie rendue</span><span>{{ $m($sale->change_amount) }}</span></div>
    @endif
    @if ((float) $sale->due_amount > 0)
        <div class="row bold"><span>À CRÉDIT</span><span>{{ $m($sale->due_amount) }}</span></div>
        <div class="row small"><span>Solde total du client</span><span>{{ $m($sale->customer?->balance) }}</span></div>
        <div style="margin-top:14px">Signature client : ____________________</div>
    @endif

    <div class="sep"></div>
    <div class="center">{{ $sale->items->count() }} article(s)</div>
    <div class="center bold" style="margin-top:4px">{{ $store['footer'] }}</div>
    <div class="center small" style="margin-top:4px">Marchandise vérifiée à la livraison.</div>
</div>

<div class="actions">
    <button onclick="window.print()">Imprimer</button>
    <button onclick="window.close()">Fermer</button>
</div>

@if ($autoPrint)
    <script>
        window.addEventListener('load', () => {
            window.print();
            window.addEventListener('afterprint', () => window.close());
        });
    </script>
@endif
</body>
</html>
