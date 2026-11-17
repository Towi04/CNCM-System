<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'id_sucursal',
        'guard_name',
    ];

    public static function create(array $attributes = [])
    {
        parent::create($attributes);
    }

    /**
     * Update the model in the database.
     *
     * @param  array  $attributes
     * @param  array  $options
     * @return bool
     */
    public function update(array $attributes = [], array $options = [])
    {
        parent::update($attributes, $options);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class,'id_sucursal','id')->withDefault([
            'nombre' => 'Sin Sucursal'
        ]);
    }
}
