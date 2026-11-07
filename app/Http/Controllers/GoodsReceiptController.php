<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public const PAYMENT_STATUSES = ['unpaid' => 'Non payé', 'partial' => 'Payé en partie', 'paid' => 'Payé'];

    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $query = GoodsReceipt::with('supplier:id,name', 'user:id,name')->withCount('items')
            ->when($request->query('fournisseur'), fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($request->query('paiement'), fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->query('du'), fn ($q, $d) => $q->whereDate('received_date', '>=', $d))
            ->when($request->query('au'), fn ($q, $d) => $q->whereDate('received_date', '<=', $d));

        return view('purchases.receipts.index', [
            'receipts' => (clone $query)->latest('received_date')->latest('id')->paginate(25)->withQueryString(),
            'totals' => ['total' => (float) (clone $query)->sum('total'), 'paid' => (float) (clone $query)->sum('paid_amount')],
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'statuses' => self::PAYMENT_STATUSES,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $order = $request->filled('commande') ? PurchaseOrder::findOrFail($request->integer('commande')) : null;
        if ($order && in_array($order->status, ['received', 'cancelled'], true)) {
            return redirect()->route('purchase-orders.show', $order)->with('error', 'Ce bon de commande est clôturé.');
        }

        return view('purchases.receipts.form', [
            'order' => $order,
            'supplierId' => $order?->supplier_id ?? ($request->integer('fournisseur') ?: null),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'lines' => $order ? $this->purchases->remainingLines($order) : [],
            'methods' => array_diff_key(SalePayment::METHODS, ['card' => 1]) + ['cheque' => 'Chèque'],
            'canPay' => $request->user()->can('supplier_payments.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:50'],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'transport_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'required_with:paid_amount', 'in:cash,moncash,natcash,bank,cheque'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:50'],
            'items.*.manufacture_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.expiry_date' => ['nullable', 'date', 'after:today'],
        ], [
            'items.required' => 'Ajoutez au moins un produit.',
            'items.*.expiry_date.after' => "Un produit reçu ne peut pas être déjà expiré (vérifiez la date).",
        ]);

        $attachment = $request->file('attachment')?->store('receptions', 'public');
        $receipt = $this->purchases->receive($data, $request->user(), $attachment);

        return redirect()->route('goods-receipts.show', $receipt)
            ->with('success', "Réception {$receipt->reference} enregistrée : le stock a été mis à jour.");
    }

    public function show(Request $request, GoodsReceipt $goodsReceipt): View
    {
        return view('purchases.receipts.show', [
            'receipt' => $goodsReceipt->load('supplier', 'user', 'purchaseOrder', 'items.product.baseUnit', 'items.productUnit.unit', 'items.batch', 'payments.user'),
            'methods' => array_diff_key(SalePayment::METHODS, ['card' => 1]) + ['cheque' => 'Chèque'],
            'statuses' => self::PAYMENT_STATUSES,
            'print' => $request->boolean('imprimer'),
        ]);
    }
}
