<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaUsuario;
use Illuminate\Support\Facades\Schema;

test('dashboard uses only marked subgroups and classifies terminals by their average on sale days', function () {
    $this->actingAs(User::factory()->create());
    $ventas = [
        ['1001', 'Subgrupo A', '2026-10-01', 13000, 'Tradicional'],
        ['1001', 'Subgrupo A', '2026-10-02', 13000, 'Tradicional'],
        ['1002', 'Subgrupo A', '2026-10-01', 10000, 'Recarga'],
        ['1003', 'Subgrupo B', '2026-10-02', 7000, 'Tradicional'],
        ['1004', 'Subgrupo B', '2026-10-02', 1000, 'Tradicional'],
        ['1005', 'Subgrupo C', '2026-10-01', 90000, 'Tradicional'],
    ];

    $terminalesCreadas = [];
    foreach ($ventas as [$terminal, $subgrupo, $fecha, $monto, $tipo]) {
        if (! isset($terminalesCreadas[$terminal])) {
            CentroCosto::factory()->create(['id_viejo' => $terminal, 'descripcion' => 'Terminal '.$terminal, 'id_sub_grupo' => $subgrupo]);
            $terminalesCreadas[$terminal] = true;
        }
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'fecha' => $fecha, 'monto' => $monto, 'tipo' => $tipo]);
    }
    CentroCosto::factory()->create(['id_viejo' => '1006', 'id_sub_grupo' => 'Subgrupo D']);
    VentaUsuario::factory()->create(['agencia_id' => '1001', 'fecha' => '2026-10-01', 'monto' => 50000, 'tipo' => 'VENTA']);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo A'],
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo B'],
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo D'],
        ],
    ])->assertOk();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/index')->where('subgruposSeleccionados', ['Subgrupo A', 'Subgrupo B', 'Subgrupo D']));

    $response = $this->getJson(route('dashboard.datos', ['desde' => '2026-10-01', 'hasta' => '2026-10-02']));
    $response
        ->assertOk()
        ->assertJsonPath('total_venta', 44000)
        ->assertJsonPath('promedio', 22000)
        ->assertJsonPath('terminales_con_venta', 4)
        ->assertJsonPath('clasificaciones', ['A' => 1, 'B' => 1, 'C' => 1, 'D' => 1])
        ->assertJsonPath('subgrupos.0.nombre', 'Subgrupo A')
        ->assertJsonPath('subgrupos.0.total_venta', 36000)
        ->assertJsonPath('subgrupos.0.promedio', 18000)
        ->assertJsonPath('subgrupos.0.clasificaciones', ['A' => 1, 'B' => 1, 'C' => 0, 'D' => 0])
        ->assertJsonPath('subgrupos.0.terminales_clasificadas.A.0', ['terminal' => '1001', 'nombre' => 'Terminal 1001'])
        ->assertJsonPath('subgrupos.0.terminales_clasificadas.B.0', ['terminal' => '1002', 'nombre' => 'Terminal 1002'])
        ->assertJsonPath('subgrupos.1.nombre', 'Subgrupo B')
        ->assertJsonPath('subgrupos.1.total_venta', 8000)
        ->assertJsonPath('subgrupos.1.clasificaciones', ['A' => 0, 'B' => 0, 'C' => 1, 'D' => 1])
        ->assertJsonPath('subgrupos.1.terminales_clasificadas.C.0', ['terminal' => '1003', 'nombre' => 'Terminal 1003'])
        ->assertJsonPath('subgrupos.1.terminales_clasificadas.D.0', ['terminal' => '1004', 'nombre' => 'Terminal 1004'])
        ->assertJsonPath('subgrupos.2.nombre', 'Subgrupo D')
        ->assertJsonPath('subgrupos.2.clasificaciones', ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0])
        ->assertJsonPath('subgrupos.2.terminales_clasificadas.A', []);
});

test('dashboard returns an empty summary when no subgroup is marked', function () {
    $this->actingAs(User::factory()->create());
    VentaUsuario::factory()->create(['agencia_id' => '1001', 'fecha' => '2026-10-01', 'tipo' => 'Tradicional', 'monto' => 100]);

    $this->getJson(route('dashboard.datos', ['desde' => '2026-10-01', 'hasta' => '2026-10-02']))
        ->assertOk()
        ->assertJsonPath('total_venta', 0)
        ->assertJsonPath('subgrupos_configurados', 0)
        ->assertJsonPath('terminales_con_venta', 0);
});

test('dashboard normalizes agency identifiers when the generated terminal column is pending', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '0001001', 'id_sub_grupo' => 'Subgrupo A']);
    CentroCosto::factory()->create(['id_viejo' => '1002', 'id_sub_grupo' => 'Subgrupo B']);
    VentaUsuario::factory()->create(['agencia_id' => '001001', 'fecha' => '2026-10-01', 'tipo' => 'Tradicional', 'monto' => 100]);
    VentaUsuario::factory()->create(['agencia_id' => '1001', 'fecha' => '2026-10-01', 'tipo' => 'Recarga', 'monto' => 200]);
    VentaUsuario::factory()->create(['agencia_id' => '1002', 'fecha' => '2026-10-01', 'tipo' => 'Tradicional', 'monto' => 900]);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => 'Subgrupo A']],
    ])->assertOk();
    Schema::partialMock()->shouldReceive('hasColumn')
        ->once()
        ->with('ventas_usuarios', 'terminal_key')
        ->andReturn(false);

    $this->getJson(route('dashboard.datos', ['desde' => '2026-10-01', 'hasta' => '2026-10-01']))
        ->assertOk()
        ->assertJsonPath('total_venta', 300)
        ->assertJsonPath('terminales_con_venta', 1)
        ->assertJsonPath('subgrupos.0.nombre', 'Subgrupo A')
        ->assertJsonPath('subgrupos.0.clasificaciones', ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 1]);
});

test('dashboard limits the period and requires authentication', function () {
    $params = ['desde' => '2026-08-01', 'hasta' => '2026-10-01'];

    $this->getJson(route('dashboard.datos', $params))->assertUnauthorized();

    $this->actingAs(User::factory()->create());

    $this->getJson(route('dashboard.datos', $params))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('hasta');
});
