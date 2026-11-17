<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pagos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'folio',
        'id_sucursal',
        'id_alumno',
        'monto',
        'forma_pago',
        'fecha',
        'id_recibio',
        'folio_fiscal',
        'id_especialidad'
    ];


    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'fecha','deleted_at'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'format_fecha',
    ];


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id')->withDefault([
            'nombre' => ''
        ]);
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id')->withTrashed()->withDefault(function ($alumno) {
            $alumno->nombres = '';
            $alumno->apellido_paterno = '';
            $alumno->apellido_materno = '';
            $alumno->numero_control = '';
            $alumno->nuevo_numero_control ='';
        });
    }

    public function recibio()
    {
        return $this->belongsTo(User::class, 'id_recibio', 'id')->withDefault(function ($user) {
            $user->nombres = '';
            $user->apellido_paterno = '';
            $user->apellido_materno = '';
        });
    }

    public function abonos()
    {
        return $this->hasMany(Abono::class,'id_pago','id');
    }

    #NUEVOS ABONOS A DOCUMENTOS
    public function abonos_documentos()
    {
        return $this->hasMany(AbonoDocumento::class,'id_pago','id');
    }

    public function getFormatFechaAttribute($value)
    {
        if(empty($this->fecha)){
            return '';
        }

        return $this->fecha->format('d-m-Y h:i a');
    }

    /**
     * Get the usuario_elimino that owns the Pago
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario_elimino()
    {
        return $this->belongsTo(User::class, 'id_usuario_elimino', 'id');
    }

    /**
     * Get the especialidad that owns the Pago
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id')->withDefault([
            'nombre' => 'Vacio'
        ]);
    }

}
