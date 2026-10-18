<?php

namespace Database\Factories;

use App\Models\VentaRaza;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaRaza>
 */
class VentaRazaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consorcio_id' => fake()->numerify('#'),
            'agencia_id' => fake()->numerify('########'),
            'proveedor_id' => fake()->numerify('#'),
            'proveedor_nombre' => fake()->company(),
            'ventas' => fake()->randomFloat(2, 10, 20000),
            'premios_pagados' => fake()->randomFloat(2, 0, 10000),
            'premios' => fake()->randomFloat(2, 0, 10000),
            'fecha' => fake()->date(),
        ];
    }
}
