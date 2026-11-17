<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Nota extends Model
{
    use HasFactory;

    protected $table = 'notas';

    protected $dates = [
        'fecha'
    ];

    protected $fillable = [
        'id_alumno','nota','id_usuario','fecha'
    ];
    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id');
    }

    /**
     * Get the alumno t
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id');
    }


}
