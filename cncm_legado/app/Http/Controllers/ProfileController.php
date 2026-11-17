<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('profile.index', compact('user'));
    }

    public function edit()
    {
        $user = Auth::user();

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request, User $usuario )
    {
        $request->validate([
            'nombres'           => 'required',
            'apellido_paterno'  => 'required',
            'apellido_materno'  => 'nullable',
            'celular'           => 'nullable',
        ]);

        $usuario->update($request->except('_token'));

        return redirect()->route('profile.index')->with([
            'message' => "Perfil actualizado correctamente"
        ]);
    }
}
