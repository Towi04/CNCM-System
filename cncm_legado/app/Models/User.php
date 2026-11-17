<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'nombres',
        'foto',
        'telefono',
        'celular',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'password',
        'fecha_nacimiento',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'fullname','link_verificacion'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $dates = [
        'fecha_nacimiento'
    ];


    public function getFullnameAttribute()
    {
        return $this->nombres . ' ' . $this->apellido_paterno . ' ' . $this->apellido_materno;
    }

    public function setPasswordAttribute($valor)
    {
        if (!empty($valor)) {

            if (Request::url() != url('/') . "/password/reset") {
                $this->attributes['password'] = bcrypt($valor);
            } else {
                $this->attributes['password'] = $valor;
            }
        }
    }

    public function getNameRoleUserAttribute()
    {
        $roles = $this->roles();

        if (is_null($roles)) {
            return '(Sin Rol)';
        }

        return $roles->pluck('display_name')->implode(',');
    }



    public function sucursales()
    {
        return $this->belongsToMany(Sucursal::class, 'sucursales_usuarios', 'id_usuario', 'id_sucursal');
    }

    public function getSucursalesUserAttribute()
    {
        $sucursales = $this->sucursales();

        if (is_null($sucursales)) {
            return '(Sin Rol)';
        }

        return $sucursales->pluck('nombre')->implode(',');
    }

    public function registros()
    {
        return $this->hasMany(Alumno::class, 'id_asesor_educativo', 'id');
    }

    public function horarios()
    {
        return $this->hasMany(HorarioProfesor::class, 'id_profesor', 'id');
    }

    public function especialidades()
    {
        return $this->belongsToMany(Especialidad::class, 'especialidades_users', 'id_usuario', 'id_especialidad')
            ->withPivot('id_especialidad')
            ->using(EspecialidadUser::class);
    }

    public function getLinkVerificacionAttribute(){
        if($this->id){
            return route('users.verificacion', $this->id);
        }
        
    }

    public function days()
    {
        return $this->hasMany(UsuarioDia::class, 'id_usuario', 'id');
    }

    public function getDiasCortoAttribute()
    {
        $horario = '';
        foreach ($this->days as $day) {
            $horario .= ucfirst(substr($day->dia, 0, 2)) . ' H ' . $day->hora_inicio . ' - ' . $day->hora_final . ' | ';
        }

        return $horario;
    }

    public function getHorarioCortoAttribute()
    {
        $horario = '';
        foreach ($this->days as $day) {
            $horario .= ucfirst(substr($day->dia, 0, 2)) . ' ' . $day->hora_inicio . ' - ' . $day->hora_final . ' <br> ';
        }
    }

       
}
