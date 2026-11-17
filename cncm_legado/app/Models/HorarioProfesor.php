<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioProfesor extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'horarios_profesores';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'id_profesor',
        'dia',
        'hora_inicio',
        'hora_final',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id')->withDefault([
            'nombre' => ''
        ]);
    }

    public function profesor()
    {
        return $this->belongsTo(User::class, 'id_profesor', 'id')->withDefault([
            'nombres'           => '',
            'apellido_paterno'  => '',
            'apellido_materno'  => '',
        ]);
    }
}
