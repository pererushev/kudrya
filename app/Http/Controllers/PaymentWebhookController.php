<?php

namespace App\Http\Controllers;

use App\Domain\PaymentService;
use App\Http\Requests\PaymentWebhookRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class PaymentWebhookController extends Controller
{
    public function __invoke(PaymentWebhookRequest $request, PaymentService $payments): JsonResponse
    {
        $this->verifySignature($request);

        $result = $payments->handlePaidWebhook($request->validated());

        return response()->json($result);
    }

    private function verifySignature(PaymentWebhookRequest $request): void
    {
        $secret = config('commerce.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            return;
        }

        $provided = (string) $request->header('X-Signature', '');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $provided)) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid webhook signature.');
        }
    }
}
