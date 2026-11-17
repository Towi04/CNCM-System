<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'especialidades';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_alumno',
        'id_especialidad',
        'fecha_inicio',
        'no_semanas',
        'tipo_pago',
        'monto',
        'semanas_cursar',
        'semanas_cursadas',
        'semanas_pagadas',
        'status',
        'nombre',
        'descripcion',
        // 'formas_pago',
    ];

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_especialidad', 'id');
    }

    public function grupos_activos()
    {
        return $this->hasMany(Grupo::class,'id_especialidad', 'id')
            ->where('status',config('grupos.status.values.Activo'));
    }

    public function cordinadores()
    {
        return $this->belongsToMany(User::class, 'especialidades_users', 'id_especialidad', 'id_usuario')
            ->withPivot('id_usuario')
            ->using(EspecialidadUser::class);
    }

    /**
     * Get all of the precios f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function precios()
    {
        return $this->hasMany(Precio::class, 'id_especialidad', 'id');
    }

    /**
     * Get all of the materias for the Especialidad
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function materias()
    {
        return $this->hasMany(Materia::class, 'id_especialidad', 'id');
    }

    public function getInscripcionFecha($fecha){
        // dd($fecha);
        // $fecha = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);
        $precio = $this->precios->where('tipo','=','Inscripción')->filter(function($precio)use($fecha){
            if($precio->fecha_final == null && $precio->fecha_inicio->lte($fecha)){
                return true;
            }
            
            if($precio->fecha_inicio->lte($fecha) && $precio->fecha_final->gt($fecha)){
                return true;
            }
        })->first();

        // dd($precio);

        if($precio){
            return $precio->precio_normal;
        }else{
            return $this->precio_inscripcion;
        }

    }

    public function getPrecioColegiaturaSemanalFecha($fecha){
        // dd($fecha);
        // $fecha = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);
        $precio = $this->precios->where('tipo','=','Precio Semanal')->filter(function($precio)use($fecha){
            if($precio->fecha_final == null && $precio->fecha_inicio->lte($fecha)){
                return true;
            }
            
            if($precio->fecha_inicio->lte($fecha) && $precio->fecha_final->endOfDay()->gt($fecha)){
                return true;
            }
        })->first();

        // dd($precio);

        if($precio){
            return $precio->precio_normal;
        }else{
            return $this->precio_semanal;
        }

    }

    public function getPrecioColegiaturaMensualFecha($fecha){
        // dd($fecha);
        // $fecha = \Carbon\Carbon::createFromFormat('Y-m-d', $fecha);

        $precio = $this->precios->where('tipo','=','Precio Mensual')->filter(function($precio)use($fecha){
            if($precio->fecha_final == null && $precio->fecha_inicio->lte($fecha)){
                return true;
            }
            
            if($precio->fecha_inicio->lte($fecha) && $precio->fecha_final->gt($fecha)){
                return true;
            }
        })->first();

        

        if($precio){
            return [
                'precio_pronto_pago'=>$precio->precio_pronto_pago,
                'precio_normal'=>$precio->precio_normal,
            ];
        }else{
            return [
                'precio_pronto_pago'=>$this->precio_mensualidad_pronto_pago,
                'precio_normal'=>$this->precio_mensualidad,
            ];
        }

    }
}
