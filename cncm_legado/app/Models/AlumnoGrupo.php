<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AlumnoGrupo extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'alumnos_grupos';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['fecha_inicio','fecha_final'];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class,'id_alumno','id')->withDefault([
            'nombres'               => '',
            'apellido_paterno'      => '',
            'apellido_materno'      => '',
            'numero_control'        => '',
            'nuevo_numero_control'  => '',
        ]);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class,'id_grupo','id')->withDefault();
    }
}
