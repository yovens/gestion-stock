<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CashSessionService
{
    public function open(User $user, CashRegister $register, float $openingHtg, float $openingUsd = 0): CashSession
    {
        return DB::transaction(function () use ($user, $register, $openingHtg, $openingUsd) {
            $register = CashRegister::lockForUpdate()->findOrFail($register->id);

            if (! $register->is_active) {
                throw new BusinessException('Cette caisse est désactivée.');
            }
            if (CashSession::openFor($user)) {
                throw new BusinessException('Vous avez déjà une caisse ouverte.');
            }
            if ($register->openSession()) {
                throw new BusinessException('Cette caisse est déjà utilisée par un autre caissier.');
            }

            return CashSession::create([
                'cash_register_id' => $register->id,
                'user_id' => $user->id,
                'opened_at' => now(),
                'opening_amount_htg' => round($openingHtg, 2),
                'opening_amount_usd' => round($openingUsd, 2),
                'status' => 'open',
            ]);
        });
    }

    /**
     * Ce qui DEVRAIT être dans le tiroir (espèces uniquement).
     *
     * @return array<string, float>
     */
    public function summary(CashSession $session): array
    {
        $sales = $session->sales()->whereIn('status', Sale::COUNTED);
        $saleIds = (clone $sales)->pluck('id');

        $cashPayments = DB::table('sale_payments')->whereIn('sale_id', $saleIds)->where('method', 'cash');
        $cashHtg = (float) (clone $cashPayments)->where('currency', 'HTG')->sum('amount');
        $cashUsd = (float) (clone $cashPayments)->where('currency', 'USD')->sum('amount');
        $change = (float) (clone $sales)->sum('change_amount'); // monnaie rendue en HTG

        $credit = $session->customerPayments()->where('method', 'cash');
        $creditHtg = (float) (clone $credit)->where('currency', 'HTG')->sum('amount');
        $creditUsd = (float) (clone $credit)->where('currency', 'USD')->sum('amount');

        $mv = fn (string $type, string $cur) => (float) $session->cashMovements()->where('type', $type)->where('currency', $cur)->sum('amount');
        $expenses = (float) $session->expenses()->where('method', 'cash')->sum('amount');
        $refunds = (float) $session->saleReturns()->where('refund_method', 'cash')->sum('total');

        $byMethod = DB::table('sale_payments')->whereIn('sale_id', $saleIds)
            ->selectRaw('method, SUM(amount_htg) as total')->groupBy('method')->pluck('total', 'method')
            ->map(fn ($v) => (float) $v)->all();

        $expectedHtg = (float) $session->opening_amount_htg + $cashHtg - $change + $creditHtg
            + $mv('in', 'HTG') - $mv('out', 'HTG') - $expenses - $refunds;
        $expectedUsd = (float) $session->opening_amount_usd + $cashUsd + $creditUsd
            + $mv('in', 'USD') - $mv('out', 'USD');

        return [
            'sales_count' => (clone $sales)->count(),
            'sales_total' => round((float) (clone $sales)->sum('total'), 2),
            'credit_sales' => round((float) (clone $sales)->sum('due_amount'), 2),
            'by_method' => $byMethod,
            'expenses' => round($expenses, 2),
            'refunds' => round($refunds, 2),
            'expected_htg' => round($expectedHtg, 2),
            'expected_usd' => round($expectedUsd, 2),
        ];
    }

    public function close(CashSession $session, float $countedHtg, float $countedUsd, User $closer, ?string $notes = null): CashSession
    {
        return DB::transaction(function () use ($session, $countedHtg, $countedUsd, $closer, $notes) {
            $session = CashSession::lockForUpdate()->findOrFail($session->id);
            if (! $session->isOpen()) {
                throw new BusinessException('Cette caisse est déjà fermée.');
            }
            if ((int) $session->user_id !== (int) $closer->id && ! $closer->can('cash.view_all')) {
                throw new BusinessException('Vous ne pouvez fermer que votre propre caisse.');
            }
            if ($session->sales()->where('status', Sale::HELD)->exists()) {
                throw new BusinessException('Finalisez ou supprimez les ventes en attente avant de fermer.');
            }

            $s = $this->summary($session);

            $session->update([
                'closed_at' => now(),
                'expected_amount_htg' => $s['expected_htg'],
                'expected_amount_usd' => $s['expected_usd'],
                'counted_amount_htg' => round($countedHtg, 2),
                'counted_amount_usd' => round($countedUsd, 2),
                'difference_htg' => round($countedHtg - $s['expected_htg'], 2),
                'difference_usd' => round($countedUsd - $s['expected_usd'], 2),
                'status' => 'closed',
                'closing_notes' => $notes,
                'closed_by' => $closer->id,
            ]);

            return $session;
        });
    }
}
