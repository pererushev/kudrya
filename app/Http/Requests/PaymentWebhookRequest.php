<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:128'],
            'order_id' => ['required', 'uuid'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', 'in:paid'],
        ];
    }
}
