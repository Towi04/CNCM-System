<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alerta extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descripcion',
        'fecha',
        'id_usuario_asignado',
        'id_sucursal',
        'nota'
    ];

    protected $dates = [
        'fecha'
    ];
    
}
