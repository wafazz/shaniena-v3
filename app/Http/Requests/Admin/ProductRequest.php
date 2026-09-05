<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $productId = $this->route('product')?->id;
        $isVariable = $this->input('type') === Product::TYPE_VARIABLE;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('products', 'slug')->ignore($productId)],
            'description' => ['nullable', 'string'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],

            // Grams and millimetres, integers — the source's own units.
            'weight' => ['required', 'integer', 'min:1'],
            'length' => ['required', 'integer', 'min:1'],
            'width' => ['required', 'integer', 'min:1'],
            'height' => ['required', 'integer', 'min:1'],

            'type' => ['required', Rule::in([Product::TYPE_SIMPLE, Product::TYPE_VARIABLE])],
            'price_capital' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'boolean'],

            'variants' => ['required', 'array', 'min:1'],
            'variants.*.id' => ['nullable', 'integer'],
            // A simple product still has one row behind it; only a variable
            // product needs the operator to name each one.
            'variants.*.variant_name' => [$isVariable ? 'required' : 'nullable', 'string', 'max:255'],
            // product_variants.sku carries a UNIQUE index, and a soft-deleted
            // variant still holds its SKU — without this a clash is a 500.
            'variants.*.sku' => ['required', 'string', 'max:255', 'distinct'],
            'variants.*.price_retail' => ['required', 'numeric', 'min:0'],
            'variants.*.price_sale' => ['required', 'numeric', 'min:0'],
            'variants.*.max_purchase' => ['required', 'integer', 'min:1'],

            'prices' => ['array'],
            'prices.*.market_price' => ['required', 'numeric', 'min:0'],
            'prices.*.sale_price' => ['required', 'numeric', 'min:0'],

            // The source capped at 5 in a browser alert(); enforced here.
            'images' => ['array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'keep_images' => ['array'],
            'keep_images.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'variants.*.variant_name.required' => 'Name every variant, or switch the product back to Simple.',
            'variants.*.sku.required' => 'Every variant needs a SKU.',
            'images.max' => 'Up to 5 images per product.',
            'variants.*.sku.distinct' => 'Two variants share a SKU. Each one must be unique.',
        ];
    }
}
