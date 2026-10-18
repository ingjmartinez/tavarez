<?php

namespace Database\Factories;

use App\Models\CentroCosto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CentroCosto>
 */
class CentroCostoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'empresa_id' => 162,
            'id_centro_costo' => fake()->unique()->numberBetween(1, 100000),
            'company' => '162-Negocios Icrapat, Srl',
            'descripcion' => fake()->city().' Suc-'.fake()->numerify('####'),
            'cuenta' => null,
            'inactivo' => false,
            'id_grupo' => '3-Las Terrenas',
            'id_sub_grupo' => '19-Las Terrenas',
            'id_division' => '14-Region Sur',
            'id_sociedad' => '10-Negocios Icrapat',
            'id_viejo' => fake()->numerify('########'),
            'id_centro_costo_resumir_en' => null,
            'com_recarga' => false,
            'gasto_vta_tradicional' => false,
            'varios_locales' => false,
            'aplica_para_ponderar' => false,
            'valor_ponderar' => 0,
            'creado_por' => 'SYSADMIN',
            'fecha_grabado' => now(),
            'modificado_por' => null,
            'fecha_modificado' => null,
            'atributos' => null,
        ];
    }
}
