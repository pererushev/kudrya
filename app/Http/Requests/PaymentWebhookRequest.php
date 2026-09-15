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
            'order_id' => ['required', 'string', 'max:64'],
            'status' => ['required', 'string', 'in:paid,failed'],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:8'],
            'created_at' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('status') !== 'paid') {
                return;
            }

            if ($this->input('amount') === null && $this->input('amount_cents') === null) {
                $validator->errors()->add('amount', 'amount or amount_cents is required.');
            }
        });
    }
}
