<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    protected $fillable = [
        'id_alumno',
        'id_grupo',
        'id_especialidad',
        'id_asesor',
        'id_sucursal',
        'fecha',
        'fecha_inicio_grupo'
    ];

    protected $dates = [
        'fecha','fecha_inicio_grupo'
    ];
    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id');
    }

    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo', 'id');
    }

    /**
     * Get the alumno
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */

    public function asesor()
    {
        return $this->belongsTo(User::class, 'id_asesor', 'id')->withTrashed();
    }
}
