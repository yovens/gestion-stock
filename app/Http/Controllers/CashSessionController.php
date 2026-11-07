<?php

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Services\CashSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashSessionController extends Controller
{
    public function __construct(private CashSessionService $service) {}

    public function create(Request $request): View|RedirectResponse
    {
        if (CashSession::openFor($request->user())) {
            return redirect()->route('pos.index');
        }

        $registers = CashRegister::where('is_active', true)
            ->whereDoesntHave('sessions', fn ($q) => $q->where('status', 'open'))
            ->orderBy('name')->get();

        return view('cash.open', compact('registers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'opening_amount_htg' => ['required', 'numeric', 'min:0'],
            'opening_amount_usd' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->service->open(
            $request->user(),
            CashRegister::findOrFail($data['cash_register_id']),
            (float) $data['opening_amount_htg'],
            (float) ($data['opening_amount_usd'] ?? 0),
        );

        return redirect()->route('pos.index')->with('success', 'Caisse ouverte. Bonnes ventes !');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $session = CashSession::openFor($request->user());
        if (! $session) {
            return redirect()->route('cash.open');
        }

        return view('cash.close', [
            'session' => $session->load('register'),
            'summary' => $this->service->summary($session),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $session = CashSession::openFor($request->user());
        abort_unless($session, 404);

        $data = $request->validate([
            'counted_amount_htg' => ['required', 'numeric', 'min:0'],
            'counted_amount_usd' => ['nullable', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $session = $this->service->close(
            $session,
            (float) $data['counted_amount_htg'],
            (float) ($data['counted_amount_usd'] ?? 0),
            $request->user(),
            $data['closing_notes'] ?? null,
        );

        return redirect()->route('cash.report', ['session' => $session, 'print' => 1]);
    }

    public function report(Request $request, CashSession $session): View
    {
        $user = $request->user();
        abort_unless((int) $session->user_id === (int) $user->id || $user->can('cash.view_all'), 403);

        return view('cash.report', [
            'session' => $session->load('register', 'user'),
            'summary' => $this->service->summary($session),
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
