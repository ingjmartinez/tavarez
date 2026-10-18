<?php

namespace Database\Factories;

use App\Models\PagoOtraEmpresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoOtraEmpresa>
 */
class PagoOtraEmpresaFactory extends Factory
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
            'pagado_consorcio_id' => fake()->numerify('#'),
            'importe' => fake()->randomFloat(2, 10, 5000),
            'producto_id' => '0',
            'descripcion' => fake()->words(3, true),
            'plataforma_pago' => fake()->randomElement(['LOTOBET', 'BANCA']),
            'monto' => fake()->randomFloat(2, 10, 5000),
            'fecha' => fake()->date(),
        ];
    }
}
