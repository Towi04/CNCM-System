<?php

namespace App\Http\Controllers;

use App\Models\Abono;
use App\Models\AlumnoPago;
use App\Models\Pago;
use Illuminate\Http\Request;

class AbonosController extends Controller
{
    public function actualizar_informacion_xeditable(Request $request)
    {
        $request->validate([
            'value' => 'required',
            'pk'    => 'required',
            'name'  => 'required',
        ]);

        $abono = Abono::findOrFail($request->pk);

        # 👉 ACTUALIZAR SEGUN EL CASO
        switch ($request->name) {
            case 'monto':
                $abono[$request->name] = $request->value;
                $abono->save();
                $abono->load(['pago','alumno_pago']);

                $abono->pago->update([
                    'monto' => $request->value
                ]);

                $abono->alumno_pago->update([
                    'monto' => $request->value
                ]);

                return response()->json([
                    'abono' => $abono
                ]);
            break;

            case 'id_alumno':
                # 👉 ACTUALIZAR ALUNO ATRAVEZ DEL ABONO
                $abono->pago->update([
                    'id_alumno' => $request->value
                ]);

                $abono->alumno_pago->update([
                    'id_alumno' => $request->value
                ]);

                $abono->load(['pago.alumno']);

                return response()->json([
                    'abono'     => $abono,
                    'alumno'    => $abono->pago->alumno->full_name
                ]);
            break;

            default:
                # 👉 COMPORTAMIENTO NORMAL DEL XEDITABLE
                $abono[$request->name] = $request->value;
                $abono->save();

                return response()->json([
                    'abono'     => $abono,
                ]);
            break;
        }
    }

    public function actualizar_alumno_pago_xeditable(Request $request)
    {
        $alumno_pago = AlumnoPago::findOrFail($request->pk);
        $alumno_pago[$request->name] = $request->value;
        $alumno_pago->save();

        return response()->json([
            'alumno_pago' => $alumno_pago
        ]);
    }

    public function actualizar_pago_xeditable(Request $request)
    {
        $pago = Pago::findOrFail($request->pk);

        switch ($request->name) {
            case 'fecha':
                $fecha = "{$request->value}";
                $pago[$request->name] = $fecha;
            break;
            default:
                $pago[$request->name] = $request->value;
            break;
        }

        $pago->save();

        return response()->json([
            'pago' => $pago
        ]);
    }
}
