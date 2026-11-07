@php $m = fn ($v) => number_format((float) $v, 2, '.', ' '); $methods = \App\Models\SalePayment::METHODS; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $payment->reference }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { margin: 0; background: #e2e8f0; font: 12px/1.4 'Courier New', monospace; }
        .t { width: 80mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .c { text-align: center; } .b { font-weight: 700; } .big { font-size: 15px; }
        .s { border-top: 1px dashed #000; margin: 6px 0; } .r { display: flex; justify-content: space-between; }
        .a { text-align: center; font-family: system-ui; } .a button { padding: 8px 16px; border: 0; border-radius: 8px; background: #0f172a; color: #fff; }
        @media print { body { background: #fff; } .t { margin: 0; width: 100%; } .a { display: none; } }
    </style>
</head>
<body>
<div class="t">
    <div class="c b big">{{ $store['name'] }}</div>
    <div class="c">{{ $store['address'] }}<br>{{ $store['phone'] }}</div>
    <div class="s"></div>
    <div class="c b">REÇU DE PAIEMENT</div>
    <div class="r"><span>N°</span><span class="b">{{ $payment->reference }}</span></div>
    <div class="r"><span>Date</span><span>{{ $payment->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="r"><span>Client</span><span>{{ $payment->customer->name }}</span></div>
    <div class="r"><span>Reçu par</span><span>{{ $payment->user->name }}</span></div>
    <div class="s"></div>
    <div class="r"><span>Mode</span><span>{{ $methods[$payment->method] ?? $payment->method }}</span></div>
    @if ($payment->transaction_reference)<div class="r"><span>Réf.</span><span>{{ $payment->transaction_reference }}</span></div>@endif
    @if ($payment->currency === 'USD')<div class="r"><span>Montant USD</span><span>${{ $m($payment->amount) }}</span></div>@endif
    <div class="r b big"><span>MONTANT HTG</span><span>{{ $m($payment->amount_htg) }}</span></div>
    <div class="s"></div>
    <div class="r b"><span>Reste à payer</span><span>{{ $m($payment->customer->balance) }}</span></div>
    <div class="c" style="margin-top:8px">Merci !</div>
</div>
<div class="a"><button onclick="window.print()">Imprimer</button></div>
@if ($autoPrint)<script>window.addEventListener('load', () => { window.print(); window.addEventListener('afterprint', () => window.close()); });</script>@endif
</body>
</html>
