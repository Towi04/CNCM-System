<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApoyoInscripcion extends Model
{
    use HasFactory;

    protected $table = 'apoyos_inscripciones';

    protected $fillable = [
        'id_alumno',
        'id_especialidad',
        'id_grupo',
        'apoyo',
        'id_usuario',
        'id_usuario_autoriza',
        'motivo',
    ];

    /**
     * Get the alumno
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id')->withTrashed();
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
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }

    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id');
    }

    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario_solicito()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id')->withDefault([
            'nombre' => 'NA',
            'apellido_paterno' => ''
        ]);
    }

    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario_autorizo()
    {
        return $this->belongsTo(User::class, 'id_usuario_autoriza', 'id')->withDefault([
            'nombre' => 'NA',
            'apellido_paterno' => ''
        ]);
    }

}
