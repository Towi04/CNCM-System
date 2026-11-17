<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\EnviarMensajeSoporte;
use Illuminate\Http\Request;

class SoporteController extends Controller
{
    public function index()
    {
        return view('soporte.index');
    }

    public function enviar_correo_soporte(Request $request)
    {
        $rules = [
            'mensaje'   => 'required',
            'nombre'    => 'required',
            'email'     => 'required'
        ];

        $this->validate($request, $rules);

        try {
            $remitente = new User();
            $remitente->email = config('settings.company.soporte_email');
            $remitente->notify(new EnviarMensajeSoporte($request->nombre, $request->email, $request->mensaje));

        } catch (\Throwable $th) {
            return redirect()
                ->back()
                ->withInput()
                ->with(['error' => $th->getMessage()]);
        }

        return redirect()->route('soporte.index')->with(['message' => 'Tu mensaje fue enviado con éxito']);
    }
}
