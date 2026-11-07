<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\Setting;
use App\Services\CustomerService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customers) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        $list = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")
                ->orWhere('business_name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
            ->when($request->query('type'), fn ($w, $t) => $w->where('type', $t))
            ->when($request->query('filtre') === 'dette', fn ($w) => $w->where('balance', '>', 0))
            ->when($request->query('filtre') !== 'inactifs', fn ($w) => $w->where('is_active', true))
            ->when($request->query('filtre') === 'inactifs', fn ($w) => $w->where('is_active', false))
            ->withSum(['sales as purchases_total' => fn ($s) => $s->whereIn('status', Sale::COUNTED)], 'total')
            ->orderByDesc('balance')->orderBy('name')
            ->paginate(25)->withQueryString();

        $list->getCollection()->each(fn (Customer $c) => $c->setAttribute('oldest_debt', $this->customers->oldestUnpaidDate($c)));

        return view('customers.index', [
            'customers' => $list,
            'totalDebt' => (float) Customer::where('balance', '>', 0)->sum('balance'),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer(['type' => 'retail', 'is_active' => true, 'credit_limit' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));
        if ((float) $request->input('opening_balance') > 0) {
            $this->customers->setOpeningBalance($customer, (float) $request->input('opening_balance'));
        }

        return redirect()->route('customers.show', $customer)->with('success', 'Client ajouté.');
    }

    public function show(Customer $customer): View
    {
        $since = now()->subDays(90);

        return view('customers.show', [
            'customer' => $customer,
            'oldest' => $this->customers->oldestUnpaidDate($customer),
            'sales' => $customer->sales()->whereIn('status', [...Sale::COUNTED, Sale::CANCELLED])->with('cashier:id,name')
                ->latest()->paginate(15),
            'payments' => $customer->payments()->with('user:id,name')->latest()->limit(15)->get(),
            'stats' => [
                'total' => (float) $customer->sales()->whereIn('status', Sale::COUNTED)->sum('total'),
                'last90' => (float) $customer->sales()->whereIn('status', Sale::COUNTED)->where('created_at', '>=', $since)->sum('total'),
                'count' => $customer->sales()->whereIn('status', Sale::COUNTED)->count(),
                'last' => $customer->sales()->whereIn('status', Sale::COUNTED)->max('created_at'),
            ],
            'methods' => SalePayment::METHODS,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request, $customer));

        return redirect()->route('customers.show', $customer)->with('success', 'Client mis à jour.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ((float) $customer->balance > 0) {
            return back()->with('error', 'Ce client doit encore de l\'argent : impossible de le supprimer.');
        }
        if ($customer->sales()->exists()) {
            $customer->update(['is_active' => false]);

            return redirect()->route('customers.index')->with('info', 'Client désactivé (il a un historique d\'achats).');
        }
        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Client supprimé.');
    }

    public function pay(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys(SalePayment::METHODS))],
            'currency' => ['required', 'in:HTG,USD'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->customers->pay($customer, (float) $data['amount'], $data['method'], $request->user(),
            $data['currency'], $data['reference'] ?? null, $data['notes'] ?? null);

        return redirect()->route('customers.show', $customer)
            ->with('success', "Paiement {$payment->reference} enregistré.")
            ->with('print_payment', $payment->id);
    }

    public function paymentReceipt(Request $request, CustomerPayment $payment): View
    {
        return view('customers.payment-receipt', [
            'payment' => $payment->load('customer', 'user'),
            'store' => ['name' => Setting::get('store_name', config('app.name')), 'phone' => Setting::get('store_phone'), 'address' => Setting::get('store_address')],
            'autoPrint' => $request->boolean('print'),
        ]);
    }

    public function statement(Request $request, Customer $customer): View
    {
        $from = CarbonImmutable::parse($request->query('du', now()->subMonths(3)->toDateString()))->startOfDay();
        $to = CarbonImmutable::parse($request->query('au', today()->toDateString()))->endOfDay();

        return view('customers.statement', [
            'customer' => $customer,
            'from' => $from,
            'to' => $to,
            'statement' => $this->customers->statement($customer, $from, $to),
            'store' => ['name' => Setting::get('store_name', config('app.name')), 'phone' => Setting::get('store_phone'), 'address' => Setting::get('store_address'), 'nif' => Setting::get('store_nif')],
        ]);
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:retail,wholesale'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:50'],
            'credit_limit' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => [$customer ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
        ]);
        unset($data['opening_balance']);

        $data['credit_allowed'] = $request->boolean('credit_allowed');
        $data['is_active'] = $request->boolean('is_active', true);
        // Seul l'ADM accorde le crédit et fixe la limite
        if (! $request->user()->can('settings.manage')) {
            if ($customer) {
                unset($data['credit_limit'], $data['credit_allowed']);
            } else {
                $data['credit_allowed'] = false;
                $data['credit_limit'] = 0;
            }
        }

        return $data;
    }
}
