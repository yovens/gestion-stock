<?php

namespace App\Http\Requests;

use App\Models\SalePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pos.access');
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_type' => ['nullable', Rule::in(['retail', 'wholesale'])],
            'hold' => ['boolean'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['nullable', 'integer', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['array', 'max:6'],
            'payments.*.method' => ['required', Rule::in(array_keys(SalePayment::METHODS))],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.currency' => ['nullable', Rule::in(['HTG', 'USD'])],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Le panier est vide.',
            'items.min' => 'Le panier est vide.',
            'items.*.quantity.gt' => 'La quantité doit être supérieure à zéro.',
        ];
    }
}
