<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AlumnoEspecialidad extends Pivot
{
    // use HasFactory;

    protected $fillable = [
        'id_alumno',
        'id_especialidad',
        'fecha_inicio',
        'no_semanas',
        'forma_pago',
        'monto',
        'monto_pronto_pago',
        'semanas_cursar',
        'semanas_cursadas',
        'semanas_pagadas',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $table = 'alumnos_especialidades';

    protected $dates = ['fecha_inicio'];

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

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class,'id_especialidad','id')->withDefault();
    }


}
