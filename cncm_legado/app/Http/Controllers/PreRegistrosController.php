<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\User;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\ApoyoInscripcion;
use App\Models\Inscripcion;
use App\Models\InscripcionRecomendacion;


use Illuminate\Http\Request;
use App\Services\FacturacionService;
use App\Services\PagoInscripcionService;
use App\Services\PagoInscripcionDocumentosService;
use App\Services\PagoColegiaturaDocumentosService;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class PreRegistrosController extends Controller
{
    public function index()
    {
        return view('alumnos.pre_registro.index');
    }

    public function datatables(Request $request)
    {
        $query = Alumno::query()
            ->when($request->input('id_sucursal'),function($q,$id_sucursal){
                $q->where('id_sucursal',$id_sucursal);
            })->where(function($q){
                $q->whereNull('status');
                $q->orWhere('status',config('alumnos.status.Pre-Registro'));
            })
            ->when($request->input('id_asesor_educativo'),function($q,$id){
                if(!Auth::user()->canAny(['convertir_pre_registro_alumno'])){
                    $q->where('id_asesor_educativo',$id);
                }
            })
            ->with(['asesor_educativo','especialidad']);

        return DataTables::eloquent($query)
            ->addColumn('nombre_asesor',function($model){
                return $model->asesor_educativo->fullname;
            })
            ->addColumn('nombre_alumno',function($model){
                return "<a href=".route('pre-registro-alumnos.show', $model->id).">{$model->nombres} {$model->apellido_paterno} {$model->apellido_materno}</a>";
            })
            ->addColumn('fecha_nacimiento',function($model){
                return optional($model->fecha_nacimiento)->format('d/m/Y');
            })
            ->addColumn('saldo',function($model){
                return "$ {$model->saldo}";
            })
            ->addColumn('buttons', 'alumnos.pre_registro.datatables._buttons')
            ->editColumn('created_at', function($model){
                return optional($model->created_at)->format('d/m/Y');
            })
            ->rawColumns(['buttons','nombre_alumno'])
            ->make(true);
    }

    public function show(Alumno $alumno)
    {
        return view('alumnos.pre_registro.show',compact('alumno'));
    }

    public function create(FacturacionService $facturacionService)
    {
        return view('alumnos.pre_registro.create',[
            'alumno'            => new Alumno,
            'especialidades'    => Especialidad::query()->pluck('nombre','id')->sort()->prepend('Selecciona una especialidad',''),
            'cfdis'             => []
        ]);

    }

    public function store(Request $request)
    {
        $rules = [
            'id_sucursal'           => 'required',
            'foto'                  => 'nullable',
            'nombres'               => 'required',
            'apellido_paterno'      => 'required',
            'apellido_materno'      => 'required',
            'edad'                  => 'nullable',
            'fecha_nacimiento'      => 'nullable',
            'domicilio'             => 'nullable',
            'colonia'               => 'nullable',
            'municipio'             => 'nullable',
            'telefono'              => 'nullable',
            'celular'               => 'nullable',
            'email'                 => 'nullable',
            'codigo_postal'         => 'nullable',
            'ocupacion'             => 'nullable',
            'grado_estudios'        => 'nullable',
            'otro_grado_estudios'   => 'nullable',
            'tutor'                 => 'nullable',
            'id_especialidad'       => 'nullable',
            'otra_especialidad'     => 'nullable',
            'escuela_procedencia'   => 'nullable',
            'objetivo_inscripcion'  => 'nullable',
            'enfermedad_cronica'    => 'nullable',
            'solicitud_factura'     => 'nullable',
            'id_asesor_educativo'   => 'required',

            # DATOS DE FACTURACION
            'razon_social'          => 'nullable',
            'rfc'                   => 'nullable',
            'cfdi'                  => 'nullable',
            'curp'                  => 'nullable',
            'telefono_general'      => 'nullable',
            'correo_general'        => 'nullable',
            'domicilio_fiscal'      => 'nullable',

            'observaciones'         => 'nullable',
            'id_grupo'              => 'nullable',
            'status'                => 'required',
        ];

        $sucursal = optional(session('sucursal'));

        $request->request->add([
            'id_sucursal'         => $sucursal->id,
            'id_asesor_educativo' => auth()->id(),
            'solicitud_factura'   => $request->has('solicitud_factura'),
            'status'              => config('alumnos.status.Pre-Registro'),
        ]);

        $data = $request->validate($rules);

        $alumno = Alumno::create($data);
        Log::alert('Usuario '.Auth::user()->fullname.' creó el pre registro '.$alumno->numero_control_fullname);

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');

            $image = Image::make($file);

            $nombre_foto = $file->getClientOriginalName();

            if (!Storage::exists('alumnos_foto')) {
                Storage::makeDirectory('usuarios_foto');
            }

            if (!Storage::exists("alumnos_foto/{$alumno->id}")) {
                Storage::makeDirectory("alumnos_foto/{$alumno->id}");
            }

            $path = storage_path() . "/app/alumnos_foto/{$alumno->id}/";
            $image->save($path . $nombre_foto);

            $alumno->foto = $nombre_foto;
            $alumno->save();
        }

        return redirect()->route('pre-registro-alumnos.index')->with([
            'message' => 'Se agregó el alumno con éxito'
        ]);
    }

    public function edit(Alumno $alumno,FacturacionService $facturacionService)
    {
        return view('alumnos.pre_registro.edit', [
            'alumno'            => $alumno,
            'especialidades'    => Especialidad::query()->pluck('nombre','id')->sort()->prepend('Selecciona una especialidad',''),
            'cfdis'             => $facturacionService->usosCfdi()->prepend('Selecciona un cfdi',''),

        ]);
    }

    public function update(Request $request, Alumno $alumno)
    {
        $rules = [
            'id_sucursal'           => 'required',
            'como_supiste_nosotros' => 'nullable',
            'nuevo_numero_control'  => 'nullable',
            'foto'                  => 'nullable',
            'nombres'               => 'required',
            'apellido_paterno'      => 'required',
            'apellido_materno'      => 'required',
            'edad'                  => 'nullable',
            'fecha_nacimiento'      => 'nullable',
            'domicilio'             => 'nullable',
            'colonia'               => 'nullable',
            'municipio'             => 'nullable',
            'telefono'              => 'nullable',
            'celular'               => 'nullable',
            'email'                 => 'nullable',
            'codigo_postal'         => 'nullable',
            'ocupacion'             => 'nullable',
            'grado_estudios'        => 'nullable',
            'otro_grado_estudios'   => 'nullable',
            'tutor'                 => 'nullable',
            'id_especialidad'       => 'nullable',
            'otra_especialidad'     => 'nullable',
            'escuela_procedencia'   => 'nullable',
            'objetivo_inscripcion'  => 'nullable',
            'enfermedad_cronica'    => 'nullable',
            'solicitud_factura'     => 'nullable',

            # DATOS DE FACTURACION
            'razon_social'          => 'nullable',
            'rfc'                   => 'nullable',
            'cfdi'                  => 'nullable',
            'curp'                  => 'nullable',
            'telefono_general'      => 'nullable',
            'correo_general'        => 'nullable',
            'domicilio_fiscal'      => 'nullable',

            'observaciones'         => 'nullable',
            'forma_pago'            => 'nullable',
            'status'                => 'nullable',
        ];

        $sucursal = optional(session('sucursal'));

        $request->request->add([
            'id_sucursal'         => $sucursal->id,
            'solicitud_factura'   => $request->has('solicitud_factura'),
        ]);

        $data = $this->validate($request, $rules);
        $alumno->fill($data);
        Log::alert('Usuario '.Auth::user()->fullname.' actualizó el pre registro '.$alumno->numero_control_fullname);

        if ($request->hasFile('foto')) {
            $file = $request->file('foto');

            $image = Image::make($file);

            $nombre_foto = $file->getClientOriginalName();

            if (!Storage::exists('alumnos_foto')) {
                Storage::makeDirectory('usuarios_foto');
            }

            if (!Storage::exists("alumnos_foto/{$alumno->id}")) {
                Storage::makeDirectory("alumnos_foto/{$alumno->id}");
            }

            $path = storage_path() . "/app/alumnos_foto/{$alumno->id}/";
            $image->save($path . $nombre_foto);

            $alumno->foto = $nombre_foto;
            $alumno->save();
        }   

        $alumno->save();



        return redirect()->route('pre-registro-alumnos.index')->with([
            'message' => 'El alumno se edito con éxito'
        ]);
    }

    public function formulario_inscripcion(Alumno $alumno,FacturacionService $facturacionService)
    {
        if($alumno->status == config('alumnos.status.Alumno')) {
            return redirect()
            ->route('pre-registro-alumnos.index')
            ->with(['error' => 'El alumno ya ha sido inscrito']);
        }

        $usuarios_autorizados = User::with('roles.permissions')->whereHas('roles.permissions', function($q){
            return $q->where('name','=','autorizar_apoyos_especiales_en_inscripcion');
        })->get();

        // dd($usuarios_autorizados);

        return view('alumnos.pre_registro.inscribir', [
            'alumno'            => $alumno,
            'especialidades'    => Especialidad::query()->pluck('nombre','id')->sort()->prepend('Selecciona una especialidad',''),
            'cfdis'             => [],
            'asesores'          => User::query()->get()->pluck('fullname','id')->sort()->prepend('CNCM',''),
            'usuarios_autorizados' => $usuarios_autorizados->pluck('fullname','id')->sort()->prepend('Selecciona un usuario',''),
        ]);
    }

    public function inscribir(Request $request, $id,PagoInscripcionDocumentosService $pids, PagoColegiaturaDocumentosService $pcds)
    {
        $rules = [
            'id_sucursal'           => 'required',
            'como_supiste_nosotros' => 'nullable',
            'nuevo_numero_control'  => 'required',
            'foto'                  => 'nullable',
            'nombres'               => 'required',
            'apellido_paterno'      => 'required',
            'apellido_materno'      => 'required',
            'edad'                  => 'required',
            'fecha_nacimiento'      => 'required',
            'domicilio'             => 'required',
            'colonia'               => 'required',
            'municipio'             => 'required',
            'telefono'              => 'required',
            'celular'               => 'required',
            'email'                 => 'required',
            'codigo_postal'         => 'required',
            'ocupacion'             => 'required',
            'grado_estudios'        => 'required',
            'otro_grado_estudios'   => 'nullable',
            'tutor'                 => 'required',
            'id_especialidad'       => 'required',
            'otra_especialidad'     => 'nullable',
            'escuela_procedencia'   => 'nullable',
            'objetivo_inscripcion'  => 'required',
            'enfermedad_cronica'    => 'required',
            'solicitud_factura'     => 'nullable',
            'id_asesor_educativo'   => 'nullable',
            # DATOS DE FACTURACION
            'razon_social'          => 'nullable',
            'rfc'                   => 'nullable',
            'cfdi'                  => 'nullable',
            'curp'                  => 'nullable',
            'telefono_general'      => 'nullable',
            'correo_general'        => 'nullable',
            'domicilio_fiscal'      => 'nullable',

            'observaciones'         => 'nullable',
            'forma_pago'            => 'required',
            'status'                => 'required',
        ];

        $sucursal = optional(session('sucursal'));
        
        // SI LE PONEN PRECIO DE INSCRIPCIÓN SE TIENE QUE VALIDAR QUE LO AUTORICE ALGUIEN

        if($request->apoyo_especial == "true"){
            $usuario = User::find($request->id_usuario_autoriza);
            
            $password = $request->password;
            if(!Hash::check($password,$usuario->password))
            {
                throw ValidationException::withMessages(['mensaje' => 'Credenciales de autorización incorrectas']);
            }
        }

        if($request->apoyo_por_recomendacion == "true"){
            $usuario = User::find($request->id_usuario_autoriza);
            
            $password = $request->password;
            if(!Hash::check($password,$usuario->password))
            {
                throw ValidationException::withMessages(['mensaje' => 'Credenciales de autorización incorrectas']);
            }
        }

        if($request->cupo <= 0){
            $usuario = User::find($request->id_usuario_autoriza);
            
            $password = $request->password;
            if(!Hash::check($password,$usuario->password))
            {
                throw ValidationException::withMessages(['mensaje' => 'Credenciales de autorización incorrectas']);
            }
        }

        try {
            $alumno = Alumno::find($id);
            $request->request->add([
                'id_sucursal'           => $sucursal->id,
                'solicitud_factura'     => $request->has('solicitud_factura'),
                'status'                => config('alumnos.status.Alumno'),
                'nuevo_numero_control'  => generar_folio_alumno($sucursal->id),
            ]);

            $data = $this->validate($request, $rules);
            $alumno->fill($data);

            if ($request->hasFile('foto')) {
                $file = $request->file('foto');

                $image = Image::make($file);

                $nombre_foto = $file->getClientOriginalName();

                if (!Storage::exists('alumnos_foto')) {
                    Storage::makeDirectory('usuarios_foto');
                }

                if (!Storage::exists("alumnos_foto/{$alumno->id}")) {
                    Storage::makeDirectory("alumnos_foto/{$alumno->id}");
                }

                $path = storage_path() . "/app/alumnos_foto/{$alumno->id}/";
                $image->save($path . $nombre_foto);

                $alumno->foto = $nombre_foto;
                $alumno->save();
            }

            $alumno->save();

            if ($request->has('id_grupo')) {

                $grupo_inscripcion = Grupo::findOrFail($request->input('id_grupo'));
                $especialidad = Especialidad::with('materias')->findOrFail($request->id_especialidad);

                $alumno->grupos()->attach($request->input('id_grupo'),['fecha_inicio' => $request->input('fecha_inicio')]);


                $pids->setRequest($request);
                $pids->setAlumno($alumno);
                $pcds->setRequest($request);
                $pcds->setAlumno($alumno, $grupo_inscripcion);

                switch ($request->input('forma_pago')) {
                    case config('alumnos.forma_pago.mensual','mensual'):
                        $pids->inscripcion($grupo_inscripcion, $grupo_inscripcion->precio_inscripcion);
                        // GENERA DOCUMENTOS MENSUALES
                        $pcds->mensual($especialidad, $grupo_inscripcion);

                        $monto_pactado = $grupo_inscripcion->precio_mensualidad;
                        $monto_pronto_pago_pactado = $grupo_inscripcion->precio_mensualidad_pronto_pago;
                    break;
                    case config('alumnos.forma_pago.semanal','semanal'):
                        $pids->inscripcion($grupo_inscripcion, $grupo_inscripcion->precio_inscripcion);
                        // GENERA DOCUMENTOS SEMANALES
                        $pcds->semanal($especialidad, $grupo_inscripcion);

                        $monto_pactado = $grupo_inscripcion->precio_semanal;
                        $monto_pronto_pago_pactado = 0;
                    break;
                }
                
                // throw ValidationException::withMessages(['mensaje' => $monto_pactado]);
                // SE GENERA EL REGISTRO DE ESTE ALUMNO EN LA ESPECIALIDAD SELECCIONADA. ESTE SERA SU REGISTRO DE ESPECIALIDAD
                // CENTRAL A PARTIR DE AQUI
                // SE GUARDA EL MONTO PACTADO EN EL CORE DEL ALUMNO DONDE SE VA A RESPETAR ESTA CANTIDAD 
                $alumno_especialidad = $alumno->especialidades()->attach($especialidad->id, [
                    'fecha_inicio' => $request->input('fecha_inicio'),
                    'forma_pago' => $request->forma_pago,
                    'semanas_cursar' => $especialidad->materias->sum('semanas'),
                    'monto' => $monto_pactado,
                    'monto_pronto_pago' => $monto_pronto_pago_pactado,
                    'semanas_cursadas' => 0,
                    'semanas_pagadas' => 0,
                    'status' => 'Activo',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                
               
               
                #SE CREA EL APOYO A LA INSCRIPCION DEL ALUMNO PARA EL REPORTE
                if($request->apoyo_especial == "true"){
                    $grupo = Grupo::find($request->input('id_grupo'));

                    $apoyo = ApoyoInscripcion::create([
                        'id_alumno' => $alumno->id,
                        'id_especialidad'  => $especialidad->id,
                        'apoyo'     => $request->precio_inscripcion,
                        'id_usuario'=> Auth::id(),
                        'id_usuario_autoriza'=> $request->id_usuario_autoriza,
                        'motivo'    =>$request->motivo,
                    ]);
                }
                
                 #SE CREA EL APOYO A LA INSCRIPCION DEL ALUMNO PARA EL REPORTE
                 if($request->apoyo_por_recomendacion == "true"){
                    $grupo = Grupo::find($request->input('id_grupo'));
                    $inscripcion_recomendacion = InscripcionRecomendacion::create([
                        'id_alumno_recomendado' => $alumno->id,
                        'id_alumno_recomendo' => $request->id_alumno_recomendo,
                        'id_especialidad'  => $especialidad->id,
                        'id_documento_aplicado' => null,
                        'id_autorizo'=> $request->id_usuario_autoriza,
                        'fecha'=> date('Y-m-d'),
                        'id_sucursal'    =>$sucursal->id,
                        'monto' => null,
                    ]);

                    
                    $alumno_recomendo = Alumno::find($request->id_alumno_recomendo);
                    $documentos = $alumno_recomendo->documentos()->pendientes()->where('id_especialidad', '=', $request->id_especialidad)->orderBy('fecha_limite', 'asc')->get();

                    $monto_semanal = $especialidad->precio_semanal;
                    $inscripcion_recomendacion->monto = $monto_semanal;
                    $inscripcion_recomendacion->save();

                } 
                

                // OPERACIONES: sumar | restar
                // CAMPOS: inicios | reingresos | cambios_horarios_plus | bajas | cambios_horarios_minus | fin_curso
                Log::alert('Usuario '.Auth::user()->fullname.' inscribio a el pre registro '.$alumno->numero_control_fullname.' en el grupo '.$grupo_inscripcion->nombre);
                $grupo_inscripcion->actualizarReporteDesercion('sumar','inicios',1);

                #Se registra la inscripcion de este alumno para el reporte de asesores
                $inscripcion = Inscripcion::create([
                    'id_alumno' => $alumno->id,
                    'id_grupo' => $grupo_inscripcion->id,
                    'id_asesor' => $alumno->id_asesor_educativo,
                    'id_sucursal' => $sucursal->id,
                    'fecha' => date('Y-m-d H:i:s'),
                    'fecha_inicio_grupo' => $request->fecha_inicio
                ]);

            }
        } catch (\Throwable $th) {

            Log::error('Ocurrio el siguiente error:' .$th->getMessage().' en la línea '.$th->getLine().' en la clase '.$th->getFile());

            return response()->json([
                'success'   => false,
                'message'   => 'Ocurrio el siguiente error:' .$th->getMessage().' en la línea '.$th->getLine().' en la clase '.$th->getFile(),
            ]);

            Session::flash('error','Ocurrio el siguiente error: '.$th);
            return redirect()->back();
            
        }


        if($request->ajax()) {
            return response()->json([
                'success'   => true,
                'message'   => 'El alumno se inscribio con éxito',
                'redirect'  => route('alumnos.show',$alumno),
                'pago'      => $request->has('id_grupo') ? Pago::first()->where('id_alumno',$alumno->id)->latest()->first() : ''
            ]);
        }

        return redirect()->route('pre-registro-alumnos.index')->with([
            'message' => 'El alumno se inscribio con éxito'
        ]);
    }

    public function destroy(Alumno $alumno, Request $request)
    {
        $alumno->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El alumno fue eliminado con éxito',
            ]);
        }

        return redirect()->route('pre-registro-alumnos.index')->with([
            'message' => 'El alumno fue eliminado con éxito'
        ]);
    }
}
