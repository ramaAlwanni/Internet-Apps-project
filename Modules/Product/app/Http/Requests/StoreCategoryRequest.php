<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
       // return [];
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                // بالتعديل بتتجاهل الصنف الحالي حتى ما يعتبر اسمه مكرر
                Rule::unique('categories', 'name')->ignore($this->route('category')),
            ],
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
     public function messages(): array
    {
        return [
            'name.required' => 'The category name is required.',
            'name.string'   => 'The category name must be text.',
            'name.max'      => 'The category name must not exceed 100 characters.',
            'name.unique'   => 'A category with this name already exists.',
        ];
    }
}
