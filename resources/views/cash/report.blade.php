@php
    $m = fn ($v) => number_format((float) $v, 2, '.', ' ');
    $sign = fn ($v) => ((float) $v > 0 ? '+' : '').$m($v);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport de caisse #{{ $session->id }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { margin: 0; background: #e2e8f0; font: 12px/1.4 'Courier New', ui-monospace, monospace; }
        .ticket { width: 80mm; margin: 12px auto; padding: 4mm; background: #fff; }
        .center { text-align: center; } .bold { font-weight: 700; } .big { font-size: 15px; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .actions { text-align: center; margin: 12px; font-family: system-ui, sans-serif; }
        .actions a, .actions button { display: inline-block; padding: 8px 16px; margin: 0 4px; border: 0; border-radius: 8px; background: #0f172a; color: #fff; text-decoration: none; cursor: pointer; font-size: 14px; }
        @media print { body { background: #fff; } .ticket { margin: 0; width: 100%; } .actions { display: none; } }
    </style>
</head>
<body>
<div class="ticket">
    <div class="center bold big">{{ \App\Models\Setting::get('store_name', config('app.name')) }}</div>
    <div class="center bold">RAPPORT DE FERMETURE</div>
    <div class="sep"></div>
    <div class="row"><span>Caisse</span><span>{{ $session->register->name }}</span></div>
    <div class="row"><span>Caissier</span><span>{{ $session->user->name }}</span></div>
    <div class="row"><span>Ouverture</span><span>{{ $session->opened_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Fermeture</span><span>{{ $session->closed_at?->format('d/m/Y H:i') ?? 'ouverte' }}</span></div>

    <div class="sep"></div>
    <div class="row"><span>Ventes ({{ $summary['sales_count'] }})</span><span>{{ $m($summary['sales_total']) }}</span></div>
    @foreach ($summary['by_method'] as $method => $amount)
        <div class="row"><span>&nbsp;&nbsp;{{ \App\Models\SalePayment::METHODS[$method] ?? $method }}</span><span>{{ $m($amount) }}</span></div>
    @endforeach
    <div class="row"><span>Crédit accordé</span><span>{{ $m($summary['credit_sales']) }}</span></div>
    <div class="row"><span>Dépenses</span><span>-{{ $m($summary['expenses']) }}</span></div>
    <div class="row"><span>Remboursements</span><span>-{{ $m($summary['refunds']) }}</span></div>

    <div class="sep"></div>
    <div class="bold">ESPÈCES HTG</div>
    <div class="row"><span>Fond initial</span><span>{{ $m($session->opening_amount_htg) }}</span></div>
    <div class="row"><span>Attendu</span><span>{{ $m($session->expected_amount_htg ?? $summary['expected_htg']) }}</span></div>
    @if ($session->status === 'closed')
        <div class="row"><span>Compté</span><span>{{ $m($session->counted_amount_htg) }}</span></div>
        <div class="row bold big"><span>Écart</span><span>{{ $sign($session->difference_htg) }}</span></div>
    @endif

    <div class="sep"></div>
    <div class="bold">ESPÈCES USD</div>
    <div class="row"><span>Fond initial</span><span>{{ $m($session->opening_amount_usd) }}</span></div>
    <div class="row"><span>Attendu</span><span>{{ $m($session->expected_amount_usd ?? $summary['expected_usd']) }}</span></div>
    @if ($session->status === 'closed')
        <div class="row"><span>Compté</span><span>{{ $m($session->counted_amount_usd) }}</span></div>
        <div class="row bold"><span>Écart</span><span>{{ $sign($session->difference_usd) }}</span></div>
    @endif

    @if ($session->closing_notes)
        <div class="sep"></div>
        <div>Remarques : {{ $session->closing_notes }}</div>
    @endif

    <div class="sep"></div>
    <div style="margin-top:18px">Caissier : ____________________</div>
    <div style="margin-top:18px">Responsable : _________________</div>
</div>

<div class="actions">
    <button onclick="window.print()">Imprimer</button>
    <a href="{{ route('cash.open') }}">Nouvelle session</a>
</div>

@if ($autoPrint)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
