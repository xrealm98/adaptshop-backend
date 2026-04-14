<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;


class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(User::all());
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return response()->json($user->load('orders'));
    }

    public function showProfile(Request $request)
    {
        $user = $request->user()->load('orders.items.product');

        return response()->json(
            $user->makeHidden(['role', 'is_blocked', 'created_at', 'updated_at'])
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string',
            'last_name'  => 'sometimes|string',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'      => 'sometimes|nullable|string',
            'street'     => 'sometimes|nullable|string',
            'city'       => 'sometimes|nullable|string',
            'province'   => 'sometimes|nullable|string',
            'postal_code' => 'sometimes|nullable|string',
            'country'    => 'sometimes|nullable|string',
            'role'       => 'sometimes|in:client,admin',
            'is_blocked' => 'sometimes|boolean',
        ]);

        if ($request->has('role') && $user->id === $request->user()->id && $request->role !== 'admin') {
            return response()->json([
                'message' => 'No puedes quitarte el rol de administrador a ti mismo.'
            ], 403);
        }

        $user->update($validated);

        return response()->json($user);
    }
    // Editar solo datos permitidos del perfil del usuario.
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'first_name' => 'sometimes|string',
            'last_name'  => 'sometimes|string',
            'email'      => 'sometimes|email|unique:users,email,' . $user->id,
            'phone'      => 'sometimes|nullable|string',
            'street'     => 'sometimes|nullable|string',
            'city'       => 'sometimes|nullable|string',
            'province'   => 'sometimes|nullable|string',
            'postal_code' => 'sometimes|nullable|string',
            'country'    => 'sometimes|nullable|string',
        ]);

        $user->update($data);

        return response()->json(
            $user->makeHidden(['role', 'is_blocked', 'created_at', 'updated_at'])
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente'
        ]);
    }
}
