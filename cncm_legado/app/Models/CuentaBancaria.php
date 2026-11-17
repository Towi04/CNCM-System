<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CuentaBancaria extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'cuentas_bancarias';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nombre',
        'banco',
        'no_cuenta',
        'id_sucursal'
    ];


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class,'id_sucursal','id')->withDefault([
            'nombre' => ''
        ]);
    }
}
