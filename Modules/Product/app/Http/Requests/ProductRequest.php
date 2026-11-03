<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0|max:99999999.99',
            'description' => 'nullable|string',
            'stock'       => 'required|integer|min:0',
            'photo'       => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'category_id' => 'required|integer|exists:categories,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'The product name is required.',
            'price.required'       => 'The product price is required.',
            'price.numeric'        => 'The price must be a number.',
            'price.min'            => 'The price cannot be negative.',
            'stock.required'       => 'The stock quantity is required.',
            'stock.integer'        => 'The stock must be a whole number.',
            'stock.min'            => 'The stock cannot be negative.',
            'photo.required'       => 'The product photo is required.',
            'photo.image'          => 'The photo must be an image file.',
            'photo.mimes'          => 'The photo must be a jpeg, jpg or png file.',
            'photo.max'            => 'The photo must not be larger than 2 MB.',
            'category_id.required' => 'The category is required.',
            'category_id.exists'   => 'The selected category does not exist.',
        ];
    }
}

// namespace Modules\Product\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

// class ProductRequest extends FormRequest
// {
//     /**
//      * Get the validation rules that apply to the request.
//      */
//     public function rules(): array
//     {
//         return [
//             'name'        => 'required|string|max:255',
//             'price'       => 'required|decimal:10,2',
//             'description' => 'nullable|string',
//             'stock'       => 'required|integer|min:0',
//             'photo'       => 'required|image|max:2048|in:jpeg,png,jpg',
//             'category_id' => 'required|exists:categories,id',
//         ];
//     }

//     /**
//      * Determine if the user is authorized to make this request.
//      */
//     public function authorize(): bool
//     {
//         return true;
//     }
// }
