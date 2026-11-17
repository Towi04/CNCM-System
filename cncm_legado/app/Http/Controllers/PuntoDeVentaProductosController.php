<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Venta;
use App\Models\PartidaVenta;
use App\Models\Alumno;
use App\Models\Producto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Jenssegers\Date\Date;
use Yajra\DataTables\Facades\DataTables;

class PuntoDeVentaProductosController extends Controller
{
    public function index()
    {
        $id_sucursal = optional(session('sucursal'))->id;
        $id_usuario = Auth::id();

        $venta = Venta::where('id_sucursal','=', $id_sucursal)->where('id_recibio','=',$id_usuario)->where('status','=','En caja')->get()->first();
        if(!$venta){
            $venta = new Venta();
            $venta->folio = Venta::where('id_sucursal','=', $id_sucursal)->max('folio') + 1;
            $venta->status = 'En caja';
            $venta->id_sucursal = $id_sucursal;
            $venta->id_recibio = Auth::id();
            $venta->save();
        }

        return view('punto_de_venta_productos.index', compact('venta'));
    }

    public function cerrar_venta(Request $request)
    {
        $this->validate($request,[
            // 'id_alumno'     => 'required',
            // 'monto'         => 'required|numeric|min:0.01|not_in:0',
            // 'forma_pago'    => 'required',
        ]);

        $id_sucursal = optional(session('sucursal'))->id;
        $id_recibio = auth()->id();
        $fecha_abono = now();

        if($request->id_alumno){
            $nombre = Alumno::find($request->input('id_alumno'))->fullname;
        }else{
            $nombre = $request->nombre;
        }
        
       
        $venta = Venta::find($request->id_venta);
        $venta->status = 'Cerrada';
        $venta->fecha = $fecha_abono;
        $venta->id_recibio = $id_recibio;
        $venta->forma_pago = $request->forma_pago;
        $venta->id_alumno = $request->id_alumno;
        $venta->nombre = $nombre;
        $venta->save();

        return response()->json([
            'venta'=>$venta
        ]);
    }

    public function ticket($id)
    {
        $venta = Venta::findOrFail($id);

        $venta->load(['partidas','alumno','sucursal','recibio']);

        return view('punto_de_venta_productos.ticket',compact('venta'));
    }

    public function datatables_partidas(Request $request)
    {
        $query = PartidaVenta::with(['venta','producto'])->where('id_venta','=',$request->id_venta);

        $venta = Venta::find($request->id_venta);

        $datatables = DataTables::eloquent($query)
        ->editColumn('cantidad', function($model){
            $route = route('punto_de_venta_productos.actualizar_informacion_partida');

            return " <a  class='editable_cantidad editable'
                data-type='text'
                data-name='cantidad'
                data-pk='{$model->id}'
                data-url='{$route}'
                data-value='{$model->cantidad}'
                data-title='Selecciona la cantidad'>
                {$model->cantidad}
            </a>";
        })
            ->addColumn('buttons', 'punto_de_venta_productos.datatables._buttons')
            ->rawColumns(['buttons','cantidad'])
            ->with([
                'total_venta' => $venta->total
            ])
            ->make(true);

        return $datatables;
    }

    public function guardar_partida(Request $request){
        $venta = Venta::find($request->id_venta);
        
        $partida = new PartidaVenta();
        $partida->id_venta = $venta->id;
        $partida->id_producto = $request->id_producto;
        $partida->cantidad = 1;
        $producto = Producto::find($request->id_producto);
        $partida->precio = $producto->precio;
        $partida->total = $producto->precio;
        $partida->save();

        $venta = Venta::find($venta->id);
        $venta->total = $venta->partidas->sum('total');
        $venta->save();

        return response()->json([
            'venta'=> $venta,
            'partida' => $partida,
        ]);

    }

    public function eliminar_partida($id){
        $partida = PartidaVenta::find($id);
        $partida->delete();

        $venta = Venta::find($partida->id_venta);
        $venta->total = $venta->partidas->sum('total');
        $venta->save();


    }

    public function actualizar_informacion_partida(Request $request)
    {
        $partida = PartidaVenta::find($request->pk);
        $partida[$request->name] = $request->value;
        $partida->save();

        $partida->total = $partida->cantidad * $partida->precio;
        $partida->save();

        $venta = Venta::find($partida->id_venta);
        $venta->total = $venta->partidas->sum('total');
        $venta->save();

        return response()->json([
            'venta' => $venta,
            'partida' => $partida,
        ]);
    }

}
