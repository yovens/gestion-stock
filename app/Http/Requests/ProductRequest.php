<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('product') ? 'products.edit' : 'products.create');
    }

    protected function prepareForValidation(): void
    {
        $units = collect($this->input('units', []))
            ->filter(fn ($u) => ! empty($u['unit_id']))
            ->map(fn ($u) => [...$u, 'barcode' => trim((string) ($u['barcode'] ?? '')) ?: null])
            ->values()->all();

        $this->merge([
            'barcode' => trim((string) $this->input('barcode')) ?: null,
            'sku' => mb_strtoupper(trim((string) $this->input('sku'))),
            'track_expiry' => $this->boolean('track_expiry'),
            'is_active' => $this->boolean('is_active'),
            'units' => $units,
        ]);
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $id = $product?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:64'],
            'category_id' => ['required', 'exists:categories,id'],
            'brand_name' => ['nullable', 'string', 'max:100'],
            'base_unit_id' => ['required', 'exists:units,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:3072'],
            'remove_image' => ['boolean'],

            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'retail_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'lte:retail_price'],
            'wholesale_min_qty' => ['nullable', 'numeric', 'gt:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'min_stock' => ['required', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'gte:min_stock'],
            'location' => ['nullable', 'string', 'max:100'],
            'track_expiry' => ['boolean'],
            'expiry_alert_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_active' => ['boolean'],

            'units' => ['array', 'max:6'],
            'units.*.id' => ['nullable', 'integer', Rule::exists('product_units', 'id')->where('product_id', $id ?? 0)],
            'units.*.unit_id' => ['required', 'distinct', 'exists:units,id', 'different:base_unit_id'],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0', 'not_in:1'],
            'units.*.barcode' => ['nullable', 'string', 'max:64', 'distinct'],
            'units.*.retail_price' => ['required', 'numeric', 'min:0'],
            'units.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.wholesale_min_qty' => ['nullable', 'numeric', 'gt:0'],
            'units.*.is_default_sale_unit' => ['boolean'],

            // Stock initial : uniquement à la création
            'opening_quantity' => [$product ? 'prohibited' : 'nullable', 'numeric', 'min:0'],
            'opening_unit_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_expiry' => ['nullable', 'date', 'after:today'],
            'opening_batch' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $productId = $this->route('product')?->id ?? 0;
            $codes = collect([$this->input('barcode')])
                ->merge(collect($this->input('units', []))->pluck('barcode'))
                ->filter()->values();

            if ($codes->count() !== $codes->unique()->count()) {
                $v->errors()->add('barcode', 'Le même code-barres est utilisé deux fois sur ce produit.');
                return;
            }
            if ($codes->isEmpty()) return;

            $taken = Product::withTrashed()->whereIn('barcode', $codes)->where('id', '!=', $productId)->value('name')
                ?? ProductUnit::whereIn('barcode', $codes)->where('product_id', '!=', $productId)->with('product:id,name')->first()?->product?->name;

            if ($taken) {
                $v->errors()->add('barcode', "Un code-barres est déjà utilisé par « {$taken} ».");
            }

            if ($this->boolean('track_expiry') && (float) $this->input('opening_quantity') > 0 && ! $this->input('opening_expiry')) {
                $v->errors()->add('opening_expiry', "Indiquez la date d'expiration du stock initial.");
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom', 'sku' => 'code interne', 'barcode' => 'code-barres', 'category_id' => 'catégorie',
            'base_unit_id' => 'unité de base', 'retail_price' => 'prix détail', 'wholesale_price' => 'prix gros',
            'wholesale_min_qty' => 'quantité minimum gros', 'min_stock' => 'stock minimum', 'max_stock' => 'stock maximum',
            'units.*.unit_id' => 'conditionnement', 'units.*.conversion_factor' => 'contenance',
            'units.*.retail_price' => 'prix détail du conditionnement', 'units.*.barcode' => 'code-barres du conditionnement',
            'opening_quantity' => 'stock initial', 'opening_expiry' => "date d'expiration",
        ];
    }

    public function messages(): array
    {
        return [
            'units.*.unit_id.different' => "Un conditionnement ne peut pas être l'unité de base.",
            'units.*.unit_id.distinct' => 'Ce conditionnement est en double.',
            'units.*.conversion_factor.not_in' => 'La contenance doit être différente de 1.',
            'wholesale_price.lte' => 'Le prix gros doit être inférieur ou égal au prix détail.',
            'opening_quantity.prohibited' => 'Le stock se modifie par une réception ou un ajustement, pas ici.',
        ];
    }
}
