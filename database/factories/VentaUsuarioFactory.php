<?php

namespace Database\Factories;

use App\Models\VentaUsuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaUsuario>
 */
class VentaUsuarioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consorcio_id' => fake()->numerify('##'),
            'agencia_id' => fake()->numerify('####'),
            'cedula' => fake()->numerify('###########'),
            'tipo' => fake()->randomElement(['VENTA', 'RECARGA']),
            'producto_id' => fake()->numerify('###'),
            'descripcion' => fake()->words(3, true),
            'monto' => fake()->randomFloat(2, 10, 5000),
            'fecha' => fake()->date(),
        ];
    }
}
