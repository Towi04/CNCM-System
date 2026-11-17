<?php

namespace App\Http\Controllers\Reportes;

use App\Models\Pago;
use App\Models\User;
use App\Models\Alumno;
use Jenssegers\Date\Date;
use Illuminate\Http\Request;
use App\Models\Configuracion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PartidaVenta;
use App\Models\Venta;
use Yajra\DataTables\Facades\DataTables;
use App\Models\ApoyoInscripcion;
use PDF;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ReporteVentasController extends Controller
{
    public function index(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $tipo = $request->input('tipo') ?? 'dia';

        $sucursal = optional(session('sucursal'));

        $mostrar_solo_fiscales = optional(Configuracion::where('nombre', '=', 'mostrar_solo_fiscales')->first())->valor == 'Si';

        if (isset($request->fecha)) {
            $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
        } else {
            $fecha = Carbon::today();
        }

        if ($tipo == 'dia') {
            $tipo = 'dia';

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'anual') {
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        
        if ($mostrar_solo_fiscales) {
            $pagos =  $pagos->whereNotNull('folio_fiscal');
        }
        

        $pagos =  $pagos->with(['alumno', 'abonos.alumno_pago', 'recibio'])->get();

        if ($request->ajax()) {
            return response()->json([
                'monto_abonos'          => $pagos->sum('monto'),
                'monto_abono_fiscal'    => $pagos->whereHas('abonos', function ($q) {
                    return $q->where('venta_fiscal', '=', 1);
                })->sum('monto'),
                'monto_abono_no_fiscal' => $pagos->whereHas('abonos', function ($q) {
                    return $q->where('venta_fiscal', '=', 0);
                })->sum('monto'),
            ]);
        }

        $user = auth()->user();
        $puede_editar_reporte_ventas = $user->can('editar_reporte_ventas');
        $puede_eliminar_registro = $user->can('eliminar_movimiento_reporte_ventas');
        $puede_reimprimir_ticket = $user->can('reimprimir_ticket_reporte_ventas');

        return view('reportes.reporte_ventas.index', compact(
            'pagos',
            'fecha',
            'fecha_antes',
            'fecha_despues',
            'tipo',
            'mostrar_solo_fiscales',
            'puede_editar_reporte_ventas',
            'puede_eliminar_registro',
            'puede_reimprimir_ticket',
        ));
    }

    public function corte_caja(Request $request)
    {
        \Carbon\Carbon::setWeekStartsAt(Carbon::SUNDAY);
        \Carbon\Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $tipo = $request->input('tipo') ?? 'dia';

        $sucursal = optional(session('sucursal'));

        if ($tipo == 'dia') {
            $tipo = 'dia';

            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
            } else {
                $fecha = Carbon::today();
            }

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();


            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'anual') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->orderBy('fecha', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if (Configuracion::where('nombre', '=', 'mostrar_solo_fiscales')->first()->valor == 'Si') {
            $pagos =  $pagos->whereHas('abonos', function ($q) {
                return $q->where('venta_fiscal', '=', 1);
            });
        }

        $pagos =  $pagos->with(['alumno', 'abonos.alumno_pago', 'recibio'])->get();


        PDF::setOptions(['isPhpEnabled' => true]);

        $lista_titulos = [
            'dia'       => $fecha->format('d \d\e F \d\e\l Y'),
            'mes'       => $fecha->format('F \d\e\l Y'),
            'semanal'   => $fecha->startOfWeek()->format('d \d\e F \d\e\l Y') . 'al' . $fecha->endOfWeek()->format('d \d\e F \d\e\l Y'),
            'anual'     => $fecha->format('Y')
        ];

        $titulo = $lista_titulos[$request->input('tipo')?? 'dia'];

        $pdf = PDF::loadView('reportes.reporte_ventas.corte_caja', [
            'pagos'     => $pagos,
            'titulo'    => $titulo,
            'autor'     => auth()->user()->full_name
        ]);

        return $pdf->stream('corte_caja.pdf');

        // if ($request->ajax()) {
        //     return response()->json([
        //         'monto_abonos'          => $pagos->sum('monto'),
        //         'monto_abono_fiscal'    => $pagos->whereHas('abonos', function ($q) {
        //             return $q->where('venta_fiscal', '=', 1);
        //         })->sum('monto'),
        //         'monto_abono_no_fiscal' => $pagos->whereHas('abonos', function ($q) {
        //             return $q->where('venta_fiscal', '=', 0);
        //         })->sum('monto'),
        //     ]);
        // }
    }

    public function vencimientos()
    {
        return view('reportes.reporte_ventas.vencimientos');
    }

    public function datatables_vencimientos(Request $request)
    {
        $query = Alumno::with(['documentos'])
            ->where('status', config('alumnos.status.Alumno'))
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })->whereHas('documentos', function ($q) {
                return $q->where('status', '=', 'Pendiente')->where('fecha_limite', '<', date('Y-m-d'));
            });

        return DataTables::eloquent($query)
            ->addColumn('nombre_alumno', function ($model) {
                return "<a href=" . route('alumnos.show', $model->id) . ">{$model->nombres} {$model->apellido_paterno} {$model->apellido_materno}</a>";
            })
            ->editColumn('documentos_vencidos', function ($model) {
                return $model->documentos_vencidos->count();
            })
            ->editColumn('monto_vencido', function ($model) {
                return "$ " . number_format($model->monto_vencido, 2, '.', ',');
            })
            // ->addColumn('buttons', 'alumnos.datatables._buttons')

            ->rawColumns(['buttons', 'nombre_alumno'])
            ->make(true);
    }

    public function proyeccion()
    {
        return view('reportes.reporte_ventas.proyeccion');
    }

    public function datatables_proyeccion(Request $request)
    {
        $today = \Carbon\Carbon::today();
        $fin_de_mes = $today->endOfMonth();

        $query = Alumno::with(['documentos'])
            ->where('status', config('alumnos.status.Alumno'))
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })->whereHas('documentos', function ($q) use ($fin_de_mes) {
                return $q->where('status', '=', 'Pendiente')->where('fecha_limite', '<', $fin_de_mes->format('Y-m-d'));
            });

        return DataTables::eloquent($query)
            ->addColumn('nombre_alumno', function ($model) {
                return "<a href=" . route('alumnos.show', $model->id) . ">{$model->nombres} {$model->apellido_paterno} {$model->apellido_materno}</a>";
            })
            ->editColumn('pagos_por_cobrar', function ($model) {
                return $model->pagos_por_cobrar->count();
            })
            ->editColumn('monto_por_cobrar', function ($model) {
                return "$ " . number_format($model->monto_por_cobrar, 2, '.', ',');
            })
            // ->addColumn('buttons', 'alumnos.datatables._buttons')

            ->rawColumns(['buttons', 'nombre_alumno'])
            ->make(true);
    }

    public function asesores(Request $request)
    {
        \Carbon\Carbon::setWeekStartsAt(Carbon::SUNDAY);
        \Carbon\Carbon::setWeekEndsAt(Carbon::SATURDAY);

        if ($request->has('tipo')) {
            $tipo = $request->input('tipo');
        } else {
            $tipo = 'dia';
        }

        $sucursal = optional(session('sucursal'));

        if ($tipo == 'dia') {
            $tipo = 'dia';

            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
            } else {
                $fecha = Carbon::today();
            }

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();


            $alumnos = Alumno::with('asesor_educativo')
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('created_at', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $alumnos = Alumno::with('asesor_educativo')
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('created_at', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $alumnos = Alumno::with('asesor_educativo')
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('created_at', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }
        if ($tipo == 'anual') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $alumnos = Alumno::with('asesor_educativo')
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('created_at', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        // dd($alumnos->get());

        $asesores =  User::whereHas('registros', function ($q) use ($alumnos) {
            return $q->whereIn('id', $alumnos->pluck('id'));
        })->get();

        $alumnos = $alumnos->get()->groupBy(function ($alumno) {
            return $alumno->asesor_educativo->id;
        });

        // dd($alumnos);



        return view('reportes.reporte_ventas.asesores', compact('alumnos', 'fecha', 'fecha_antes', 'fecha_despues', 'asesores', 'tipo'));
    }

    public function convertir_ventas_fiscales(Request $request)
    {
        $tipo = $request->tipo;

        $sucursal = optional(session('sucursal'));

        if ($tipo == 'mes') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('fecha', [$fecha->copy()->startOfMonth()->format('Y-m-d H:i:s'), $fecha->copy()->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('folio', 'asc')->get();

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {
            if (isset($request->fecha)) {
                $fecha = Carbon::createFromFormat('d-m-Y', $request->fecha);
            } else {
                $fecha = Carbon::today();
            }
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $pagos = Pago::query()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('created_at', [$fecha->copy()->startOfWeek()->format('Y-m-d H:i:s'), $fecha->copy()->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('folio', 'asc')->get();

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        $porcentaje_configuracion = Configuracion::where('nombre', '=', 'porcentaje_fiscal')->first();

        // SI NO ESTA CONFIGURADO EL PORCENTAJE FISCAL
        if (!$porcentaje_configuracion) {
            Session::flash('error', 'Se debe configurar un porcentaje en la configuración global de la plataforma');
            return redirect()->back();
        }

        $porcentaje_esperado = $porcentaje_configuracion->valor;
        // dd($pagos);
        // SE COMIENZA CON LA CONVERSION

        $porcentaje_fiscal_real = $pagos->whereNotNull('folio_fiscal')->sum('monto') / $pagos->sum('monto') * 100;

        // dd($porcentaje_fiscal_real);
        if ($porcentaje_fiscal_real <= $porcentaje_esperado) {
            foreach ($pagos->whereNull('folio_fiscal') as $pago) {

                $max_folio_fiscal = Pago::where('id_sucursal', '=', $sucursal->id)->max('folio_fiscal');

                $max_folio_fiscal = $max_folio_fiscal + 1;

                $pago->update(['folio_fiscal' => $max_folio_fiscal]);
                // Abono::where('id_pago','=',$pago->id)->update(['venta_fiscal'=>1]);
                $pago->abonos()->update(['venta_fiscal' => 1]);
                // break;
                if ($tipo == 'mes') {
                    $pas = Pago::query()
                        ->where('id_sucursal', '=', $sucursal->id)
                        ->whereBetween('fecha', [$fecha->copy()->startOfMonth()->format('Y-m-d H:i:s'), $fecha->copy()->endOfMonth()->format('Y-m-d H:i:s')])
                        ->orderBy('folio', 'asc')->get();
                }

                if ($tipo == 'semanal') {
                    $pas = Pago::query()
                        ->where('id_sucursal', '=', $sucursal->id)
                        ->whereBetween('created_at', [$fecha->copy()->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                        ->orderBy('folio', 'asc')->get();
                }

                // dd($pas);

                $porcentaje_fiscal_real = $pas->whereNotNull('folio_fiscal')->sum('monto') / $pas->sum('monto') * 100;

                if ($porcentaje_fiscal_real >= $porcentaje_esperado) {
                    break;
                }
            }
        }



        return response()->json([]);
        // Session::flash('message','Se ajustaron las ventas no fiscales a fiscales de acuerdo al porcentaje correctamente');
        // return redirect()->back();

    }

    public function index_productos(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        # 👉 SECCION DE VARIABLES
        $tipo = $request->input('tipo') ?? 'dia';

        $sucursal = optional(session('sucursal'));

        if (isset($request->fecha)) {
            $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
        } else {
            $fecha = Carbon::today();
        }

        $inicio  = null;
        $final = null;

        if ($tipo == 'dia') {
            $fecha =  new Date($fecha);

            $inicio = $fecha->startOfDay()->format('Y-m-d H:i:s');
            $final = $fecha->endOfDay()->format('Y-m-d H:i:s');

            $fecha_antes = new Date($fecha->clone()->subDay()->format('Y-m-d'));
            $fecha_despues = new Date($fecha->clone()->addDay()->format('Y-m-d'));
        }

        if ($tipo == 'mes') {
            $fecha =  new Date($fecha);
            $fecha_antes = new Date($fecha->clone()->subMonth()->format('Y-m-d'));
            $fecha_despues = new Date($fecha->clone()->addMonth()->format('Y-m-d'));

            $inicio = $fecha->startOfMonth()->format('Y-m-d H:i:s');
            $final = $fecha->endOfMonth()->format('Y-m-d H:i:s');
        }

        if ($tipo == 'semanal') {
            $fecha =  new Date($fecha);
            $fecha_antes = new Date($fecha->clone()->subDays(7)->format('Y-m-d'));
            $fecha_despues = new Date($fecha->clone()->addDays(7)->format('Y-m-d'));

            $inicio = $fecha->startOfWeek()->format('Y-m-d H:i:s');
            $final = $fecha->endOfWeek()->format('Y-m-d H:i:s');
        }

        if ($tipo == 'anual') {
            $fecha =  new Date($fecha);
            $fecha_antes = new Date($fecha->clone()->subYear()->format('Y-m-d'));
            $fecha_despues = new Date($fecha->clone()->addYear()->format('Y-m-d'));

            $inicio = $fecha->startOfYear()->format('Y-m-d H:i:s');
            $final = $fecha->endOfYear()->format('Y-m-d H:i:s');
        }

        # 👉 OBTENER PARTIDAS
        $partidas = PartidaVenta::query()
        ->whereHas('venta',function($q) use($inicio,$final,$sucursal){
            $q->where('id_sucursal', '=', $sucursal->id);
            $q->whereBetween('fecha', [$inicio, $final]);
            $q->where('status','Cerrada');
        })
        ->with(['venta','producto'])
        ->get();

        # 👉 CALCULO TOTALES
        $total = $partidas->sum('total');

        $total_fiscal = $partidas->filter(function($partida){
            return $partida->venta->forma_pago != 'Efectivo';
        })->sum('total');

        $total_no_fiscal = $partidas->filter(function($partida){
            return $partida->venta->forma_pago == 'Efectivo';
        })->sum('total');

        $porcentaje_fiscal = ($total_fiscal > 0) ?
            number_format($total_fiscal / $total * 100, 2) . ' %':
            ' NO SE HAN REGISTRADO VENTAS';

        if ($request->ajax()) {
            return response()->json([
                'total'             => $total,
                'total_fiscal'      => $total_fiscal,
                'total_no_fiscal'   => $total_no_fiscal,
                'porcentaje_fiscal' => $porcentaje_fiscal,
            ]);
        }

        # 👉 PERMISOS
        $user = auth()->user();
        $puede_editar = $user->can('editar_reporte_ventas_productos');
        $puede_eliminar = $user->can('eliminar_movimiento_reporte_ventas_productos');
        $puede_reimprimir = $user->can('reimprimir_ticket_reporte_ventas_productos');

        return view('reportes.reporte_ventas.index_productos', compact(
            'partidas',
            'fecha',
            'fecha_antes',
            'fecha_despues',
            'tipo',
            'puede_editar',
            'puede_eliminar',
            'puede_reimprimir',
            'total',
            'total_fiscal',
            'total_no_fiscal',
            'porcentaje_fiscal',
        ));
    }

    public function actualizar_ventas_xeditable(Request $request)
    {
        $venta = Venta::findOrFail($request->pk);
        $venta[$request->name] = $request->value;

        switch ($request->name) {
            case 'fecha':
                $hour = now()->format('h:i a');
                $fecha = "{$request->value} {$hour}";
                $venta[$request->name] = $fecha;
            break;
            default:
                $venta[$request->name] = $request->value;
            break;
        }

        $venta->save();

        return response()->json([
            'venta' => $venta
        ]);
    }

    public function actualizar_partidas_ventas_xeditable(Request $request)
    {
        # 👉 ACTUALIZA INFORMACION
        $partida = PartidaVenta::findOrFail($request->pk);
        $partida[$request->name] = $request->value;
        $partida->save();

        # 👉 ACTUALIZO LA INFORMACION DEL PRODUCTO
        $producto = $partida->producto;
        $partida->precio = $producto->precio;
        $partida->total = $producto->precio;
        $partida->total = $partida->cantidad * $partida->precio;
        $partida->save();

        # 👉 ACTUALIZO EL TOTAL DE LA VENTA
        $venta = $partida->venta;
        $venta->total = $venta->partidas->sum('total');
        $venta->save();

        return response()->json([
            'partida' => $partida
        ]);
    }

    public function eliminar_partida(Request $request, PartidaVenta $partida)
    {
        DB::beginTransaction();

        try {
            # ELIMINAMOS LA PARTIDA
            $venta = $partida->venta;
            $partida->delete();

            # SE RECALCULA EL TOTAL DE LAS PARTIDAS
            $venta->total = $venta->partidas()->sum('total');
            $venta->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()
                ->back()
                ->with([
                    'error' => 'Hubo un error al eliminar la partida: ',
                ]);
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Partida eliminada correctamente',
                'success' => true,
            ]);
        }

        return redirect()->back()->with([
            'message' => 'Partida eliminada correctamente',
        ]);
    }

    public function eliminar_pago(Request $request, Pago $pago)
    {
        DB::beginTransaction();
        $sucursal = session('sucursal');
        try {
            $pago->load(['abonos_documentos.documento','alumno']);

            $pago->abonos_documentos->each(function ($abono) {
                $documento = $abono->documento;
                $documento->saldo = $documento->saldo + $abono->monto;
                $documento->save();
                if($documento->saldo > 0){
                    $documento->status = 'Pendiente';
                }  
                $documento->save(); 
                $abono->delete();                
            });

            $pago->id_usuario_elimino = Auth::id();
            $pago->save();
            $pago->delete();
            
            Log::alert('Usuario '.Auth::user()->fullname.' elimino el pago '.(($pago->folio_fiscal)?$pago->folio_fiscal:$pago->folio).' de '.$pago->alumno->numero_control_fullname.' por '.$pago->monto.' | Sucursal: '.$sucursal->nombre);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
             dd($e);
            return redirect()
                ->back()
                ->with([
                    'error' => 'Hubo un error al eliminar el pago: ',
                ]);
        }

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Pago eliminado correctamente',
                'success' => true,
            ]);
        }


        return redirect()->back()->with([
            'message' => 'Pago eliminado correctamente',
        ]);
    }

    public function ocultar_ventas_no_fiscales()
    {
        $config = Configuracion::query()->where('nombre','mostrar_solo_fiscales')->first();

        if(!empty($config)){
            $config->valor = ($config->valor == 'No' || empty($config->valor)) ? 'Si': 'No';
            $config->save();
        }

        return redirect()->back();
    }

    public function apoyos_inscripcion(Request $request)
    {
        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $tipo = $request->input('tipo') ?? 'semanal';

        $sucursal = optional(session('sucursal'));

        // dd($sucursal);

        if (isset($request->fecha)) {
            $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
        } else {
            $fecha = Carbon::today();
        }

        if ($tipo == 'dia') {
            $tipo = 'dia';

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();

            $apoyos_inscripcion = ApoyoInscripcion::with(['alumno','usuario_autorizo','usuario_solicito'])
                ->whereBetween('created_at', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $apoyos_inscripcion = ApoyoInscripcion::with(['alumno','usuario_autorizo','usuario_solicito'])
                ->whereBetween('created_at', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $apoyos_inscripcion = ApoyoInscripcion::with(['alumno','usuario_autorizo','usuario_solicito','grupo.especialidad'])
                ->whereBetween('created_at', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'anual') {
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $apoyos_inscripcion = ApoyoInscripcion::with(['alumno','usuario_autorizo','usuario_solicito'])
                ->whereBetween('created_at', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->orderBy('created_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        $apoyos = $apoyos_inscripcion->get()->filter(function($apoyo)use($sucursal){
            return $apoyo->alumno->id_sucursal == $sucursal->id;
        });


        $user = auth()->user();

        return view('reportes.apoyos_inscripcion', compact(
            'apoyos',
            'fecha',
            'fecha_antes',
            'fecha_despues',
            'tipo',
        ));
    }

    public function pagos_eliminados(Request $request){

        Carbon::setWeekStartsAt(Carbon::SUNDAY);
        Carbon::setWeekEndsAt(Carbon::SATURDAY);

        $tipo = $request->input('tipo') ?? 'dia';

        $sucursal = optional(session('sucursal'));

        $mostrar_solo_fiscales = optional(Configuracion::where('nombre', '=', 'mostrar_solo_fiscales')->first())->valor == 'Si';

        if (isset($request->fecha)) {
            $fecha = Carbon::createFromFormat('d-m-Y', $request->input('fecha'));
        } else {
            $fecha = Carbon::today();
        }

        if ($tipo == 'dia') {
            $tipo = 'dia';

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDay();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDay();

            $pagos = Pago::onlyTrashed()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('deleted_at', [$fecha->startOfDay()->format('Y-m-d H:i:s'), $fecha->endOfDay()->format('Y-m-d H:i:s')])
                ->orderBy('deleted_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'mes') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subMonth();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addMonth();

            $pagos = Pago::onlyTrashed()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('deleted_at', [$fecha->startOfMonth()->format('Y-m-d H:i:s'), $fecha->endOfMonth()->format('Y-m-d H:i:s')])
                ->orderBy('deleted_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'semanal') {

            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subDays(7);
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addDays(7);

            $pagos = Pago::onlyTrashed()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('deleted_at', [$fecha->startOfWeek()->format('Y-m-d H:i:s'), $fecha->endOfWeek()->format('Y-m-d H:i:s')])
                ->orderBy('deleted_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($tipo == 'anual') {
            $fecha =  new Date($fecha);
            $fecha_antes = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->subYear();
            $fecha_despues = Carbon::createFromFormat('Y-m-d', $fecha->format('Y-m-d'))->addYear();

            $pagos = Pago::onlyTrashed()
                ->where('id_sucursal', '=', $sucursal->id)
                ->whereBetween('deleted_at', [$fecha->startOfYear()->format('Y-m-d H:i:s'), $fecha->endOfYear()->format('Y-m-d H:i:s')])
                ->orderBy('deleted_at', 'desc');

            $fecha_antes = new Date($fecha_antes);
            $fecha_despues = new Date($fecha_despues);
        }

        if ($mostrar_solo_fiscales) {
            $pagos =  $pagos->whereHas('abonos', function ($q) {
                return $q->where('venta_fiscal', '=', 1);
            });
        }

        $pagos =  $pagos->with(['alumno', 'abonos.alumno_pago', 'recibio'])->get();

        if ($request->ajax()) {
            return response()->json([
                'monto_abonos'          => $pagos->sum('monto'),
                'monto_abono_fiscal'    => $pagos->whereHas('abonos', function ($q) {
                    return $q->where('venta_fiscal', '=', 1);
                })->sum('monto'),
                'monto_abono_no_fiscal' => $pagos->whereHas('abonos', function ($q) {
                    return $q->where('venta_fiscal', '=', 0);
                })->sum('monto'),
            ]);
        }

        $user = auth()->user();
        $puede_editar_reporte_ventas = $user->can('editar_reporte_ventas');
        $puede_eliminar_registro = $user->can('eliminar_movimiento_reporte_ventas');
        $puede_reimprimir_ticket = $user->can('reimprimir_ticket_reporte_ventas');

        return view('reportes.reporte_ventas.pagos_eliminados', compact(
            'pagos',
            'fecha',
            'fecha_antes',
            'fecha_despues',
            'tipo',
            'mostrar_solo_fiscales',
            'puede_editar_reporte_ventas',
            'puede_eliminar_registro',
            'puede_reimprimir_ticket',
        ));

    }

}
