<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Date\Date;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $dates = [
        'fecha'
    ];

    public function getFechaAttribute($value){
        return new Date($value);
    }

    /**
     * Get the personal t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function personal()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }

    /**
     * Get the grupo that owns the Asistencia
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class, 'id_grupo', 'id');
    }

    /**
     * Get the grupo that owns the Asistencia
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad', 'id');
    }
}
