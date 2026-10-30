<?php

namespace Modules\User\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SignInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string'
        ];
    }


    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'errors'  => $validator->errors(),
        ], 422);

        throw new ValidationException($validator, $response);
    }
}
