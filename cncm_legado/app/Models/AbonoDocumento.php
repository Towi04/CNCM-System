<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AbonoDocumento extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'abonos_documentos';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'id_pago',
        'id_documento',
        'id_especialidad',
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

    public function documento()
    {
        return $this->belongsTo(Documento::class, 'id_documento', 'id')->withDefault(['concepto'=>'A CUENTA']);
    }
}
