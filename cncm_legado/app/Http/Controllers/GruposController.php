<?php

namespace App\Http\Controllers;

use PDF;
use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\Materia;
use App\Models\GrupoDia;
use App\Models\AlumnoGrupo;
use App\Models\Especialidad;
use App\Models\GrupoMateria;
use App\Models\Precio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\PagoInscripcionDocumentosService;
use App\Services\PagoColegiaturaDocumentosService;

use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;
use Illuminate\Support\Facades\Log;

class GruposController extends Controller
{
    public function index()
    {
        return view('grupos.index');
    }

    public function datatables(Request $request)
    {
        $user = auth()->user();
        $puede_ver_todos_grupos = $user->can('ver_todos_grupos');
        $especialidades = $user->especialidades->pluck('id');

        $query = Grupo::query()
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })
            ->when($request->input('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->when(!$puede_ver_todos_grupos,function($q)use($especialidades){
                $q->whereIn('id_especialidad',$especialidades);
            })
            ->with(['especialidad','days','alumnos_simples','materias']);

        return DataTables::eloquent($query)
            ->editColumn('fecha_inicio', function ($model) {
                return optional($model->fecha_inicio)->format('d/m/Y');
            })
            ->editColumn('infantil', function ($model) {
                $tipo_grupo = ($model->infantil) ? 'Infantil' : 'Adulto';
                return "<a class='badge badge-primary text-white'>{$tipo_grupo}</a>";
            })
            ->addColumn('days', function ($model) {
                return $model->days->pluck('display_name')->implode('<br>');
            })
            ->addColumn('no_alumnos', function ($model) {
                $line = '';
                $line .= $model->alumnos->where('pivot.status','Inscrito')->count();
                if($model->max_alumnos){
                    $line .= '/'.$model->max_alumnos;
                }else{
                    $line .= '/∞';
                }
                
                return $line;
            })
            ->addColumn('no_semanas', function ($model) {
                return $model->materias->sum('semanas');
            })
            ->addColumn('buttons', 'grupos.datatables._buttons')
            ->rawColumns(['buttons', 'infantil', 'days'])
            ->make(true);
    }

    public function show(Grupo $grupo)
    {
        abort_unless(Auth::user()->can('consultar_grupo'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('grupos.show', compact('grupo'));
    }

    public function create()
    {
        abort_unless(Auth::user()->can('crear_grupo'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $dias_semana = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'domingo'   => 'Domingo',
        ];

        return view('grupos.create', [
            'grupo'         => new Grupo,
            'especialidades'  => Especialidad::query()

                ->pluck('nombre', 'id')
                ->sort()
                ->prepend('Selecciona una especialidad', ''),
            'dias_semana' => $dias_semana
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'id_sucursal'                       => 'required',
            'id_especialidad'                   => 'required',
            'horario'                           => 'required',
            'dia'                               => 'required',
            'infantil'                          => 'required',
            'fecha_inicio'                      => 'required',
            'precio_semanal'                    => 'nullable',
            'precio_mensualidad_pronto_pago'    => 'nullable',
            'precio_mensualidad'                => 'nullable',
            'precio_inscripcion'                => 'nullable',
            'clave'                             => 'required',
            'status'                            => 'required',
        ];

        $especialidad = Especialidad::findOrFail($request->input('id_especialidad'));

        $materias = Materia::toBase()->select('id','orden')->where('id_especialidad', $request->input('id_especialidad'))->get();

        # 👉 VERIFICACION DEL STATUS DEL GRUPO
        $status = Carbon::parse($request->input('fecha_inicio'))->lt(now())
            ? config('grupos.status.values.Activo')
            : config('grupos.status.values.Programado');

        $request->request->add([
            'id_sucursal'                       => optional(session('sucursal'))->id,
            'infantil'                          => $request->has('infantil'),
            'precio_semanal'                    => $especialidad->precio_semanal,
            'precio_mensualidad_pronto_pago'    => $especialidad->precio_mensualidad_pronto_pago,
            'precio_mensualidad'                => $especialidad->precio_mensualidad,
            'precio_inscripcion'                => $especialidad->precio_inscripcion,
            'status'                            => $status
        ]);

        $data = $request->validate($rules);

        $grupo = Grupo::create($data);

        if(isset($request->max_alumnos)){
            $grupo->max_alumnos = $request->max_alumnos;
        }else{
            $grupo->max_alumnos = null;
        }
        $grupo->save();

        # 👉 ASOCIAR LAS MATERIAS AL GRUPO ESPECIFICANDO UN ORDEN
        foreach ($materias as $materia) {
            $grupo->materias()->attach($materia->id,[
                'horas_semana'  => null,
                'id_profesor'   => null,
                'orden'         => $materia->orden
            ]);
        }

        $dias_number = [
            'lunes'     => '2',
            'martes'    => '3',
            'miercoles' => '4',
            'jueves'    => '5',
            'viernes'   => '6',
            'sabado'    => '7',
            'domingo'   => '1',
        ];
        // CREACION DE HORAS Y DIAS
        foreach ($request->dia as $dia) {
            $grupo_dia = new GrupoDia();
            $grupo_dia->id_grupo = $grupo->id;
            $grupo_dia->dia = $dia;
            $grupo_dia->dayofweek = $dias_number[$dia];
            $grupo_dia->hora_inicio = $request['inicio_' . $dia];
            $grupo_dia->hora_final = $request['fin_' . $dia];
            $grupo_dia->save();
        }

        return redirect()->route('grupos.index')->with([
            'message' => 'Se agregó el grupo con éxito',
        ]);
    }

    public function edit(Grupo $grupo)
    {
        abort_unless(Auth::user()->can('editar_grupo'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $dias_semana = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'domingo'   => 'Domingo',
        ];

        
        return view('grupos.edit', [
            'grupo'             => $grupo,
            'especialidades'    => Especialidad::query()->pluck('nombre', 'id')->sort()->prepend('Selecciona una especialidad', ''),
            'dias_semana' => $dias_semana,
        ]);
    }

    public function update(Request $request, Grupo $grupo)
    {
        $rules = [
            'id_sucursal'                       => 'required',
            'id_especialidad'                   => 'required',
            'horario'                           => 'required',
            'infantil'                          => 'required',
            'fecha_inicio'                      => 'required',
            'precio_semanal'                    => 'nullable',
            'precio_mensualidad_pronto_pago'    => 'nullable',
            'precio_mensualidad'                => 'nullable',
            'precio_inscripcion'                => 'nullable',
            'clave'                             => 'required',
            'status'                            => 'required'
        ];

        $especialidad = Especialidad::findOrFail($request->input('id_especialidad'));
        $materias = Materia::query()->where('id_especialidad', $request->input('id_especialidad'))->pluck('id');

        # 👉 VERIFICACION DEL STATUS DEL GRUPO
        $status = Carbon::parse($request->input('fecha_inicio'))->lt(now())
            ? config('grupos.status.values.Activo')
            : config('grupos.status.values.Programado');

        $request->request->add([
            'id_sucursal'                       => optional(session('sucursal'))->id,
            'infantil'                          => $request->has('infantil'),
            'precio_semanal'                    => $especialidad->precio_semanal,
            'precio_mensualidad_pronto_pago'    => $especialidad->precio_mensualidad_pronto_pago,
            'precio_mensualidad'                => $especialidad->precio_mensualidad,
            'precio_inscripcion'                => $especialidad->precio_inscripcion,
            'status'                            => $status
        ]);

        $data = $this->validate($request, $rules);
        $grupo->fill($data);
        $grupo->save();

        $grupo->materias()->detach();

        $grupo->materias()->attach($materias, [
            'horas_semana'  => null,
            'id_profesor'   => null,
        ]);

        // CREACION DE HORAS Y DIAS
        $grupo->days()->delete();

        $dias_number = [
            'lunes'     => '2',
            'martes'    => '3',
            'miercoles' => '4',
            'jueves'    => '5',
            'viernes'   => '6',
            'sabado'    => '7',
            'domingo'   => '1',
        ];


        foreach ($request->dia as $dia) {
            $grupo_dia = new GrupoDia();
            $grupo_dia->id_grupo = $grupo->id;
            $grupo_dia->dia = $dia;
            $grupo_dia->dayofweek = $dias_number[$dia];
            $grupo_dia->hora_inicio = $request['inicio_' . $dia];
            $grupo_dia->hora_final = $request['fin_' . $dia];
            $grupo_dia->save();
        }

        if(isset($request->max_alumnos)){
            $grupo->max_alumnos = $request->max_alumnos;
        }else{
            $grupo->max_alumnos = null;
        }
        $grupo->save();


        return redirect()->route('grupos.index')->with([
            'message' => 'Se actualizó el grupo con éxito'
        ]);
    }

    public function destroy(Grupo $grupo, Request $request)
    {
        $grupo->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El grupo fue eliminado con éxito',
            ]);
        }

        return redirect()->route('grupos.index')->with([
            'message' => 'El grupo fue eliminado con éxito'
        ]);
    }

    public function traer_grupos_select2(Request $request)
    {
        $results = Grupo::query()
            ->when($request->input('id_sucursal'), function ($q, $sucursal) {
                $q->where('id_sucursal', $sucursal);
            })
            ->when($request->input('id_especialidad'), function ($q, $especialidad) {
                $q->where('id_especialidad', $especialidad);
            })
            ->orderBy('fecha_inicio', 'desc')
            ->get();

            $especialidad = Especialidad::find($request->id_especialidad);

        if ($request->ajax()) {
            return response()->json([
                'results'       => $results,
                'formas_pago' => json_decode($especialidad->formas_pago), 
            ]);
        }

        return redirect()->back();

        
    }

    # NOTE: ASIGNACION DE MATERIAS

    public function asignar_materias(Grupo $grupo)
    {
        abort_unless(Auth::user()->can('crear_grupo'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $grupo->load(['days','especialidad']);

        return view('grupos.asignar_materias', compact('grupo'));
    }

    public function guardar_materias(Grupo $grupo, Request $request)
    {
        $rules = [
            'id_materia'            => 'required',
            'horas_semana'          => 'nullable',
            'id_profesor'           => 'nullable',
        ];

        $this->validate($request, $rules);

        $grupo->materias()->attach($request->input('id_materia'), [
            'horas_semana'  => $request->input('horas_semana'),
            'id_profesor'   => $request->input('id_profesor'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Materia asociada correctamente',
        ]);
    }

    public function actualizar_materias_xeditable(Request $request)
    {
        $grupo_materia = GrupoMateria::find($request->pk);
        $grupo_materia[$request->name] = $request->value;
        $grupo_materia->save();

        $grupo_materia->load(['materia', 'profesor']);

        return response()->json([
            'grupo_materia' => $grupo_materia
        ]);
    }

    public function eliminar_materias(Request $request)
    {
        $rules = [
            'id_grupo_materia'            => 'required',
        ];

        $this->validate($request, $rules);

        $grupo_materia = GrupoMateria::findOrFail($request->input('id_grupo_materia'));
        $grupo_materia->delete();

        return response()->json([
            'message' => 'Materia eliminada correctamente'
        ]);
    }

    public function datatables_materias(Request $request)
    {
        $query = GrupoMateria::select('grupos_materias.*')
            ->when($request->input('id_grupo'), function ($q, $grupo) {
                $q->where('id_grupo', $grupo);
            })->with(['materia', 'profesor']);

        return DataTables::eloquent($query)
            ->editColumn('orden', function ($model) {
                $route = route('grupos.actualizar_materias_xeditable');
                return " <a  class='editable_orden editable'
                    data-type='number'
                    data-name='orden'
                    data-pk='{$model->id}'
                    data-url='{$route}'
                    data-value='{$model->orden}'
                    data-min='1'
                    data-placeholder='Escribe el orden de la materia'>
                    {$model->orden}
                </a>";
            })
            ->addColumn('nombre_materia', function ($model) {
                $route = route('grupos.actualizar_materias_xeditable');

                return " <a  class='editable_id_materia editable'
                    data-type='select2'
                    data-name='id_materia'
                    data-pk='{$model->id}'
                    data-url='{$route}'
                    data-value='{$model->id_materia}'
                    data-title='Selecciona una materia'>
                    {$model->materia->nombre}
                </a>";
            })
            ->addColumn('nombre_profesor', function ($model) {
                $route = route('grupos.actualizar_materias_xeditable');
                return "
                <a  class='editable_id_profesor editable'
                    data-type='select2'
                    data-name='id_profesor'
                    data-pk='{$model->id}'
                    data-url='{$route}'
                    data-value='{$model->id_profesor}'
                    data-title='Selecciona un profesor'>
                    {$model->profesor->full_name}
                </a>";
            })
            ->editColumn('semanas', function ($model) {
                $route = route('grupos.actualizar_materias_xeditable');

                return "<a class='editable_semanas editable'
                    data-type='text'
                    data-name='semanas'
                    data-pk='{$model->id}'
                    data-url='{$route}'
                    data-value='{$model->materia->semanas}'
                    data-placeholder='Horas por semana'> {$model->materia->semanas} </a>";
            })
            ->addColumn('buttons', 'grupos.datatables._buttons_materias')
            ->addColumn('buttons_lista', 'grupos.datatables._buttons_materias_lista')
            ->rawColumns(['orden','nombre_materia', 'nombre_profesor', 'semanas', 'buttons','buttons_lista'])
            ->make(true);
    }

    # NOTE: ASIGNACION DE ALUMNOS

    public function asignar_alumnos(Grupo $grupo)
    {
        abort_unless(Auth::user()->can('asignar_alumnos'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        $grupo->load(['days','especialidad','alumnos']);

        return view('grupos.asignar_alumnos', compact('grupo'));
    }

    public function guardar_alumnos(Grupo $grupo, Request $request, PagoInscripcionDocumentosService $pids,PagoColegiaturaDocumentosService $pcds)
    {
        $rules = [
            'id_alumno' =>  'required',
        ];

        $this->validate($request, $rules);

        $grupo->alumnos()->attach($request->input('id_alumno'));

        $alumno = Alumno::with(['especialidades','apoyos_especiales'])->findOrFail($request->input('id_alumno'));

        $alumno->load(['grupos']);

        $pids->setAlumno($alumno);
        $pcds->setAlumno($alumno);

        $forma_pago = 'semanal';
        $especialidad = $grupo->especialidad;

        if($alumno->especialidades->where('id_especialidad',$grupo->id_especialidad)->count() == 0){
            // throw ValidationException::withMessages(['mensaje' => $monto_pactado]);
                // SE GENERA EL REGISTRO DE ESTE ALUMNO EN LA ESPECIALIDAD SELECCIONADA. ESTE SERA SU REGISTRO DE ESPECIALIDAD
                // CENTRAL A PARTIR DE AQUI
                // SE GUARDA EL MONTO PACTADO EN EL CORE DEL ALUMNO DONDE SE VA A RESPETAR ESTA CANTIDAD 
                
                $monto_pactado = $grupo->precio_semanal;
                $monto_pronto_pago_pactado = 0;

                $alumno_especialidad = $alumno->especialidades()->attach($especialidad->id, [
                    'fecha_inicio' => date('Y-m-d'),
                    'forma_pago' => 'semanal',
                    'semanas_cursar' => $especialidad->materias->sum('semanas'),
                    'monto' => $monto_pactado,
                    'monto_pronto_pago' => $monto_pronto_pago_pactado,
                    'semanas_cursadas' => 0,
                    'semanas_pagadas' => 0,
                    'status' => 'Activo',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }

        $alumno = Alumno::with(['especialidades','apoyos_especiales'])->findOrFail($request->input('id_alumno'));
        $especialidad = $alumno->load('especialidades')->especialidades->where('id',$grupo->id_especialidad)->first();



        switch ($forma_pago) {
            case config('alumnos.forma_pago.mensual', 'mensual'):
                
                break;

            case config('alumnos.forma_pago.semanal', 'semanal'):
                $pids->inscripcion($grupo, $grupo->precio_inscripcion);
                $pcds->semanal($especialidad, $grupo);
                break;
        }

        return response()->json([
            'success' => true,
            'message' => 'Alumno asociado correctamente',
        ]);
    }

    public function actualizar_alumnos_xeditable(Request $request)
    {
        $alumno_grupo = AlumnoGrupo::find($request->pk);
        $alumno_grupo[$request->name] = $request->value;
        $alumno_grupo->save();

        $alumno_grupo->load(['alumno']);

        return response()->json([
            'alumno_grupo' => $alumno_grupo
        ]);
    }

    public function eliminar_alumnos(Request $request)
    {
        $rules = [
            'id_alumno_grupo'            => 'required',
        ];

        $this->validate($request, $rules);

        $alumno_grupo = AlumnoGrupo::findOrFail($request->input('id_alumno_grupo'));
        $alumno = $alumno_grupo->alumno;

        $alumno->pagos()
            ->where('id_grupo', $alumno_grupo->id_grupo)
            ->where('status', config('pagos.status.Pendiente'))
            ->delete();

        $alumno_grupo->delete();

        return response()->json([
            'message' => 'Alumno removido correctamente'
        ]);
    }

    public function datatables_alumnos(Request $request)
    {
        $query = AlumnoGrupo::query()->select('alumnos_grupos.*')
            ->when($request->input('id_grupo'), function ($q, $grupo) {
                $q->where('id_grupo', $grupo);
            })->where('alumnos_grupos.status','=','Inscrito')->with(['alumno']);

        return DataTables::eloquent($query)
            ->addColumn('nombre_alumno', function ($model) {
                $route = route('alumnos.show', $model->id_alumno);

                return "
                <a target='_blank'  href='{$route}' >
                    {$model->alumno->full_name} 
                </a>";
            })
            ->editColumn('fecha_inicio', function($model){
                return $model->fecha_inicio->format('d-m-Y');
            })
            ->addColumn('buttons', 'grupos.datatables._buttons_alumnos')
            ->rawColumns(['nombre_alumno', 'buttons'])
            ->make(true);
    }

    //traer info para ver inscripcion en
    public function traer_info(Request $request)
    {
        $grupo = Grupo::with('especialidad')->find($request->id_grupo);
        $alumno = Alumno::select(['id','nombres','apellido_paterno','apellido_materno'])->find($request->id_alumno);
        $grupo_alu = Grupo::with(['alumnos'])->find($request->id_grupo);

        if($grupo->max_alumnos){
            $cupo = $grupo->max_alumnos - $grupo_alu->alumnos->count() ;
        }else{
            $cupo = 1;
        }
        
        return response()->json([
            'grupo' => $grupo,
            'alumno' => $alumno,
            'cupo' => $cupo,
        ]);
    }

    public function cronograma(Grupo $grupo)
    {
        abort_unless(Auth::user()->can('consultar_grupo'), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

        return view('grupos.cronograma', compact('grupo'));
    }

    public function finalizar_grupo(Request $request)
    {
        $grupo = Grupo::find($request->id);
        $grupo->status = 'Finalizado';
        $grupo->save();

        // OPERACIONES: sumar | restar
        // CAMPOS: inicios | reingresos | cambios_horarios_plus | bajas | cambios_horarios_minus | fin_curso
        $grupo->actualizarReporteDesercion('sumar','fin_curso',$grupo->alumnos->count());
        $grupo = Grupo::find($request->id);

        return response()->json([
            'grupo' => $grupo
        ]);
    }

    public function activar_grupo(Request $request)
    {
        $grupo = Grupo::find($request->id);
        $grupo->status = 'Activo';
        $grupo->save();

        // OPERACIONES: sumar | restar
        // CAMPOS: inicios | reingresos | cambios_horarios_plus | bajas | cambios_horarios_minus | fin_curso
        $grupo->actualizarReporteDesercion('restar','fin_curso',$grupo->alumnos->count());

        $grupo = Grupo::find($request->id);

        return response()->json([
            'grupo' => $grupo
        ]);
    }


    public function lista_asistencia($id_grupo, $id_materia,Request $request)
    {   
        
        $grupo = Grupo::find($id_grupo);
        $grupo->load(['alumnos', 'especialidad','materias']);
        
        if($id_materia != 'no'){
            $materia = $grupo->materias->where('id',$id_materia)->first();
            $profesor = $materia->pivot->load('profesor')->profesor;
        }else{
            $materia = collect();
            $profesor = collect();
        }
        

        $sucursal = optional(session('sucursal'));

        # GENERACION DE SEMANAS
        $now = now();
        $semanas = [];
        foreach (range(0, 11) as $semana) {
            $semanas[] = $now->copy()->addWeek($semana)->week;
        }

        $dias = $grupo->days->unique('dia')->pluck('dia');

        $array_dias = [
            'lunes' => 'L',
            'martes' => 'M',
            'miercoles' => 'Mi',
            'jueves' => 'J',
            'viernes' => 'V',
            'sabado' => 'S',
            'domingo' => 'D',
        ];

        $array_carbon = [
            'lunes' => 'MONDAY',
            'martes' => 'TUESDAY',
            'miercoles' => 'WEDNESDAY',
            'jueves' => 'THURSDAY',
            'viernes' => 'FRIDAY',
            'sabado' => 'SATURDAY',
            'domingo' => 'SUNDAY',
        ];

        $dias_semana = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

        PDF::setOptions(['isPhpEnabled' => true]);

        $mostrar_telefono = $request->has('mostrar-telefono')
            && $request->filled('mostrar-telefono')
            && $request->input('mostrar-telefono') == 'si';


        $pdf = PDF::loadView('grupos.lista_asistencia', [
            'grupo'             => $grupo,
            'sucursal'          => $sucursal,
            'semanas'           => $semanas,
            'dias_semana'       => $dias_semana,
            'mostrar_telefono'  => $mostrar_telefono,
            'dias' => $dias,
            'array_carbon' => $array_carbon,
            'array_dias' => $array_dias,
            'profesor' => $profesor,
            'materia' => $materia,

        ]);

        return $pdf->stream('lista_asistencia.pdf');
    }

    public function guardar_precio(Request $request){
        
        $precio = Precio::where('id_grupo','=',$request->id_grupo)->where('tipo','=',$request->tipo)->whereNull('fecha_final')->update(['fecha_final'=>date('Y-m-d H:i:s')]);

        $grupo = Grupo::find($request->id_grupo);
        $sucursal = Session::get('sucursal');

        if($request->tipo == 'Inscripción'){
            $precio = Precio::create([
                'tipo' => $request->tipo,
                'id_grupo' => $request->id_grupo,
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
                'id_grupo' => $request->id_grupo,
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
                'id_grupo' => $request->id_grupo,
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

        Session::flash('message','Se dio de alta con éxito el precio');
        return redirect()->back();
       
    }
}
