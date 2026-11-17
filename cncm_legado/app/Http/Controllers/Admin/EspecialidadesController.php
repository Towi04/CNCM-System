<?php

namespace App\Http\Controllers\Admin;

use App\Models\Especialidad;
use App\Models\Precio;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class EspecialidadesController extends Controller
{
    public function index()
    {
        abort_unless(Auth::user()->can('listar_especialidades'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.especialidades.index');
    }

    public function datatables(Request $request)
    {
        $query = Especialidad::query();

        return DataTables::eloquent($query)
            ->editColumn('precio_inscripcion',function($model){
                return '$ '.number_format($model->precio_inscripcion,2,'.',',');
            })
            ->editColumn('precio_mensualidad',function($model){
                return '$ '.number_format($model->precio_mensualidad,2,'.',',');
            })
            ->editColumn('precio_mensualidad_pronto_pago',function($model){
                return '$ '.number_format($model->precio_mensualidad_pronto_pago,2,'.',',');
            })
            ->editColumn('precio_semanal',function($model){
                return '$ '.number_format($model->precio_semanal,2,'.',',');
            })
            ->addColumn('buttons', 'admin.especialidades.datatables._buttons')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function show(Especialidad $especialidad)
    {
        abort_unless(Auth::user()->can('consultar_especialidad'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.especialidades.show',compact('especialidad'));
    }

    public function create()
    {
        abort_unless(Auth::user()->can('crear_especialidad'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.especialidades.create',[
            'especialidad'    => new Especialidad(),
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'nombre'                            => 'required',
            'descripcion'                       => 'nullable',
            'precio_inscripcion'                => 'nullable',
            'precio_mensualidad'                => 'nullable',
            'precio_mensualidad_pronto_pago'    => 'nullable',
            'precio_semanal'                    => 'nullable',
            'id_sucursal'                       => 'required',
            'formas_pago'                       => 'required'
        ];

        $request->request->add([
            'id_sucursal'   => optional(session('sucursal'))->id,
        ]);

        $data = $request->validate($rules);
        
        $especialidad = Especialidad::create($data);
      
        if($request->formas_pago){
            $especialidad->formas_pago = json_encode($request->formas_pago);
        }else{
            $especialidad->formas_pago = '[]';
        }
        $especialidad->save();

        # 👉 AGREGAR CORDINADORES
        if($request->has('id_usuario')){
            $especialidad->cordinadores()->attach($request->input('id_usuario'));
        }

        return redirect()->route('admin.especialidades.edit', $especialidad->id)->with([
            'message' => 'Se agregó la especialidad con éxito',
        ]);
    }

    public function edit(Especialidad $especialidad)
    {
        abort_unless(Auth::user()->can('editar_especialidad'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('admin.especialidades.edit', [
            'especialidad'    => $especialidad,
        ]);
    }

    public function update(Request $request, Especialidad $especialidad)
    {
        $rules = [
            'nombre'                            => 'required',
            'descripcion'                       => 'nullable',
            'precio_inscripcion'                => 'nullable',
            'precio_mensualidad'                => 'nullable',
            'precio_mensualidad_pronto_pago'    => 'nullable',
            'precio_semanal'                    => 'nullable',
            'id_sucursal'                       => 'required',
        ];

        $request->request->add([
            'id_sucursal'   => optional(session('sucursal'))->id,
        ]);

        $data = $this->validate($request, $rules);
        $especialidad->fill($data);
        if($request->formas_pago){
            $especialidad->formas_pago = json_encode($request->formas_pago);
        }else{
            $especialidad->formas_pago = '[]';
        }
        
        $especialidad->save();

        # 👉 ACTUALIZAR COORDINADORES
        $especialidad->cordinadores()->sync($request->input('id_usuario'));

        return redirect()->route('admin.especialidades.index')->with([
            'message' => 'Se actualizó la especialidad con éxito'
        ]);
    }

    public function destroy(Especialidad $especialidad, Request $request)
    {
        $especialidad->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'La especialidad fue eliminada con éxito',
            ]);
        }

        return redirect()->route('admin.especialidades.index')->with([
            'message' => 'La especialidad fue eliminada con éxito'
        ]);
    }

    public function cronograma($id)
    {
        $especialidad = Especialidad::find($id);
        $especialidad->load('grupos_activos');
        $sucursal = optional(session('sucursal'));

        # 👉 OBTENGO A TRAVES DE LA RELACION LOS GRUPOS ACTIVOS Y PREGARGO SUS MATERIAS
        $grupos = $especialidad->grupos_activos->load('materias');

        // Se obtiene la fecha de inicio mas antigua para saber de que semena se va a comenzar
        $fecha_inicio = $grupos->min('fecha_inicio');

        // Se obtiene la fecha de inico mas reciente para calcular hasta que semana se va a mostrar en el calendario.
        $fecha_reciente_inicio = $grupos->max('fecha_inicio');

        // Se obtiene el numero de semanas del grupo
        $max_semanas = $grupos->max(function($grupo){
            return $grupo->materias->sum('semanas');
        });

        // Se obtiene el total de semanas que se van a dibujar en la tabla
        $dif_semanas = $fecha_inicio->diffInWeeks($fecha_reciente_inicio->addWeeks($max_semanas));


        return view('admin.especialidades.cronograma', compact('grupos','dif_semanas','especialidad','fecha_inicio'));
    }

    public function guardar_precio(Request $request){
        
        if($request->periodo == 'Precio actual'){
            $precio = Precio::where('id_especialidad','=',$request->id_especialidad)->where('tipo','=',$request->tipo)->whereNull('fecha_final')->update(['fecha_final'=>date('Y-m-d H:i:s')]);
        }

        $especialidad = Especialidad::find($request->id_especialidad);

        if($request->tipo == 'Inscripción'){
            $precio = Precio::create([
                'tipo' => $request->tipo,
                'id_especialidad' => $request->id_especialidad,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_final' => ($request->periodo == 'Precio historico')?$request->fecha_final:null,
                'precio_pronto_pago' => null,
                'precio_normal' => $request->precio_inscripcion,
                'id_usuario' => Auth::id(),
            ]);

            if($request->periodo == 'Precio actual'){
                $precio_anterior = $especialidad ->precio_inscripcion;
                $especialidad ->precio_inscripcion = $request->precio_inscripcion;
                Log::alert('Usuario '.Auth::user()->fullname.' actualizo el precio de la inscripcion de la especliadad '.$especialidad->nombre.' de '.$precio_anterior.' a '.$request->precio_inscripcion);
            }
            
            
            
        }

        if($request->tipo == 'Precio Semanal'){

            $precio = Precio::create([
                'tipo' => $request->tipo,
                'id_especialidad' => $request->id_especialidad,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_final' => ($request->periodo == 'Precio historico')?$request->fecha_final:null,
                'precio_pronto_pago' => null,
                'precio_normal' => $request->precio_semanal,
                'id_usuario' => Auth::id(),
            ]);

            if($request->periodo == 'Precio actual'){
                $precio_anterior = $especialidad ->precio_semanal;
                $especialidad ->precio_semanal = $request->precio_semanal;
                Log::alert('Usuario '.Auth::user()->fullname.' actualizo el precio de colegiatura semanal de la especliadad '.$especialidad->nombre.' de '.$precio_anterior.' a '.$request->precio_semanal);
            }

            
        }

        if($request->tipo == 'Precio Mensual'){
            $precio = Precio::create([
                'tipo' => $request->tipo,
                'id_especialidad' => $request->id_especialidad,
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_final' => ($request->periodo == 'Precio historico')?$request->fecha_final:null,
                'precio_pronto_pago' => $request->precio_mensual_pronto_pago,
                'precio_normal' => $request->precio_mensual,
                'id_usuario' => Auth::id(),
            ]);
            
            if($request->periodo == 'Precio actual'){
                $precio_anterior_pronto_pago = $especialidad ->precio_mensualidad_pronto_pago;
                $precio_anterior = $especialidad ->precio_mensualidad;

                $especialidad ->precio_mensualidad_pronto_pago = $request->precio_mensual_pronto_pago;
                $especialidad ->precio_mensualidad = $request->precio_mensual;
                $sucursal = Session::get('sucursal');            
                Log::alert('Usuario '.Auth::user()->fullname.' actualizó el precio de colegiatura mensual de la especliadad '.$especialidad->nombre.' de '.$precio_anterior.' ('.$precio_anterior_pronto_pago.' pronto pago) a '.$request->precio_mensual.' ('.$request->precio_mensual_pronto_pago.')');
            }

            
        }

        $sucursal = Session::get('sucursal');    
        // SE ACTUALIZAN LOS PRECIOS DE LOS GRUPOS
        if($request->periodo == 'Precio actual'){
            foreach($especialidad->grupos as $grupo){

                $precio = Precio::where('id_grupo','=',$grupo->id)->where('tipo','=',$request->tipo)->whereNull('fecha_final')->update(['fecha_final'=>date('Y-m-d H:i:s')]);

                if($request->tipo == 'Inscripción'){
                    $precio = Precio::create([
                        'tipo' => $request->tipo,
                        'id_grupo' => $grupo->id,
                        'fecha_inicio' => date('Y-m-d H:i:s'),
                        'precio_pronto_pago' => null,
                        'precio_normal' => $request->precio_inscripcion,
                        'id_usuario' => Auth::id(),
                    ]);
        
                    $precio_anterior =  $grupo ->precio_inscripcion;
                    $grupo ->precio_inscripcion = $request->precio_inscripcion;
                    
                    Log::alert('Usuario '.Auth::user()->fullname.' actualizo el precio de la inscripcion del grupo '.$grupo->nombre.' de '.$precio_anterior.' a '.$request->precio_inscripcion.'. Sucursal:'. $sucursal->nombre );
                
                }
        
                if($request->tipo == 'Precio Semanal'){
        
                    $precio = Precio::create([
                        'tipo' => $request->tipo,
                        'id_grupo' => $grupo->id,
                        'fecha_inicio' => date('Y-m-d H:i:s'),
                        'precio_pronto_pago' => null,
                        'precio_normal' => $request->precio_semanal,
                        'id_usuario' => Auth::id(),
                    ]);
        
                    $precio_anterior =  $grupo ->precio_semanal;
                    $grupo ->precio_semanal = $request->precio_semanal;
        
                    Log::alert('Usuario '.Auth::user()->fullname.' actualizo el precio de la colegiatura semanal del grupo '.$grupo->nombre.' de '.$precio_anterior.' a '.$request->precio_inscripcion.'. Sucursal:'. $sucursal->nombre );
                }
        
                if($request->tipo == 'Precio Mensual'){
                    $precio = Precio::create([
                        'tipo' => $request->tipo,
                        'id_grupo' => $grupo->id,
                        'fecha_inicio' => date('Y-m-d H:i:s'),
                        'precio_pronto_pago' => $request->precio_mensual_pronto_pago,
                        'precio_normal' => $request->precio_mensual,
                        'id_usuario' => Auth::id(),
                    ]);
        
                    $precio_anterior_pronto_pago = $grupo ->precio_mensualidad_pronto_pago;
                    $precio_anterior = $grupo ->precio_mensualidad;
        
                    $grupo ->precio_mensualidad_pronto_pago = $request->precio_mensual_pronto_pago;
                    $grupo ->precio_mensualidad = $request->precio_mensual;
                    
                    Log::alert('Usuario '.Auth::user()->fullname.' actualizó el precio de colegiatura mensual de la especliadad '.$grupo->nombre.' de '.$precio_anterior.' ('.$precio_anterior_pronto_pago.' pronto pago) a '.$request->precio_mensual.' ('.$request->precio_mensual_pronto_pago.')');
                }

                $grupo->save();
            }
        }
        $especialidad->save();

        Session::flash('message','Se dio de alta con éxito el precio');

        
        return redirect()->back();
       
    }

    public function actualizar_informacion_precio(Request $request){

        $precio = Precio::find($request->pk);
        $precio[$request->name] = $request->value;
        $precio->save();

    }

}
