<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrupoDia extends Model
{
    use HasFactory;

    protected $table = 'grupos_dias';



    public function getDisplayNameAttribute($value)
    {
        return ucfirst($this->dia) . ' H ' . $this->hora_inicio . ' - ' . $this->hora_final;
    }
}
