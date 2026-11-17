<?php

namespace Database\Factories;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\Factory;

class SucursalFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Sucursal::class;

    protected $estados = [
        'Aguascalientes',
        'Baja California',
        'Guanajuato',
        'Jalisco',
        'Michoacán',
        'Morelos'
    ];

    protected $municipios = [
        'Celaya',
        'Irapuato',
        'Leon',
        'Salamanca',
    ];

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'nombre'    => $this->faker->name,
            'direccion' => $this->faker->streetAddress,
            'municipio' => $this->faker->randomElement($this->municipios),
            'estado'    => $this->faker->randomElement($this->estados)
        ];
    }
}
