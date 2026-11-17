<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abono extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'abonos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'id_pago',
        'id_alumno_pago',
        'monto',
        'venta_fiscal',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];


    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id')->withDefault([
            'nombre' => ''
        ]);
    }

    public function pago()
    {
        return $this->belongsTo(Pago::class, 'id_pago', 'id')->withDefault();
    }

    public function alumno_pago()
    {
        return $this->belongsTo(AlumnoPago::class, 'id_alumno_pago', 'id')->withDefault(['concepto'=>'A CUENTA']);
    }
}
