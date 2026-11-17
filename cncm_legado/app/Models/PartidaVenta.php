<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartidaVenta extends Model
{
    use HasFactory;

    protected $table = 'partidas_ventas';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'cantidad' => 'integer',
    ];

    /**
     * Get the venta t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venta()
    {
        return $this->belongsTo(Venta::class, 'id_venta', 'id');
    }

    /**
     * Get the producto t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id');
    }
}
