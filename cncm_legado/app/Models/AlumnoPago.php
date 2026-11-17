<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class AlumnoPago extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'alumnos_pagos';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at','updated_at','fecha_limite'];


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_alumno',
        'id_grupo',
        'concepto',
        'monto',
        'monto_apoyo_inscripcion',
        'saldo',
        'fecha_limite',
        'status',
        'tipo',
        'semana',
        'mes',
        'anio',
        'modalidad',
    ];

    protected $attributes = [
        'status' => 'Pendiente',
    ];

    public $appends = [
        'status_vencimiento'
    ];

    public function alumno()
    {
        return $this->belongsTo(Alumno::class,'id_alumno','id')
        ->withTrashed()
        ->withDefault([
            'nombres'               => '',
            'apellido_paterno'      => '',
            'apellido_materno'      => '',
            'numero_control'        => '',
            'nuevo_numero_control'  => '',
        ]);
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class,'id_grupo','id')->withDefault();
    }

    public function abonos()
    {
        return $this->hasMany(Abono::class,'id_alumno_pago','id');
    }

    # NOTE: SCOPES

    public function scopePendientes($query)
    {
        return $query->where('status',config('pagos.status.Pendiente'));
    }

    public function getStatusVencimientoAttribute(){
        if($this->fecha_limite->lt(\Carbon\Carbon::today()) && $this->status == 'pendiente'){
            return 'Vencido';
        }else{
            return $this->status;
        }
    }

    public function getConceptoCompletoAttribute(){
        #SE PONE NOMBRE COMPLETO CON SEMANA Y AÑO PARA CUANDO UN PAGO ES SEMANAL
        if($this->modalidad == 'semanal'){
            return $this->concepto.' Semana #'.$this->semana.' del '.$this->anio;
        }
        #SE PONE NOMBRE COMPLETO CON MENSUAL Y AÑO PARA CUANDO UN PAGO ES MENUSAL
        if($this->modalidad == 'mensual'){
            return $this->concepto.' Mes '.$this->mes.' del '.$this->anio;
        }

        return $this->concepto;
        
    }
}
