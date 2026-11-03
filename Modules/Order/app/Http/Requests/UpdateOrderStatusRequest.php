<?php

namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * التفويض يتم داخل OrderPolicy في Controller.
         * لذلك نسمح بمرور طلب التحقق إلى Controller.
         */
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                'in:pending,confirmed,processing,shipped,delivered,cancelled',
            ],
        ];
    }
}
