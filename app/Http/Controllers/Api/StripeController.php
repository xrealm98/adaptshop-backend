<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        // Se calcula el total real desde la DB
        $productIds = collect($request->items)->pluck('product_id');
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $total = 0;
        foreach ($request->items as $item) {
            $product = $products[$item['product_id']];
            $total += $product->price * $item['quantity'];
        }

        // Configurar Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // Crear intento de pago real en los servidores de Stripe
            $paymentIntent = PaymentIntent::create([
                'amount'   => (int) round($total * 100),
                'currency' => 'eur',
                'metadata' => [
                    'user_id' => $request->user()->id,

                ],
            ]);

            return response()->json([
                'client_secret' => $paymentIntent->client_secret,
                'total'         => $total,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
