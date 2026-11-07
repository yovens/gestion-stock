<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessException;
use App\Models\CashSession;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ReferenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $from = CarbonImmutable::parse($request->query('du', now()->startOfMonth()->toDateString()));
        $to = CarbonImmutable::parse($request->query('au', today()->toDateString()));

        $query = Expense::whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->query('categorie'), fn ($q, $c) => $q->where('expense_category_id', $c));

        return view('expenses.index', [
            'expenses' => (clone $query)->with('category', 'user:id,name')->latest('expense_date')->latest('id')->paginate(30)->withQueryString(),
            'byCategory' => (clone $query)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
                ->selectRaw('expense_categories.name, SUM(expenses.amount) as total')->groupBy('expense_categories.name')
                ->orderByDesc('total')->get(),
            'total' => (float) (clone $query)->sum('amount'),
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'from' => $from, 'to' => $to,
            'hasSession' => (bool) CashSession::openFor($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expense_category_id' => ['required_without:new_category', 'nullable', 'exists:expense_categories,id'],
            'new_category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,moncash,natcash,bank,cheque'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'from_register' => ['boolean'],
        ]);

        if (! empty($data['new_category'])) {
            $data['expense_category_id'] = ExpenseCategory::firstOrCreate(['name' => trim($data['new_category'])])->id;
        }

        $session = null;
        if ($request->boolean('from_register')) {
            $session = CashSession::openFor($request->user());
            if (! $session || $data['method'] !== 'cash') {
                throw new BusinessException('Pour payer depuis la caisse : ouvrez votre caisse et choisissez « Espèces ».');
            }
        }

        Expense::create([
            'reference' => ReferenceGenerator::next('DEP'),
            'expense_category_id' => $data['expense_category_id'],
            'cash_session_id' => $session?->id,
            'description' => $data['description'],
            'amount' => round((float) $data['amount'], 2),
            'method' => $data['method'],
            'expense_date' => $data['expense_date'],
            'attachment' => $request->file('attachment')?->store('depenses', 'public'),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Dépense enregistrée.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        if (! $expense->created_at->isToday() && ! $request->user()->can('settings.manage')) {
            return back()->with('error', 'Seul l\'administrateur peut supprimer une dépense des jours précédents.');
        }
        if ($expense->cashSession && ! $expense->cashSession->isOpen()) {
            return back()->with('error', 'Cette dépense fait partie d\'une caisse déjà fermée.');
        }
        $expense->delete();

        return back()->with('success', 'Dépense supprimée.');
    }
}
