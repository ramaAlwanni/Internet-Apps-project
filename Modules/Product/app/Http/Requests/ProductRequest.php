<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'price'       => 'required|decimal:10,2',
            'description' => 'nullable|string',
            'stock'       => 'required|integer|min:0',
            'photo'       => 'required|image|max:2048|in:jpeg,png,jpg',
            'category_id' => 'required|exists:categories,id',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    
    public function failedValidation(Validator $validator)
    {
        throw new ValidationException($validator);
    }
}
