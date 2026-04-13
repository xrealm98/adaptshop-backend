<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Cargamos los pedidos del usuario encadenando la relación del pedido con los productos.
        $orders = Order::with(['items.product', 'user']);
        if ($request->user()->role !== 'admin') {
            $orders->where('user_id', $request->user()->id);
        }

        return response()->json($orders->orderBy('created_at', 'desc')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $request->validate([
            'shipping_street'      => 'required|string',
            'shipping_city'        => 'required|string',
            'shipping_province'    => 'required|string',
            'shipping_postal_code' => 'required|string',
            'shipping_country'     => 'required|string',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        //Obtener los IDs del array items (items.*.product_id).
        $productIds = collect($request->items)->pluck('product_id');

        // Obtener los productos de la db y se organiza en una colección indexada por el ID.
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $total = 0;

        foreach ($request->items as $item) {

            // Se compara el producto del pedido ($item) con el producto de la base de datos ($products) para obtener el producto correspondiente.
            $product = $products[$item['product_id']];

            // Verificar el stock del producto
            if ($product->stock < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuficiente del producto: {$product->name}"
                ], 422);
            }

            $total += $product->price * $item['quantity'];
        }

        $order = Order::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'total' => $total,
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
