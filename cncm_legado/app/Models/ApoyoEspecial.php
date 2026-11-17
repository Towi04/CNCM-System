<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApoyoEspecial extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'apoyos_especiales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_alumno',
        'id_grupo',
        'id_especialidad',
        'id_sucursal',
        'precio',
        'fecha_final',
        'fecha_inicio',
        'tipo',
        'id_descuento',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['fecha_final','fecha_inicio',];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class,'id_alumno')->withDefault();
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class,'id_grupo')->withDefault();
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class,'id_sucursal')->withDefault();
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class,'id_especialidad')->withDefault();
    }
}
