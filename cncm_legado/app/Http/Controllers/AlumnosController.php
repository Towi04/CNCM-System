<?php

namespace App\Http\Controllers;

#MODELS
use App\Models\User;
use App\Models\Grupo;
use App\Models\Alumno;
use App\Models\AlumnoGrupo;
use App\Models\AlumnoPago;
use App\Models\Documento;
use App\Models\Especialidad;
use App\Models\Pago;
use App\Models\Nota;
use App\Models\Alerta;
use App\Models\ApoyoInscripcion;
use App\Models\Inscripcion;
use App\Models\AlumnoEspecialidad;
use App\Models\Descuento;
use App\Models\ApoyoEspecial;


#FACADES
use App\Services\FacturacionService;
use App\Services\PagoInscripcionService;
use App\Services\PagoColegiaturaDocumentosService;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use Symfony\Component\HttpFoundation\Response as HTTPMessages;
use Illuminate\Support\Facades\Log;

class AlumnosController extends Controller
{
    public function index()
    {
        $sucursal = optional(session('sucursal'));
        $asesores = User::whereHas('sucursales', function($q)use($sucursal){
            return $q->where('id','=',$sucursal->id);
        })->get()->pluck('fullname', 'id')->sort();

        $dias_semana = [
            'lunes'     => 'Lunes',
            'martes'    => 'Martes',
            'miercoles' => 'Miércoles',
            'jueves'    => 'Jueves',
            'viernes'   => 'Viernes',
            'sabado'    => 'Sábado',
            'domingo'   => 'Domingo',
        ];

        return view('alumnos.index', compact('asesores','dias_semana'));
    }

    public function datatables(Request $request)
    {
        $query = Alumno::query()
            ->where('status', config('alumnos.status.Alumno'))
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })
            ->when($request->input('forma_pago'), function ($q, $forma_pago) {
                $q->where('forma_pago', $forma_pago);
            })->when($request->input('alumnos_no_grupos'), function ($q, $alumnos_no_grupos) {
                if ($alumnos_no_grupos == 'true') {
                    $q->whereRaw(DB::raw('id not in (Select id_alumno from alumnos_grupos)'));
                }
            })->with('asesor_educativo');

        return DataTables::eloquent($query)
            ->addColumn('nombre_alumno', function ($model) {
                return "<a href=" . route('alumnos.show', $model->id) . ">{$model->nombres} {$model->apellido_paterno} {$model->apellido_materno}</a>";
            })
            ->addColumn('nombre_asesor', function ($model) {


                    $txt = "";
                    $txt .= "<a ";
                    if(Auth::user()->can('actualizar_asesor_alumno')){
                        $txt .= " class='editable_asesor' ";
                    }
                    $txt .=     "data-pk='".$model->id."' ";
                    $txt .=     "data-name='id_asesor_educativo' ";
                    $txt .=     "data-url='".route("alumnos.actualizar_informacion")."' ";
                    $txt .=     "data-type='select' ";
                    $txt .=     "data-value='". $model->id_asesor_educativo."'>";
                    $txt .=     $model->asesor_educativo->fullname;
                    $txt .= "</a>";
    
                    return $txt;
    
    

                return $model->asesor_educativo->full_name;
            })
            ->addColumn('no_grupos', function ($model) {
                return $model->grupos->count();
            })
            ->addColumn('buttons', 'alumnos.datatables._buttons')

            ->rawColumns(['buttons', 'nombre_alumno','nombre_asesor'])
            ->make(true);
    }

    public function show(Alumno $alumno)
    {
        $alumno->load(['especialidad', 'ventas.partidas.producto']);

        return view('alumnos.show', compact('alumno'));
    }

    public function create(FacturacionService $facturacionService)
    {
        return view('alumnos.create', [
            'alumno'            => new Alumno,
            'asesores'          => User::query()->get()->pluck('fullname', 'id')->sort(),
            'especialidades'    => Especialidad::query()->pluck('nombre', 'id')->sort()->prepend('Selecciona una especialidad', ''),
            'cfdis'             => $facturacionService->usosCfdi()->prepend('Selecciona un cfdi', '')
        ]);
    }

    public function store(Request $request, PagoInscripcionService $pis)
    {
        $rules = [
            'id_sucursal'           => 'required',
            'como_supiste_nosotros' => 'nullable',
            'nuevo_numero_control'  => 'required',
            'foto'                  => 'nullable',
            'nombres'               => 'required',
            'apellido_paterno'      => 'nullable',
            'apellido_materno'      => 'nullable',
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
            'tutor'                 => 'nullable',
            'id_especialidad'       => 'required',
            'otra_especialidad'     => 'nullable',
            'escuela_procedencia'   => 'nullable',
            'objetivo_inscripcion'  => 'required',
            'enfermedad_cronica'    => 'nullable',
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
            'id_grupo'              => 'nullable',
            'forma_pago'            => 'required',
            'status'                => 'required',
        ];

        $sucursal = optional(session('sucursal'));

        $request->request->add([
            'id_sucursal'           => $sucursal->id,
            'nuevo_numero_control'  => generar_folio_alumno($sucursal->id),
            'solicitud_factura'     => $request->has('solicitud_factura'),
            'status'                => config('alumnos.status.Alumno'),
        ]);

        $data = $request->validate($rules);

        $alumno = Alumno::create($data);

        Log::alert('Usuario '.Auth::user()->fullname.' creó alumno '.$alumno->fullname);

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

        if ($request->has('id_grupo')) {
            $alumno->grupos()->attach($request->input('id_grupo'));
            $grupo_inscripcion = Grupo::findOrFail($request->input('id_grupo'));

            $pis->setAlumno($alumno);

            switch ($request->input('forma_pago')) {
                case config('alumnos.forma_pago.mensual', 'mensual'):
                    $pis->mensualPorGrupo($grupo_inscripcion);
                    break;

                case config('alumnos.forma_pago.semanal', 'semanal'):
                    $pis->semanalPorGrupo($grupo_inscripcion);
                    break;
            }
        }

        return redirect()->route('alumnos.index')->with([
            'message' => 'Se agregó el alumno con éxito'
        ]);
    }

    public function edit(Alumno $alumno, FacturacionService $facturacionService)
    {
        abort_unless(Auth::user()->canany(['editar_alumno', 'editar_datos_fiscales']), HTTPMessages::HTTP_FORBIDDEN, __('Forbidden'));

       
        return view('alumnos.edit', [
            'alumno'            => $alumno,
            'especialidades'    => Especialidad::query()->pluck('nombre', 'id')->sort()->prepend('Selecciona una especialidad', ''),
            'asesores'          => User::query()->get()->pluck('fullname', 'id')->sort()->prepend('CNCM', ''),
            'cfdis'             => $facturacionService->usosCfdi()->prepend('Selecciona un cfdi', ''),
        ]);
    }

    public function update(Request $request, Alumno $alumno)
    {
        if (Auth::user()->can('editar_alumno')) {
            $rules = [
                'id_sucursal'           => 'required',
                'numero_control'        => 'required',
                'foto'                  => 'nullable',
                'nombres'               => 'required',
                'apellido_paterno'      => 'nullable',
                'apellido_materno'      => 'nullable',
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
                'tutor'                 => 'nullable',
                'id_especialidad'       => 'required',
                'otra_especialidad'     => 'nullable',
                'escuela_procedencia'   => 'nullable',
                'objetivo_inscripcion'  => 'required',
                'enfermedad_cronica'    => 'nullable',
                'solicitud_factura'     => 'nullable',
                'id_asesor_educativo'   => 'nullable',

                # DATOS DE FACTURACION
                'razon_social'          => 'required_if:solicitud_factura,1',
                'rfc'                   => 'required_if:solicitud_factura,1',
                'cfdi'                  => 'required_if:solicitud_factura,1',
                'curp'                  => 'required_if:solicitud_factura,1',
                'telefono_general'      => 'nullable',
                'correo_general'        => 'nullable',
                'domicilio_fiscal'      => 'nullable',

                'observaciones'         => 'nullable',
                'forma_pago'            => 'required',
            ];
        } else {
            $rules = [
                # DATOS DE FACTURACION
                'solicitud_factura'      => 'nullable',
                'razon_social'          => 'required_if:solicitud_factura,1',
                'rfc'                   => 'required_if:solicitud_factura,1',
                'cfdi'                  => 'required_if:solicitud_factura,1',
                'curp'                  => 'required_if:solicitud_factura,1',
                'telefono_general'      => 'required_if:solicitud_factura,1',
                'correo_general'        => 'required_if:solicitud_factura,1',
                'domicilio_fiscal'      => 'nullable',
            ];
        }

        $request->request->add([
            'id_sucursal'         => optional(session('sucursal'))->id,
            'solicitud_factura'   => $request->has('solicitud_factura'),
        ]);

        // dd($request);
        $data = $this->validate($request, $rules);
        $alumno->fill($request->all());
        
        Log::alert('Usuario '.Auth::user()->fullname.' actualizo usuario '.$alumno->numero_control_fullname);

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
            // resize the image to a width of 300 and constrain aspect ratio (auto height)
            $image->resize(780, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $image->save($path . $nombre_foto);


            $alumno->foto = $nombre_foto;
            $alumno->save();
        }

        $alumno->save();
        // dd($alumno);

        return redirect()->route('alumnos.index')->with([
            'message' => 'Se actualizó el alumno con éxito'
        ]);
    }

    public function destroy(Alumno $alumno, Request $request)
    {
        $alumno->grupos()->detach();
        $alumno->pagos()->where('status', config('pagos.status.Pendiente'))->delete();
        $alumno->delete();

        Log::alert('Usuario '.Auth::user()->fullname.' eliminó usuario '.$alumno->nuevo_numero_control.' '.$alumno->fullname);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'El alumno fue eliminado con éxito',
            ]);
        }

        return redirect()->route('alumnos.index')->with([
            'message' => 'El alumno fue eliminado con éxito'
        ]);
    }

    public function traer_alumnos_select2(Request $request)
    {
        $term  = $request->input('term');
        $page = $request->input('page', 1);

        $resultCount = 10;
        $offset = ($page - 1) * $resultCount;

        $results = Alumno::with('especialidades')->with('grupos_activos.especialidad')->with('grupos.especialidad')->select(['id', 'nombres', 'apellido_paterno', 'apellido_materno', 'nuevo_numero_control'])
            ->when($request->input('id_sucursal'), function ($q, $sucursal) {
                $q->where('id_sucursal', $sucursal);
            })
            ->when($request->input('status'), function ($q, $status) {
                $q->where('status', '=', $status);
            })
            ->where(function ($q) use ($term) {
                $q->whereRaw("CONCAT(`nombres`,`apellido_paterno`,`apellido_materno`) LIKE REPLACE(?, ' ','')", ["%{$term}%"])
                    ->orWhereRaw("`nuevo_numero_control` LIKE REPLACE(?, ' ','')", ["%{$term}%"]);
            })
            ->orderBy('nombres', 'asc')
            ->skip($offset)
            ->take($resultCount)
            ->get();

        $count = Alumno::query()->select('id')
            ->when($request->input('id_sucursal'), function ($q, $id_sucursal) {
                $q->where('id_sucursal', $id_sucursal);
            })
            ->when($request->input('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->where(function ($q) use ($term) {
                $q->whereRaw("CONCAT(`nombres`,`apellido_paterno`,`apellido_materno`) LIKE REPLACE(?, ' ','')", ["%{$term}%"])
                    ->orWhereRaw("`nuevo_numero_control` LIKE REPLACE(?, ' ','')", ["%{$term}%"]);
            })
            ->count();

        $endCount = $offset + $resultCount;
        $morePages = $count > $endCount;

        // dd($results);

        if ($request->ajax()) {
            return response()->json([
                'results'       => $results,
                'pagination'    => [
                    'more' => $morePages,
                    'temr' => $term
                ]
            ]);
        }

        return redirect()->back();
    }

    public function datatables_pagos(Request $request)
    {

        $query = AlumnoPago::query()
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            })
            ->when($request->input('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->input('id_grupo'), function ($q, $id_grupo) {
                $q->where('id_grupo', $id_grupo);
            });

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('fecha_limite', function ($model) {
                return optional($model->fecha_limite)->format('d/m/Y');
            })
            ->editColumn('saldo', function ($model) {
                return number_format($model->saldo,2);
            })
            ->editColumn('concepto', function ($model) {
                return $model->concepto_completo;
            })
            ->editColumn('status', function ($model) {
                if ($model->fecha_limite->lt(\Carbon\Carbon::today()) && $model->status == 'Pendiente') {
                    return "<span class='badge badge-danger text-white'>Vencido</span>";
                }else{
                    return "<span class='badge badge-success'>{$model->status}</span>";
                }
            })
            ->rawColumns(['status'])
            ->make(true);
    }

    public function datatables_pagos_pendientes(Request $request)
    {
        if (isset($request->id_alumno)) {
            $query = AlumnoPago::query()
                ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                    $q->where('id_alumno', $id_alumno);
                })
                ->when($request->input('status'), function ($q, $status) {
                    $q->where('status', $status);
                })->when($request->input('id_grupo'), function ($q, $id_grupo) {
                    $q->where('id_grupo', $id_grupo);
                })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                    $q->where('id_especialidad', $id_especialidad);
                });

            $total_pendiente = AlumnoPago::query()
                ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                    $q->where('id_alumno', $id_alumno);
                })
                ->when($request->input('status'), function ($q, $status) {
                    $q->where('status', $status);
                })->when($request->input('id_grupo'), function ($q, $id_grupo) {
                    $q->where('id_grupo', $id_grupo);
                })->sum('saldo');
        } else {
            $query = AlumnoPago::where('id_alumno', 'xxxxxxxxx');
            $total_pendiente = 0;
        }

        return DataTables::eloquent($query)
            ->editColumn('fecha_limite', function ($model) {
                return optional($model->fecha_limite)->format('d/m/Y');
            })
            ->editColumn('monto', function ($model) {
                return number_format($model->monto, 2, '.', ',');
            })
            ->editColumn('saldo', function ($model) {
                return number_format($model->saldo, 2, '.', ',');
            })
            ->editColumn('concepto', function ($model) {
                return $model->concepto_completo;
            })
            ->editColumn('status', function ($model) {
                if ($model->fecha_limite->lt(\Carbon\Carbon::today()) && $model->status == 'Pendiente') {
                    return "<span class='badge badge-danger text-white'>Vencido</span>";
                }else{
                    return "<span class='badge badge-success'>{$model->status}</span>";
                }
            })


            ->rawColumns(['status'])
            ->with([
                'total_pendiente' => $total_pendiente
            ])
            ->make(true);
    }
    #FUNCIONALIDAD PARA DOCUMENTOS EN LUGAR DE ALUMNOS_PAGOS
    public function datatables_documentos(Request $request)
    {

        $query = Documento::with('abonos.pago')
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            })
            ->when($request->input('status'), function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($request->input('id_grupo'), function ($q, $id_grupo) {
                $q->where('id_grupo', $id_grupo);
            })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                $q->where('id_especialidad', $id_especialidad);
            });

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('fecha_limite', function ($model) {
                return optional($model->fecha_limite)->format('d/m/Y');
            })
            ->editColumn('saldo', function ($model) {
                return number_format($model->saldo,2);
            })
            ->editColumn('concepto', function ($model) {
                return $model->concepto_completo;
            })
            ->editColumn('abonos.monto', function ($model) {
                $txt = '';
                foreach($model->abonos as $abono){
                    if($abono->pago){

                        $folio = '';
                        $fecha = '';
                        if($abono->pago->id){
                            $fecha = $abono->pago->fecha->format('d-m-Y');
                        }

                        if($abono->pago->folio){
                            $folio = '<span class="text-danger">'.$abono->pago->folio.'</span>';
                        }
    
                        if($abono->pago->folio_fiscal){
                            $folio = '<span class="text-primary">'.$abono->pago->folio_fiscal.'</span>';
                        }

                        $txt .= $folio.' | '.$fecha.' ($'.number_format($abono->monto).')<br>';
                    }
                    
                }

                return $txt;
            })
            ->editColumn('status', function ($model) {
                if ($model->fecha_limite->lt(\Carbon\Carbon::today()) && $model->status == 'Pendiente') {
                    return "<span class='badge badge-danger text-white'>Vencido</span>";
                }else{
                    return "<span class='badge badge-success'>{$model->status}</span>";
                }
            })
            ->rawColumns(['status','abonos.monto'])
            ->make(true);
    }

    public function datatables_documentos_pendientes(Request $request)
    {
        if (isset($request->id_alumno)) {
            $query = Documento::query()
                ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                    $q->where('id_alumno', $id_alumno);
                })
                ->when($request->input('status'), function ($q, $status) {
                    $q->where('status', $status);
                })->when($request->input('id_grupo'), function ($q, $id_grupo) {
                    $q->where('id_grupo', $id_grupo);
                })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                    $q->where('id_especialidad', $id_especialidad);
                });

            $total_pendiente = Documento::query()
                ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                    $q->where('id_alumno', $id_alumno);
                })
                ->when($request->input('status'), function ($q, $status) {
                    $q->where('status', $status);
                })->when($request->input('id_grupo'), function ($q, $id_grupo) {
                    $q->where('id_grupo', $id_grupo);
                })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                    $q->where('id_especialidad', $id_especialidad);
                })->sum('saldo');

                $today = \Carbon\Carbon::today();

            $vencido = Documento::query()
                ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                    $q->where('id_alumno', $id_alumno);
                })
                ->when($request->input('status'), function ($q, $status) {
                    $q->where('status', $status);
                })->where(function($q)use($today){
                    return $q->where('fecha_limite','<',$today->format('Y-m-d'))->where('status','=','Pendiente');
                })->count();

            $apoyo = ApoyoEspecial::where('id_alumno','=', $request->id_alumno)
                        ->where('id_especialidad','=', $request->id_especialidad)
                        ->where('fecha_final','>=', date('Y-m-d'))->orderBy('fecha_final')->get()->first();                     
            
            //  SE UTILIZA PARA SABER LA DIFERENCIA ENTRE LA CANTIDAD DE MESES QUE LE TOCA DE APOYO Y EL ULTIMO PAGO QUE HIZO
            $ultimo_docto_pagado = Documento::query()
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                $q->where('id_especialidad', $id_especialidad);
            })->orderBy('fecha_limite','desc')->get()->first();


                
        } else {
            $query = Documento::where('id_alumno', 'xxxxxxxxx');
            $total_pendiente = 0;
            $vencido = 0;
            $apoyo = ApoyoEspecial::where('id_alumno','=', 'xxxxxxxxx');
            $ultimo_docto_pagado = Documento::where('id_alumno', 'xxxxxxxxx')->first();
        }

        return DataTables::eloquent($query)
            ->editColumn('fecha_limite', function ($model) {
                return optional($model->fecha_limite)->format('d/m/Y');
            })
            ->editColumn('monto', function ($model) {
                return number_format($model->monto, 2, '.', ',');
            })
            ->editColumn('saldo', function ($model) {
                return number_format($model->saldo, 2, '.', ',');
            })
            ->editColumn('concepto', function ($model) {
                return $model->concepto_completo;
            })
            ->editColumn('status', function ($model) {
                if ($model->fecha_limite->lt(\Carbon\Carbon::today()) && $model->status == 'Pendiente') {
                    return "<span class='badge badge-danger text-white'>Vencido</span>";
                }else{
                    return "<span class='badge badge-success'>{$model->status}</span>";
                }
            })
            ->addColumn('saldo_sin_formato', function ($model) {
                return $model->saldo;
            })
            ->rawColumns(['status'])
            ->with([
                'total_pendiente' => $total_pendiente,
                'vencido'         => $vencido,
                'apoyo'          => $apoyo,
                'ultimo_docto_pagado' => $ultimo_docto_pagado
            ])
            ->make(true);
    }


    public function formulario_inscribir_otro_grupo(Alumno $alumno)
    {
        return view('alumnos.inscripcion', [
            'alumno'            => $alumno,
            'especialidades'    => Especialidad::query()->pluck('nombre', 'id')->sort()->prepend('Selecciona una especialidad', ''),
        ]);
    }

    public function inscribir_a_otro_grupo(Request $request, $id, PagoColegiaturaDocumentosService $pcds)
    {
        $alumno = Alumno::with(['especialidades'])->find($id);
        
        if ($request->has('id_grupo')) {
            $alumno->grupos()->attach($request->input('id_grupo'),['fecha_inicio' => $request->input('fecha_inicio')]);

        
            $grupo_inscripcion = Grupo::findOrFail($request->input('id_grupo'));
            Log::alert('Usuario '.Auth::user()->fullname.' inscribio a alumno '.$alumno->numero_control_fullname.' al grupo '.$grupo_inscripcion->nombre);

            $pcds->setRequest($request);
            $pcds->setAlumno($alumno, $grupo_inscripcion);

            // throw ValidationException::withMessages(['mensaje' => $monto_pactado]);
                // SE GENERA EL REGISTRO DE ESTE ALUMNO EN LA ESPECIALIDAD SELECCIONADA. ESTE SERA SU REGISTRO DE ESPECIALIDAD
                // CENTRAL A PARTIR DE AQUI
                // SE GUARDA EL MONTO PACTADO EN EL CORE DEL ALUMNO DONDE SE VA A RESPETAR ESTA CANTIDAD 
                $especialidad = $grupo_inscripcion->especialidad;

                
                switch ($request->input('forma_pago')) {
                    case config('alumnos.forma_pago.mensual','mensual'):
                        // $pids->inscripcion($grupo_inscripcion, $grupo_inscripcion->precio_inscripcion);
                        // SE VERIFICA SI ES VIABLE A DESCUENTO Y SI ES ASI SE GENERA UN APOYO A LA COLEGIATURA CORRESPONDIENTE
                        
                        $especialidad_alumno = $alumno->especialidades->first();

                        $descuentos = Descuento::where(function($q)use($especialidad_alumno) {
                            return $q->where(function($q2) use($especialidad_alumno){
                                return $q2->where('id_especialidad_1','=',$especialidad_alumno->id)->where('forma_pago_1','=',$especialidad_alumno->pivot->forma_pago);
                            })->orWhere(function($q2) use($especialidad_alumno){
                                return $q2->where('id_especialidad_2','=',$especialidad_alumno->id)->where('forma_pago_2','=',$especialidad_alumno->pivot->forma_pago);
                            });
                        })->where(function($q)use($especialidad, $request) {
                            return $q->where(function($q2) use($especialidad, $request){
                                return $q2->where('id_especialidad_1','=',$especialidad->id)->where('forma_pago_1','=',$request->forma_pago);
                            })->orWhere(function($q2) use($especialidad, $request){
                                return $q2->where('id_especialidad_2','=',$especialidad->id)->where('forma_pago_2','=',$request->forma_pago);
                            });
                        })->get();

                        // SI APLICA A UN DESCUENTO, SE GENERAN LOS APOYOS DE ESPECIALIDAD
                        if($descuentos->count()>0){
                            $descuento = $descuentos->first();

                            $today = \Carbon\Carbon::today();
                            $apoyo_1 = ApoyoEspecial::create([
                                'id_sucursal' => $alumno->id_sucursal,
                                'id_especialidad'  => $descuento->id_especialidad_1,
                                'id_alumno'     => $alumno->id,
                                'fecha_inicio'=> $today->format('Y-m-d'),
                                'fecha_final'=> $today->addWeeks($especialidad_alumno->pivot->semanas_cursar)->format('Y-m-d'),
                                'precio' => $descuento->monto_1,
                                'tipo'  => 'Doble Especialidad',
                                'id_descuento' => $descuento->id,
                            ]);

                            $today = \Carbon\Carbon::today();
                            $apoyo_2 = ApoyoEspecial::create([
                                'id_sucursal' => $alumno->id_sucursal,
                                'id_especialidad'  => $descuento->id_especialidad_2,
                                'id_alumno'     => $alumno->id,
                                'fecha_inicio'=> $today->format('Y-m-d'),
                                'fecha_final'=> $today->addWeeks($especialidad->materias->sum('semanas'))->format('Y-m-d'),
                                'precio' => $descuento->monto_2,
                                'tipo'  => 'Doble Especialidad',
                                'id_descuento' => $descuento->id,
                            ]);

                        }

                        // GENERA DOCUMENTOS MENSUALES
                        $pcds->mensual($especialidad, $grupo_inscripcion);

                        $monto_pactado = $grupo_inscripcion->precio_mensualidad;
                        $monto_pronto_pago_pactado = $grupo_inscripcion->precio_mensualidad_pronto_pago;
                    break;
                    case config('alumnos.forma_pago.semanal','semanal'):
                        // $pids->inscripcion($grupo_inscripcion, $grupo_inscripcion->precio_inscripcion);
                        // GENERA DOCUMENTOS SEMANALES
                        $pcds->semanal($especialidad, $grupo_inscripcion);

                        $monto_pactado = $grupo_inscripcion->precio_semanal;
                        $monto_pronto_pago_pactado = $grupo_inscripcion->precio_semanal;
                    break;
                }

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

            // OPERACIONES: sumar | restar
            // CAMPOS: inicios | reingresos | cambios_horarios_plus | bajas | cambios_horarios_minus | fin_curso
            $grupo_inscripcion->actualizarReporteDesercion('sumar','inicios',1);
            #Se registra la inscripcion de este alumno para el reporte de asesores
            $sucursal = optional(session('sucursal'));
            $inscripcion = Inscripcion::create([
                'id_alumno' => $alumno->id,
                'id_grupo' => $grupo_inscripcion->id,
                'id_sucursal' => $sucursal->id,
                'id_asesor' => $alumno->id_asesor_educativo,
                'fecha' => date('Y-m-d'),
                'fecha_inicio_grupo' => $request->input('fecha_inicio'),
            ]);

        }

        Session::flash('message', 'Se inscribio al alumno con éxito');
        return redirect()->route('alumnos.show', $alumno->id);
    }

    #DATATABLES QUE TRAE LOS PAGOS REALIZADOS POR LOS ALUMNOS
    public function datatables_historial_pagos(Request $request)
    {

        $query = Pago::with(['recibio','alumno','abonos.alumno_pago','abonos_documentos.documento'])
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            })->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                $q->where('id_especialidad', $id_especialidad);
            });

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->editColumn('fecha', function ($model) {
                return optional($model->fecha)->format('d/m/Y H:i');
            })
            ->editColumn('folio', function ($model) {
                if($model->folio){
                    return '<span class="text-danger" >'.$model->folio.'</span>';
                }

                if($model->folio_fiscal){
                    return '<span class="text-primary" >'.$model->folio_fiscal.'</span>';
                }

                return '';

            })
            ->editColumn('abonos_documentos.documento.concepto', function ($model) {
                $txt = '';
                foreach($model->abonos_documentos as $abono){
                    $txt.= $abono->documento->concepto_completo.' ($ '.$abono->monto.') <br>';
                }

                return $txt;
            })
            ->editColumn('especialidad.nombre', function($model){

                $txt = "";
                $txt .= "<a ";
                if(Auth::user()->can('cambiar_especialidad_pagos_alumno')){
                    $txt .= " class='editable_especialidad' ";
                }
                $txt .=     "data-pk='".$model->id."' ";
                $txt .=     "data-name='id_especialidad' ";
                $txt .=     "data-url='".route("pagos.actualizar_informacion")."' ";
                $txt .=     "data-type='select' ";
                $txt .=     "data-value='". $model->id_especialidad."'>";
                $txt .=     $model->especialidad->nombre;
                $txt .= "</a>";

                return $txt;


            })
            ->rawColumns(['abonos_documentos.documento.concepto','folio','especialidad.nombre'])
            ->make(true);
    }

    public function baja_grupo(Request $request){

        $alumno = Alumno::find($request->id_alumno);

        $alumno->grupos()->detach($request->id_grupo);

        $grupo = Grupo::find($request->id_grupo);

        $grupo->actualizarReporteDesercion('sumar','bajas',1);


    }

    public function cambio_horario($id_alumno, $id_grupo){

        $alumno = Alumno::find($id_alumno);
        $grupo_origen = Grupo::find($id_grupo);

        $especialidades = Especialidad::get()->pluck('nombre','id');

        return view('alumnos.cambio_horario', compact('alumno','grupo_origen','especialidades'));

    }

    public function guardar_cambio_horario(Request $request){

        $grupo_origen = Grupo::find($request->id_grupo_origen);

        $id_grupo = $request->id_grupo;
        $alumno = Alumno::find($request->id_alumno);

        $alumno->grupos()->updateExistingPivot($request->id_grupo_origen, [
            'fecha_final' => date('Y-m-d'),
            'status' => 'Cambio horario',
        ]);

        $grupo_origen->actualizarReporteDesercion('sumar','cambios_horarios_bajas',1);

        $alumno->grupos()->attach($id_grupo, ['fecha_inicio'=>date('Y-m-d')]);
        $grupo = Grupo::find($request->id_grupo);
        $grupo->actualizarReporteDesercion('sumar','cambios_horarios_altas',1);
        Log::alert('Usuario '.Auth::user()->fullname.' cambio de grupo al alumno '.$alumno->fullname.' de '.$grupo_origen->nombre.' a '.$grupo->nombre);

        return redirect()->route('alumnos.show', $alumno->id);

    }

    public function actualizar_informacion(Request $request){

        $alumno = Alumno::find($request->pk);
        $alumno[$request->name] = $request->value;
        $alumno->save();


        if($request->name == 'id_asesor_educativo'){

            // SE ACTUALIZAN LOS REPORTES DE INSCRITOS
            $inscritos = Inscripcion::where('id_alumno','=',$alumno->id)->update([
                'id_asesor' => $request->value
            ]);

        }


    }

    public function datatables_apoyos_inscripcion(Request $request)
    {
        $query = ApoyoInscripcion::with(['especialidad','usuario_autorizo'])
            ->when($request->input('id_grupo'), function ($q, $id_grupo) {
                $q->where('id_grupo', $id_grupo);
            })
            ->when($request->input('id_especialidad'), function ($q, $id_especialidad) {
                $q->where('id_especialidad', $id_especialidad);
            })
            ->when($request->input('id_alumno'), function ($q, $id_alumno) {
                $q->where('id_alumno', $id_alumno);
            });

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('buttons', 'alumnos.datatables._buttons_apoyo_inscripcion')
            ->rawColumns(['buttons'])
            ->make(true);
    }

    public function eliminar_apoyo_inscripcion($id){
        $apoyo = ApoyoInscripcion::with(['especialidad','alumno'])->find($id);

        $apoyo->delete();
       
        Log::alert('Usuario '.Auth::user()->fullname.' elimino el apoyo a la inscripción del alumno '.$apoyo->alumno->numero_control_fullname.' del grupo'.$apoyo->especialidad->nombre);

        return response()->json([
            'apoyo' => null
        ]);
    }

    public function store_apoyo_inscripcion(Request $request){

        // BUSCAMOS QUE NO TENGA YA UN APOYO EN ESA ESPECIALIDAD
        

        if(ApoyoInscripcion::where('id_alumno','=',$request->id_alumno)->where('id_especialidad','=',$request->id_especialidad)->count() == 0){
            $apoyo = ApoyoInscripcion::create([
                'id_alumno' => $request->id_alumno,
                'id_especialidad'  => $request->id_especialidad,
                'apoyo'     => $request->apoyo,
                'id_usuario'=> Auth::id(),
                'id_usuario_autoriza'=> Auth::id(),
                'motivo' => $request->motivo,
            ]);
    
            $apoyo = ApoyoInscripcion::with(['alumno','especialidad'])->find($apoyo->id);
    
            Log::alert('Usuario '.Auth::user()->fullname.' creó el apoyo a la inscripción manual del alumno '.$apoyo->alumno->numero_control_fullname.' del grupo'.$apoyo->especialidad->nombre);

            return response()->json([
                'status' => 'success',
                'message' => 'Se guardo el apoyo con éxito'
            ]);
        }else{
            throw ValidationException::withMessages(['message' => 'Ya existe un apoyo para esta especialidad']);
        }


        

       
    }

    public function pausar_grupo(Request $request){

        $alumno = Alumno::find($request->id_alumno);

        AlumnoGrupo::where('id_alumno','=',$request->id_alumno)->where('id_grupo','=',$request->id_grupo)->update([
            'status' => 'Pausa'
        ]);

        $grupo = Grupo::find($request->id_grupo);
        Log::alert('Usuario '.Auth::user()->fullname.' pausó al alumno '.$alumno->numero_control_fullname.' del grupo '.$grupo->nombre);

        if(isset($request->fecha_recontactar)){
            $alerta = Alerta::create([
                'titulo' => 'Contactar alumno para reaundar grupo',
                'descripcion' => 'Recontactar al alumno <a target="_blank" href="'.route('alumnos.show', $alumno->id).'"  >'.$alumno->numero_control_fullname.'</a> para reaundar grupo '.$grupo->nombre,
                'fecha' => $request->fecha_recontactar,
                'id_sucursal' => $alumno->id_sucursal,
                'nota' =>$request->nota,
                'id_usuario_asignado' => Auth::id()
            ]);
        }

        // SE DETECTAN LOS DESCUENTOS POR DOBLE ESPECIALIDAD PARA PODER QUITARLOS
        $especialidad = $grupo->especialidad;
        $apoyo = $alumno->apoyos_especiales()->where('id_especialidad','=',$especialidad->id)->whereNotNull('id_descuento')->first();
        if($apoyo){
            $alumno->apoyos_especiales()->where('id_descuento','=',$apoyo->id_descuento)->update([
                'fecha_final' => date('Y-m-d')
            ]); 
        }
        

        $grupo->actualizarReporteDesercion('sumar','bajas',1);

        return redirect()->back()->with([
            'message' => 'Se pausó al alumno de este grupo con éxito'
        ]);

    }

    public function reanudar_grupo(Request $request){

        $alumno = Alumno::with(['especialidades'])->find($request->id_alumno);

        AlumnoGrupo::where('id_alumno','=',$request->id_alumno)->where('id_grupo','=',$request->id_grupo)->update([
            'status' => 'Inscrito'
        ]);
        
        $grupo = Grupo::find($request->id_grupo);
       
        
        // SE DETECTAN LOS DESCUENTOS POR DOBLE ESPECIALIDAD PARA PODER QUITARLOS
        $especialidad = $grupo->especialidad;
        $apoyo = $alumno->apoyos_especiales()->where('id_especialidad','=',$especialidad->id)->whereNotNull('id_descuento')->first();
        if($apoyo){
            $apoyos = $alumno->apoyos_especiales->where('id_descuento','=',$apoyo->id_descuento);
            
            foreach($apoyos as $apo){
                $new_apoyo = $apo->replicate();
                $new_apoyo->fecha_inicio = \Carbon\Carbon::today()->format('Y-m-d');
                $new_apoyo->fecha_final = \Carbon\Carbon::today()->addWeeks($alumno->especialidades->where('id',$apoyo->id_especialidad)->first()->pivot->semanas_cursar)->format('Y-m-d');
                $new_apoyo->save();

            }
        }
        
        Log::alert('Usuario '.Auth::user()->fullname.' reanudó al alumno '.$alumno->numero_control_fullname.' al grupo '.$grupo->nombre);

        $grupo->actualizarReporteDesercion('sumar','altas',1);


    }

    public function fin_de_curso(Request $request){

        $alumno = Alumno::find($request->id_alumno);

        AlumnoGrupo::where('id_alumno','=',$request->id_alumno)->where('id_grupo','=',$request->id_grupo)->update([
            'status' => 'Fin de Curso',
            'fecha_final' => date('Y-m-d'),
        ]);
        
        $grupo = Grupo::find($request->id_grupo);
        Log::alert('Usuario '.Auth::user()->fullname.' dio fin de curso al alumno '.$alumno->numero_control_fullname.' del grupo '.$grupo->nombre);

    }

    public function subir_foto(Request $request)
    {
        $alumno = Alumno::find($request->id_alumno);

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
            // resize the image to a width of 300 and constrain aspect ratio (auto height)
            $image->resize(780, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $image->save($path . $nombre_foto);


            $alumno->foto = $nombre_foto;
            $alumno->save();

            Log::alert('Usuario '.Auth::user()->fullname.' subio la foto del al alumno '.$alumno->numero_control_fullname);
            
        }

        $alumno->save();
        return redirect()->back()->with([
            'message' => 'Se actualizó la foto con éxito'
        ]);
    }

    public function guardar_nota(Request $request){

        $rules = [
            'nota'      => 'nullable',
        ];
        
        $data = $this->validate($request, $rules);

        $nota = Nota::create([
            'id_alumno' => $request->id_alumno,
            'nota' => $request->nota,
            'id_usuario' => Auth::id(),
            'fecha' => date('Y-m-d H:i:s')
        ]);

        // return redirect()->back();


    }

    public function actualizar_informacion_alumnos_especialidades(Request $request){
        $ae = AlumnoEspecialidad::find($request->pk);
        $ae[$request->name] = $request->value;
        $ae->save();

    }


}
