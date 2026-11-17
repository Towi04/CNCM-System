<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Precio extends Model
{
    use HasFactory;

    protected $table = 'precios';

    protected $fillable = [
        'tipo',
        'id_grupo',
        'id_especialidad',
        'fecha_inicio',
        'fecha_final',
        'precio_pronto_pago',
        'precio_normal',
        'id_usuario',
    ];

    protected $dates = [
        'fecha_inicio','fecha_final'
    ];
    /**
     * Get the usuario that owns the Precio
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }
    
    /**
     * Get all of the precios for the Precio
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function precios(): HasMany
    {
        return $this->hasMany(Precio::class, 'id_precio', 'id');
    }

    
}
