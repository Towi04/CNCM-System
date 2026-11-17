<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Descuento extends Model
{
    use HasFactory;

    protected $table = 'descuentos';

    protected $fillable = [
        'id_especialidad_1',
        'forma_pago_1',
        'id_especialidad_2',
        'forma_pago_2',
        'monto_1',
        'monto_2',
        'id_usuario'
    ];

    protected $dates = [];


    /**
     * Get the usuario that owns the Descuento
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }

    /**
     * Get the especialidad_1 that owns the Descuento
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function especialidad_1(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad_1', 'id');
    }

    /**
     * Get the especialidad_2 that owns the Descuento
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function especialidad_2(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'id_especialidad_2', 'id');
    }


}
