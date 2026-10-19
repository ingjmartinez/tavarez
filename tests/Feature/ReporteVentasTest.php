<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;
use Illuminate\Support\Facades\DB;

test('generated terminal keys normalize leading zeroes for both sales tables', function () {
    VentaUsuario::factory()->create(['agencia_id' => '008284636', 'tipo' => 'Tradicional', 'fecha' => '2026-08-10']);
    VentaRaza::factory()->create(['agencia_id' => '008284636', 'fecha' => '2026-08-10']);

    expect(DB::table('ventas_usuarios')->value('terminal_key'))->toBe('8284636')
        ->and(DB::table('ventas_raza')->value('terminal_key'))->toBe('8284636');
});

test('daily averages use sale days separately for each product type and raza', function () {
    $this->actingAs(User::factory()->create());

    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-10-01']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'Tradicional', 'monto' => 200, 'fecha' => '2026-10-01']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-10-03']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'Recarga', 'monto' => 90, 'fecha' => '2026-10-02']);
    VentaRaza::factory()->create(['agencia_id' => '8001001', 'ventas' => 60, 'fecha' => '2026-10-01']);
    VentaRaza::factory()->create(['agencia_id' => '8001001', 'ventas' => 120, 'fecha' => '2026-10-02']);

    $this->getJson(route('reporte-ventas.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'agrupar' => ['terminal', 'tipo'],
        'raza' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('filas.0.promedios_por_tipo.Tradicional', 200)
        ->assertJsonPath('filas.0.promedios_por_tipo.Recarga', 90)
        ->assertJsonPath('filas.0.promedios_por_tipo.Raza', 90)
        ->assertJsonPath('filas.0.promedio', 223.33)
        ->assertJsonPath('totales.promedios_por_tipo.Tradicional', 200)
        ->assertJsonPath('totales.promedios_por_tipo.Recarga', 90)
        ->assertJsonPath('totales.promedios_por_tipo.Raza', 90);
});

test('terminals are classified by their average on days with sales', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('reporte-ventas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reporte-ventas/index')->where('clasificacionUmbrales', ['A' => 12500, 'B' => 10000, 'C' => 6500]));

    foreach ([
        '1001' => 25000,
        '1002' => 20000,
        '1003' => 13000,
        '1004' => 12998,
    ] as $terminal => $venta) {
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $venta / 2, 'fecha' => '2026-10-01']);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $venta / 2, 'fecha' => '2026-10-03']);
    }

    $this->getJson(route('reporte-ventas.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'agrupar' => ['terminal'],
    ]))
        ->assertOk()
        ->assertJsonPath('filas.0.clasificacion', 'A')
        ->assertJsonPath('filas.1.clasificacion', 'B')
        ->assertJsonPath('filas.2.clasificacion', 'C')
        ->assertJsonPath('filas.3.clasificacion', 'D');
});

test('subgroup report omits product columns without sales after filtering', function () {
    $this->actingAs(User::factory()->create());

    CentroCosto::factory()->create(['id_viejo' => '08001001', 'id_sub_grupo' => 'Romana']);
    CentroCosto::factory()->create(['id_viejo' => '08001002', 'id_sub_grupo' => 'Santiago']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-10-01']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'VENTA', 'monto' => 250, 'fecha' => '2026-10-02']);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'tipo' => 'RECARGA', 'monto' => 150, 'fecha' => '2026-10-02']);
    VentaUsuario::factory()->create(['agencia_id' => '8001002', 'tipo' => 'Recarga', 'monto' => 50, 'fecha' => '2026-10-01']);

    $this->getJson(route('reporte-ventas.filtros'))
        ->assertOk()
        ->assertJsonPath('tipos', ['Recarga', 'Tradicional']);

    $this->getJson(route('reporte-ventas.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'agrupar' => ['subgrupo', 'tipo'],
        'subgrupo' => 'Romana',
    ]))
        ->assertOk()
        ->assertJsonPath('tipos', ['Tradicional'])
        ->assertJsonPath('totales.total_venta', 100)
        ->assertJsonPath('cobertura.dias_con_datos', 1);
});
