<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asesoria extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'asesorias';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id_sucursal',
        'id_profesor',
        'id_alumno',
        'fecha_inicio',
        'fecha_final',
        'notas',
        'status',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_final'];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'start',
        'end',
        'title',
        'description',
        'color',

        // 🙄 AGREGAR ELEMENTO AL MODELO
        'model_id',
        'format_fecha_inicio',
        'format_fecha_final',
        'hora_inicio',
        'hora_final'
    ];

    const COLOR_DEFAULT = '#fdb44d';

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal', 'id')->withDefault([
            'nombre' => ''
        ]);
    }

    public function profesor()
    {
        return $this->belongsTo(User::class, 'id_profesor', 'id')->withDefault([
            'nombres'           => '',
            'apellido_paterno'  => '',
            'apellido_materno'  => '',
        ]);
    }

    public function alumno()
    {
        return $this->belongsTo(Alumno::class, 'id_alumno', 'id')->withDefault([
            'nombres'               => '',
            'apellido_paterno'      => '',
            'apellido_materno'      => '',
            'numero_control'        => '',
            'nuevo_numero_control'  => '',
        ]);
    }

    public function getFormatFechaInicioAttribute()
    {
        if (empty($this->fecha_inicio)) {
            return today()->format('Y-m-d');
        }

        return $this->fecha_inicio->format('Y-m-d');
    }

    public function getFormatFechaFinalAttribute()
    {
        if (empty($this->fecha_final)) {
            return today()->format('Y-m-d');
        }
        return $this->fecha_final->format('Y-m-d');
    }

    public function getHoraInicioAttribute()
    {
        if (empty($this->fecha_inicio)) {
            return today()->format('H');
        }

        return $this->fecha_inicio->format('H');
    }

    public function getHoraFinalAttribute()
    {
        if (empty($this->fecha_final)) {
            return today()->format('H');
        }
        return $this->fecha_final->format('H');
    }

    # NOTE: FULLCALENDAR

    public function getModelIdAttribute()
    {
        if (empty($this->id)) {
            return null;
        }

        return $this->id;
    }

    public function getStartAttribute()
    {
        if (empty($this->fecha_inicio)) {
            return today()->format('Y-m-d');
        }

        return $this->fecha_inicio->format('Y-m-d');
    }

    public function getEndAttribute()
    {
        if (empty($this->fecha_final)) {
            return today()->format('Y-m-d');
        }
        return $this->fecha_final->format('Y-m-d');
    }

    public function getTitleAttribute()
    {
        return 'Asesoria a : ' . $this->alumno->fullname;
    }

    public function getDescriptionAttribute()
    {
        return $this->notas;
    }

    public function getColorAttribute()
    {
        $config = config('asesorias.status.colors',[]);

        return $config[$this->status] ?? self::COLOR_DEFAULT;
    }
}
