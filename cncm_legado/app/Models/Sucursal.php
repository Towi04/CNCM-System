<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sucursales';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nombre',
        'direccion',
        'telefono',
        'rfc',
        'municipio',
        'estado'
    ];

    public function rol()
    {
        return $this->hasOne(Role::class,'id_sucursal','id');
    }
}
