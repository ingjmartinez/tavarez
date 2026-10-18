<?php

namespace Database\Factories;

use App\Models\PagoMismaEmpresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoMismaEmpresa>
 */
class PagoMismaEmpresaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $agencia = fake()->numerify('########');

        return [
            'consorcio_id' => fake()->numerify('#'),
            'agencia_id' => $agencia,
            'pagado_agencia_id' => $agencia,
            'producto_id' => '0',
            'descripcion' => fake()->words(3, true),
            'plataforma_pago' => fake()->randomElement(['LOTOBET', 'BANCA']),
            'monto' => fake()->randomFloat(2, 10, 5000),
            'fecha' => fake()->date(),
        ];
    }
}
