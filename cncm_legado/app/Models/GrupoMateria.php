<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class GrupoMateria extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'grupos_materias';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function materia()
    {
        return $this->belongsTo(Materia::class,'id_materia','id')->withDefault();
    }

    public function profesor()
    {
        return $this->belongsTo(User::class,'id_profesor','id')->withDefault([
            'nombres'           => '',
            'apellido_paterno'  => '',
            'apellido_materno'  => '',
        ])->select(['id','nombres',
        'foto',
        'telefono',
        'celular',
        'apellido_paterno',
        'apellido_materno',
        'email',]);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class,'id_grupo','id')->withDefault();
    }
}
