<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\ExchangeRate;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    /** Remboursement de crédit. Les espèces vont dans la caisse ouverte de l'utilisateur, s'il en a une. */
    public function pay(Customer $customer, float $amount, string $method, User $user, string $currency = 'HTG',
                        ?string $reference = null, ?string $notes = null): CustomerPayment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new BusinessException('Le montant doit être supérieur à zéro.');
        }

        return DB::transaction(function () use ($customer, $amount, $method, $user, $currency, $reference, $notes) {
            $customer = Customer::lockForUpdate()->findOrFail($customer->id);
            $rate = $currency === 'USD' ? ExchangeRate::current() : 1.0;
            $htg = round($amount * $rate, 2);

            if ($htg > (float) $customer->balance + 0.005) {
                throw new BusinessException(sprintf('Le paiement (%s HTG) dépasse ce que le client doit (%s HTG).',
                    number_format($htg, 2, ',', ' '), number_format((float) $customer->balance, 2, ',', ' ')));
            }

            $session = CashSession::openFor($user);
            if ($method === 'cash' && ! $session && ! $user->can('cash.view_all')) {
                throw new BusinessException('Ouvrez votre caisse pour encaisser des espèces.');
            }

            $payment = CustomerPayment::create([
                'reference' => ReferenceGenerator::next('PC'),
                'customer_id' => $customer->id,
                'cash_session_id' => $session?->id,
                'method' => $method,
                'currency' => $currency,
                'amount' => $amount,
                'exchange_rate' => $rate,
                'amount_htg' => $htg,
                'transaction_reference' => $reference,
                'notes' => $notes,
                'user_id' => $user->id,
            ]);

            $customer->decrement('balance', $htg);

            return $payment;
        });
    }

    /** Ajuste le solde à la mise en service (dette existante). */
    public function setOpeningBalance(Customer $customer, float $amount): void
    {
        $customer->forceFill(['balance' => round($amount, 2)])->save();
    }

    /**
     * Relevé de compte : opérations entre deux dates avec solde courant.
     *
     * @return array{opening: float, closing: float, lines: Collection}
     */
    public function statement(Customer $customer, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $events = collect();

        $customer->sales()->whereIn('status', Sale::COUNTED)->where('created_at', '>=', $from)->where('due_amount', '>', 0)
            ->get(['id', 'reference', 'created_at', 'total', 'due_amount'])
            ->each(fn ($s) => $events->push(['date' => $s->created_at, 'label' => "Achat à crédit {$s->reference} (total ".number_format((float) $s->total, 2, ',', ' ').')', 'debit' => (float) $s->due_amount, 'credit' => 0.0, 'sale_id' => $s->id]));

        $customer->payments()->where('created_at', '>=', $from)->get()
            ->each(fn ($p) => $events->push(['date' => $p->created_at, 'label' => "Paiement {$p->reference}".($p->currency === 'USD' ? ' ($'.number_format((float) $p->amount, 2).')' : ''), 'debit' => 0.0, 'credit' => (float) $p->amount_htg, 'sale_id' => null]));

        DB::table('sale_returns')->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.customer_id', $customer->id)->where('sale_returns.refund_method', 'customer_balance')
            ->where('sale_returns.created_at', '>=', $from)
            ->get(['sale_returns.reference', 'sale_returns.created_at', 'sale_returns.total'])
            ->each(fn ($r) => $events->push(['date' => CarbonImmutable::parse($r->created_at), 'label' => "Retour {$r->reference}", 'debit' => 0.0, 'credit' => (float) $r->total, 'sale_id' => null]));

        $events = $events->sortBy('date')->values();

        // Solde à la date de début = solde actuel − mouvements depuis cette date
        $sinceFrom = $events->sum(fn ($e) => $e['debit'] - $e['credit']);
        $opening = round((float) $customer->balance - $sinceFrom, 2);

        $running = $opening;
        $lines = $events->filter(fn ($e) => $e['date'] <= $to)->map(function ($e) use (&$running) {
            $running = round($running + $e['debit'] - $e['credit'], 2);

            return $e + ['balance' => $running];
        })->values();

        return ['opening' => $opening, 'closing' => $running, 'lines' => $lines];
    }

    /** Ancienneté de la dette : date de la plus vieille vente à crédit non couverte (méthode FIFO). */
    public function oldestUnpaidDate(Customer $customer): ?CarbonImmutable
    {
        $remaining = (float) $customer->balance;
        if ($remaining <= 0.005) return null;

        $date = null;
        foreach ($customer->sales()->whereIn('status', Sale::COUNTED)->where('due_amount', '>', 0)->latest()->get(['created_at', 'due_amount']) as $s) {
            $date = CarbonImmutable::parse($s->created_at);
            $remaining -= (float) $s->due_amount;
            if ($remaining <= 0.005) break;
        }

        return $date;
    }
}
