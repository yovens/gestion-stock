<?php

namespace App\Http\Controllers;

use App\Models\SalePayment;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));

        return view('suppliers.index', [
            'suppliers' => Supplier::query()
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_person', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")))
                ->when($request->query('filtre') === 'dette', fn ($w) => $w->where('balance', '>', 0))
                ->withSum('goodsReceipts as purchases_total', 'total')
                ->withMax('goodsReceipts as last_receipt', 'received_date')
                ->orderByDesc('balance')->orderBy('name')
                ->paginate(25)->withQueryString(),
            'totalDebt' => (float) Supplier::where('balance', '>', 0)->sum('balance'),
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'payment_terms_days' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $supplier = new Supplier($data);
        // Dette existante au moment de la mise en service
        $supplier->balance = round((float) $request->input('opening_balance', 0), 2);
        $supplier->save();

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fournisseur ajouté.');
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', [
            'supplier' => $supplier,
            'receipts' => $supplier->goodsReceipts()->latest('received_date')->latest('id')->paginate(10, ['*'], 'rec'),
            'unpaid' => $supplier->goodsReceipts()->where('payment_status', '!=', 'paid')->orderBy('received_date')->get(),
            'payments' => $supplier->payments()->with('goodsReceipt:id,reference', 'user:id,name')->latest('payment_date')->latest('id')->limit(15)->get(),
            'orders' => $supplier->purchaseOrders()->whereIn('status', ['draft', 'sent', 'partially_received'])->latest()->get(),
            'stats' => [
                'total' => (float) $supplier->goodsReceipts()->sum('total'),
                'year' => (float) $supplier->goodsReceipts()->whereYear('received_date', now()->year)->sum('total'),
                'paid' => (float) $supplier->payments()->sum('amount'),
            ],
            'methods' => array_diff_key(SalePayment::METHODS, ['card' => 1]) + ['cheque' => 'Chèque'],
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->goodsReceipts()->exists() || $supplier->purchaseOrders()->exists()) {
            $supplier->update(['is_active' => false]);

            return redirect()->route('suppliers.index')->with('info', 'Ce fournisseur a un historique : il a été désactivé au lieu d\'être supprimé.');
        }
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Fournisseur supprimé.');
    }

    public function pay(Request $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', 'in:cash,moncash,natcash,bank,cheque'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'goods_receipt_id' => ['nullable', 'exists:goods_receipts,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $receipt = ! empty($data['goods_receipt_id']) ? $supplier->goodsReceipts()->find($data['goods_receipt_id']) : null;

        $this->purchases->pay($supplier, (float) $data['amount'], $data['method'], $request->user(),
            $data['reference'] ?? null, $data['payment_date'], $receipt, $data['notes'] ?? null);

        return back()->with('success', 'Paiement de '.number_format((float) $data['amount'], 2, ',', ' ').' HTG enregistré.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone_2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'nif' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'opening_balance' => [$supplier ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
        ]);
        unset($data['opening_balance']);
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
