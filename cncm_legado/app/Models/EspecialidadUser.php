<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EspecialidadUser extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'especialidades_users';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_alumno', 'id')->withDefault([
            'nombres'               => '',
            'apellido_paterno'      => '',
            'apellido_materno'      => '',
        ]);
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id')->withDefault([
            'nombre'        => '',
            'descripcion'   => '',
        ]);
    }
}
