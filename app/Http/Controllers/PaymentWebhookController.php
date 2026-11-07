<?php

namespace App\Http\Controllers;

use App\Payments\InvalidSignatureException;
use App\Payments\PaymentGateway;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGateway $gateway, PaymentService $payments): JsonResponse
    {
        try {
            $event = $gateway->parseWebhook($request);
        } catch (InvalidSignatureException $e) {
            Log::warning('Callback gateway ditolak', ['ip' => $request->ip(), 'reason' => $e->getMessage()]);

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $result = $payments->handleWebhook($event);

        return response()->json(['result' => $result]);
    }
}
