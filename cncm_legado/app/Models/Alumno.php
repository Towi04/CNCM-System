<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Collective\Html\Eloquent\FormAccessible;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Alumno extends Model
{
    use HasFactory, FormAccessible, SoftDeletes, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'alumnos';


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'solicitud_factura' => 'boolean',
        'grado_estudios'    => 'array',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'fecha_nacimiento'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'numero_control',
        'nuevo_numero_control',
        'id_sucursal',

        'como_supiste_nosotros',

        'foto',
        'nombres',
        'apellido_paterno',
        'apellido_materno',
        'edad',
        'fecha_nacimiento',
        'domicilio',
        'colonia',
        'municipio',
        'telefono',
        'celular',
        'email',
        'codigo_postal',
        'ocupacion',
        'grado_estudios',
        'otro_grado_estudios',
        'tutor',
        'id_especialidad',
        'otra_especialidad',
        'escuela_procedencia',
        'objetivo_inscripcion',
        'enfermedad_cronica',
        'solicitud_factura',
        'id_asesor_educativo',

        # DATOS DE FACTURACION
        'razon_social',
        'rfc',
        'cfdi',
        'curp',
        'telefono_general',
        'correo_general',
        'domicilio_fiscal',

        'observaciones',
        'forma_pago',
        'status',
    ];

    protected $appends = [
        'documentos_vencidos', 
        'pagos_vencidos', 
        'monto_vencido', 
        'url_foto',
        'fullname',
        'numero_control_fullname'
    ];

    # NOTE: MODEL RELATIONSHIPS
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id')->withDefault([
            'nombre'        => '',
            'descripcion'   => ''
        ]);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id')->withDefault();
    }

    public function asesor_educativo()
    {
        return $this->belongsTo(User::class, 'id_asesor_educativo', 'id')->withDefault([
            'id'                => 'CNCM',
            'nombres'           => 'CNCM',
            'apellido_paterno'  => '',
            'apellido_materno'  => '',
        ])->withTrashed();
    }

    public function grupos()
    {
        return $this->belongsToMany(Grupo::class, 'alumnos_grupos', 'id_alumno', 'id_grupo')
            ->withPivot('id', 'id_grupo','fecha_inicio','status','fecha_final')->using(AlumnoGrupo::class);
    }

    public function grupos_activos()
    {
        return $this->belongsToMany(Grupo::class, 'alumnos_grupos', 'id_alumno', 'id_grupo')->wherePivot('status','=','Inscrito')
            ->withPivot('id', 'id_grupo','fecha_inicio','status','fecha_final')->using(AlumnoGrupo::class);
    }

    public function pagos()
    {
        return $this->hasMany(AlumnoPago::class, 'id_alumno', 'id');
    }

    public function especialidades()
    {
        return $this->belongsToMany(Especialidad::class,'alumnos_especialidades',  'id_alumno','id_especialidad','id','id')
        ->withPivot('id', 'id_especialidad','fecha_inicio','status','semanas_cursar','semanas_cursadas','monto','forma_pago','monto_pronto_pago')->using(AlumnoEspecialidad::class);
    }

    # NOTE: MODEL ACCESORS
    public function getFullnameAttribute()
    {
        return $this->nombres . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno;
    }

    public function getNumeroControlFullnameAttribute()
    {
        return $this->nuevo_numero_control . ' '.$this->nombres . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno;
    }


    # NOTE: Form Model Accessors (Laravel Collective) https://laravelcollective.com/docs/5.4/html

    public function formFechaNacimientoAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d');
    }

    #NOTE: scopes

    public function scopeAlumno($query)
    {
        return $query->where('status', config('alumnos.status.Alumno'));
    }

    public function scopeSemanal($query)
    {
        return $query->where(function($q){
            return $q->where('forma_pago', config('alumnos.forma_pago.semanal'))->orWhereNull('forma_pago');
        });
    }

    public function scopeMensual($query)
    {
        return $query->where('forma_pago', config('alumnos.forma_pago.mensual'));
    }

    public function getPagosVencidosAttribute()
    {
        return $this->pagos->filter(function ($pago) {
            return $pago->status == 'Pendiente' && $pago->fecha_limite->lt(\Carbon\Carbon::today());
        });
    }

    // public function getMontoVencidoAttribute()
    // {
    //     return $this->pagos_vencidos->sum('saldo');
    // }

    public function getDocumentosVencidosAttribute()
    {
        return $this->documentos->filter(function ($documento) {
            return $documento->status == 'Pendiente' && $documento->fecha_limite->lt(\Carbon\Carbon::today());
        });
    }

    public function getMontoVencidoAttribute()
    {
        return $this->documentos_vencidos->sum('saldo');
    }

    public function getPagosPorCobrarAttribute()
    {
        return $this->documentos->filter(function ($documento) {
            return $documento->status == 'Pendiente' && $documento->fecha_limite->lte(\Carbon\Carbon::today()->endOfMonth());
        });
    }

    public function getMontoPorCobrarAttribute()
    {
        return $this->pagos_por_cobrar->sum('saldo');
    }

    public function getUrlFotoAttribute()
    {
        if(empty($this->id) || empty($this->foto) ){
            return asset('no_image/alumnos_foto.png');
        }

        if(!Storage::disk('local')->exists("alumnos_foto/{$this->id}/{$this->foto}")){
            return asset('no_image/alumnos_foto.png');
        }


        return url("archivo/alumnos_foto/{$this->id}/{$this->foto}");
    }

    /**
     * Get all of the asistencias for the Alumno
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'id_alumno', 'id');
    }

    /**
     * Get all of the ventas for the Alumno
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ventas()
    {
        return $this->hasMany(Venta::class, 'id_alumno', 'id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'id_alumno', 'id');
    }

    public function pagos_caja()
    {
        return $this->hasMany(Pago::class, 'id_alumno', 'id');
    }

    /**
     * Get all of the apoyos f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function apoyos_especiales()
    {
        return $this->hasMany(ApoyoEspecial::class, 'id_alumno', 'id');
    }

    /**
     * Get all of the apoyos f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function apoyos_inscripcion()
    {
        return $this->hasMany(ApoyoInscripcion::class, 'id_alumno', 'id');
    }

    /**
     * Get all of the notas f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notas()
    {
        return $this->hasMany(Nota::class, 'id_alumno', 'id');
    }


    /**
     * Get all of the apoyos f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'id_alumno', 'id');
    }
}
