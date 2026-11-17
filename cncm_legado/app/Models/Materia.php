<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'materias';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'id_especialidad',
        'nombre',
        'fase',
        'orden',
        'semanas'
    ];

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class,'id_especialidad','id')->withDefault([
            'nombre'        => '',
            'descripcion'   => ''
        ]);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class,'id_sucursal','id')->withDefault();
    }
}
