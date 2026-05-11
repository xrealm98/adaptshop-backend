<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Cargamos los pedidos del usuario encadenando la relación del pedido con los productos.
        $query = Order::with(['items.product', 'user']);

        if ($request->user()->role !== 'admin') {
            $query->where('user_id', $request->user()->id);
        }
        $query->orderBy('created_at', 'desc');

        if ($request->hasAny(['page', 'per_page'])) {
            $perPage = $request->input('per_page', 10);

            return response()->json($query->paginate($perPage));
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'payment_intent_id'    => 'required|string',
            'shipping_street'      => 'required|string',
            'shipping_city'        => 'required|string',
            'shipping_province'    => 'required|string',
            'shipping_postal_code' => 'required|string',
            'shipping_country'     => 'required|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);
        Stripe::setApiKey(config('services.stripe.secret'));
        $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

        if ($paymentIntent->status !== 'succeeded') {
            return response()->json([
                'message' => 'El pago no se ha completado correctamente'
            ], 422);
        }

        if ($paymentIntent->metadata->user_id != $request->user()->id) {
            return response()->json([
                'message' => 'El pago no corresponde al usuario autenticado'
            ], 403);
        }

        $alreadyPaid = Order::where('payment_id', $request->payment_intent_id)->exists();
        if ($alreadyPaid) {
            return response()->json([
                'message' => 'Este pago ya ha sido realizado'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $paymentIntent) {
                //Obtener los IDs del array items (items.*.product_id).
                $productIds = collect($request->items)->pluck('product_id');

                // Obtener los productos de la db y se organiza en una colección indexada por el ID.
                $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
                $total = 0;

                // Se calcula el total real de los productos
                foreach ($request->items as $item) {

                    // Se compara el producto del pedido ($item) con el producto de la base de datos ($products) para obtener el producto correspondiente.
                    $product = $products[$item['product_id']];

                    // Verificar el stock del producto
                    if ($product->stock < $item['quantity']) {
                        throw new \Exception("Stock insuficiente del producto: {$product->name}");
                    }

                    $total += $product->price * $item['quantity'];
                }

                $expectedAmount = (int) round($total * 100);
                if ($paymentIntent->amount !== $expectedAmount) {
                    throw new \Exception("El importe del pago no coincide con el total del pedido");
                }

                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'status' => 'paid',
                    'total' => $total,
                    'payment_id' => $request->payment_intent_id,
                    'shipping_street' => $request->shipping_street,
                    'shipping_city' => $request->shipping_city,
                    'shipping_province' => $request->shipping_province,
                    'shipping_postal_code' => $request->shipping_postal_code,
                    'shipping_country' => $request->shipping_country,
                ]);

                foreach ($request->items as $item) {
                    $product = $products[$item['product_id']];

                    // Se crea la relación entre el pedido y los productos a través de la tabla pivot (order_items) utilizando el método items() del modelo Order.
                    $order->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_price' => $product->price,
                    ]);
                    // Se descuenta el stock del producto
                    $product->decrement('stock', $item['quantity']);
                }
                return response()->json($order->load('items.product'), 201);
            });
        } catch (\Exception $e) {
            Log::error("Error en store: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order)
    {
        // Verificar que el pedido pertenece al usuario que lo solicita
        if ($request->user()->role !== 'admin' && $order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No tienes permiso para ver este pedido'
            ], 403);
        }
        return response()->json($order->load('items.product', 'user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:pending,paid,shipped,delivered,cancelled',
            'shipping_street' => 'sometimes|string',
            'shipping_city' => 'sometimes|string',
            'shipping_province' => 'sometimes|string',
            'shipping_postal_code' => 'sometimes|string',
            'shipping_country' => 'sometimes|string',
        ]);

        $order->update($validated);

        return response()->json($order->load(['items.product', 'user']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
