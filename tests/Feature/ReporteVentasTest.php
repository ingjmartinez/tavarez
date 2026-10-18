<?php

use App\Models\User;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;

test('daily averages use sale days separately for each product type and raza', function () {
    $this->actingAs(User::factory()->create());

    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'A', 'monto' => 100, 'fecha' => '2026-10-01']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'A', 'monto' => 200, 'fecha' => '2026-10-01']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'A', 'monto' => 100, 'fecha' => '2026-10-03']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'B', 'monto' => 90, 'fecha' => '2026-10-02']);
    VentaRaza::factory()->create(['agencia_id' => '8001001', 'ventas' => 60, 'fecha' => '2026-10-01']);
    VentaRaza::factory()->create(['agencia_id' => '8001001', 'ventas' => 120, 'fecha' => '2026-10-02']);

    $this->getJson(route('reporte-ventas.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'agrupar' => ['terminal', 'tipo'],
        'raza' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('filas.0.promedios_por_tipo.A', 200)
        ->assertJsonPath('filas.0.promedios_por_tipo.B', 90)
        ->assertJsonPath('filas.0.promedios_por_tipo.Raza', 90)
        ->assertJsonPath('filas.0.promedio', 223.33)
        ->assertJsonPath('totales.promedios_por_tipo.A', 200)
        ->assertJsonPath('totales.promedios_por_tipo.B', 90)
        ->assertJsonPath('totales.promedios_por_tipo.Raza', 90);
});
