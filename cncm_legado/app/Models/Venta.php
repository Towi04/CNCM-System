<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $table = 'ventas';

    protected $dates = [
        'fecha'
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'format_fecha',
    ];

    /**
     * Get all of the partidas f
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function partidas()
    {
        return $this->hasMany(PartidaVenta::class, 'id_venta', 'id');
    }


    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id')
        ->withTrashed()
        ->withDefault([
            'nombres'               => '',
            'apellido_paterno'      => '',
            'apellido_materno'      => '',
            'numero_control'        => '',
            'nuevo_numero_control'  => '',
        ]);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id');
    }

    public function recibio()
    {
        return $this->belongsTo(User::class, 'id_recibio', 'id');
    }

    public function getFormatFechaAttribute($value)
    {
        if(empty($this->fecha)){
            return '';
        }

        return $this->fecha->format('d-m-Y H:i');
    }
}
