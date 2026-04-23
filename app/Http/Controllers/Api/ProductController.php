<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->has('latest')) {
            $query->latest();
        }

        if ($request->has('limit')) {
            return response()->json($query->limit($request->limit)->get());
        }
        $perPage = $request->input('per_page', 12);

        return response()->json($query->paginate($perPage));
    }

    // Para verificar la información del producto del frontend y backend
    public function getProductsIds(Request $request)
    {
        $request->validate(['ids' => 'required|array']);
        $products = Product::whereIn('id', $request->ids)->get();
        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $fields = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => 'required|string',
            'details' => 'nullable|string|max:500',
            'description' => 'required|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'image'       => 'nullable|string',
            'is_active'   => 'boolean',
        ]);
        $fields['slug'] = Str::slug($request->name);
        $originalSlug = $fields['slug'];
        $count = 1;

        while (Product::where('slug', $fields['slug'])->exists()) {
            $fields['slug'] = $originalSlug . '-' . $count++;
        }
        $product = Product::create($fields);


        return response()->json($product->load('category'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($identifier)
    {
        $product = Product::with('category');

        if (is_numeric($identifier)) {
            $product = $product->where('id', $identifier);
        } else {
            $product = $product->where('slug', $identifier);
        }

        return response()->json($product->firstOrFail());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        $fields = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name'        => 'sometimes|string',
            'details' => 'nullable|string|max:500',
            'description' => 'sometimes|string',
            'price'       => 'sometimes|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'image'       => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);

        if (isset($fields['name'])) {
            $slug = Str::slug($fields['name']);
            $originalSlug = $slug;
            $count = 1;

            while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            $fields['slug'] = $slug;
        }

        $product->update($fields);

        return response()->json($product->load('category'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json([
            'message' => 'Producto eliminado correctamente'
        ]);
    }
}
