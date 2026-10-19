<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaUsuario;

test('selected corporations include terminals across groups and selections apply to every report request', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Grupo A', 'Subgrupo A', 'Corporacion X', 100],
        ['1002', 'Grupo B', 'Subgrupo B', 'Corporacion X', 200],
        ['1003', 'Grupo C', 'Subgrupo C', 'Corporacion Y', 300],
    ] as [$terminal, $grupo, $subgrupo, $sociedad, $monto]) {
        CentroCosto::factory()->create([
            'id_viejo' => $terminal,
            'id_grupo' => $grupo,
            'id_sub_grupo' => $subgrupo,
            'id_sociedad' => $sociedad,
        ]);
        VentaUsuario::factory()->create([
            'agencia_id' => $terminal,
            'tipo' => 'Tradicional',
            'monto' => $monto,
            'fecha' => '2026-10-01',
        ]);
    }

    $rutaReporte = route('reporte-ventas.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'agrupar' => ['terminal'],
    ]);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'sociedad', 'valor' => 'Corporacion X']],
    ])->assertOk();

    $this->getJson($rutaReporte)
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('totales.total_venta', 300);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'sociedad', 'valor' => 'Corporacion X'],
            ['dimension' => 'grupo', 'valor' => 'Grupo C'],
        ],
    ])->assertOk();

    $this->getJson($rutaReporte)->assertJsonPath('totales.total_venta', 600);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'grupo', 'valor' => 'Grupo A']],
    ])->assertOk();

    $this->getJson($rutaReporte)->assertJsonPath('totales.total_venta', 100);

    $this->putJson(route('terminal.guardar-categorias-reporte'), ['activas' => []])->assertOk();

    $this->getJson($rutaReporte)->assertJsonPath('totales.total_venta', 600);
});

test('operators can view and change report categories', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_OPERADOR]));
    CentroCosto::factory()->create(['id_grupo' => 'Grupo A']);

    $this->get(route('terminal.index'))->assertInertia(fn ($page) => $page->component('terminal/index')->where('puedeConfigurarReportes', true));
    $this->getJson(route('terminal.categorias-reporte'))->assertOk()->assertJsonPath('categorias.grupo.0.valor', 'Grupo A');
    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'grupo', 'valor' => 'Grupo A']],
    ])->assertOk();

    $this->assertDatabaseHas('reporte_categorias_activas', ['dimension' => 'grupo', 'valor' => 'Grupo A']);
});

test('unknown report categories are rejected without changing saved selections', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_grupo' => 'Grupo A']);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'grupo', 'valor' => 'Grupo A']],
    ])->assertOk();

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'grupo', 'valor' => 'Inexistente']],
    ])->assertUnprocessable()->assertJsonValidationErrors('activas');

    $this->assertDatabaseHas('reporte_categorias_activas', ['dimension' => 'grupo', 'valor' => 'Grupo A']);
});
