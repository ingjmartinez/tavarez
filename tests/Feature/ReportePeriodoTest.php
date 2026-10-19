<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;

test('period report places a selected subgroup sale in each monthly block', function () {
    $this->actingAs(User::factory()->create());

    CentroCosto::factory()->create(['id_viejo' => '1001', 'id_sub_grupo' => '18-Romana']);
    CentroCosto::factory()->create(['id_viejo' => '1002', 'id_sub_grupo' => '34-Nagua']);

    foreach ([
        ['2026-07-20', 10],
        ['2026-08-10', 20],
        ['2026-09-03', 30],
    ] as [$fecha, $monto]) {
        VentaUsuario::factory()->create(['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => $fecha]);
        VentaRaza::factory()->create(['agencia_id' => '1001', 'ventas' => 5, 'fecha' => $fecha]);
        VentaUsuario::factory()->create(['agencia_id' => '1002', 'tipo' => 'Tradicional', 'monto' => 500, 'fecha' => $fecha]);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => '18-Romana'],
            ['dimension' => 'subgrupo', 'valor' => '34-Nagua'],
        ],
    ])->assertOk();

    $this->get(route('reporte-periodo.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reporte-periodo/index')->where('categoriasActivas.subgrupo', ['18-Romana', '34-Nagua']));

    $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-07-15',
        'hasta' => '2026-09-05',
        'modo' => 'subgrupo',
        'categoria' => '18-Romana',
        'agrupar_meses' => 0,
    ]))
        ->assertOk()
        ->assertJsonCount(3, 'meses')
        ->assertJsonPath('meses.0.desde', '2026-07-15')
        ->assertJsonPath('meses.2.hasta', '2026-09-05')
        ->assertJsonPath('total_filas', 1)
        ->assertJsonPath('filas.0.subgrupo', '18-Romana')
        ->assertJsonPath('filas.0.meses.2026-07.total_venta', 15)
        ->assertJsonPath('filas.0.meses.2026-08.total_venta', 25)
        ->assertJsonPath('filas.0.meses.2026-09.total_venta', 35)
        ->assertJsonPath('total_venta', 75);
});

test('period report keeps terminals under each marked corporation across months', function () {
    $this->actingAs(User::factory()->create());

    foreach ([['1001', 'Corporacion A', 10], ['1002', 'Corporacion A', 20], ['1003', 'Corporacion B', 30]] as [$terminal, $corporacion, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sociedad' => $corporacion]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-08-10']);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-09-10']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'sociedad', 'valor' => 'Corporacion A']],
    ])->assertOk();

    $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-08-01',
        'hasta' => '2026-09-30',
        'modo' => 'sociedad',
        'categoria' => '__todos__',
        'por_terminal' => 1,
        'agrupar_meses' => 0,
    ]))
        ->assertOk()
        ->assertJsonCount(2, 'meses')
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('filas.0.sociedad', 'Corporacion A')
        ->assertJsonPath('filas.0.terminal', '1001')
        ->assertJsonPath('filas.1.terminal', '1002')
        ->assertJsonPath('filas.1.meses.2026-08.total_venta', 20)
        ->assertJsonPath('total_venta', 60);

    $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-08-01',
        'hasta' => '2026-09-30',
        'modo' => 'sociedad',
        'categoria' => 'Corporacion B',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('categoria');
});

test('period report retains an empty month between months with sales', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '1001', 'id_sub_grupo' => '18-Romana']);

    foreach (['2026-07-10', '2026-09-10'] as $fecha) {
        VentaUsuario::factory()->create(['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 50, 'fecha' => $fecha]);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => '18-Romana']],
    ])->assertOk();

    $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-07-01',
        'hasta' => '2026-09-30',
        'modo' => 'subgrupo',
        'categoria' => '18-Romana',
        'agrupar_meses' => 0,
    ]))
        ->assertOk()
        ->assertJsonCount(3, 'meses')
        ->assertJsonPath('meses.1.clave', '2026-08')
        ->assertJsonPath('meses.1.totales.total_venta', 0)
        ->assertJsonMissingPath('filas.0.meses.2026-08')
        ->assertJsonPath('total_venta', 100);
});

test('checked monthly grouping combines selected months in one table block', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '1001', 'id_sub_grupo' => '18-Romana']);

    foreach (['2026-08-10', '2026-09-10'] as $fecha) {
        VentaUsuario::factory()->create(['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 50, 'fecha' => $fecha]);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => '18-Romana']],
    ])->assertOk();

    $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-08-01',
        'hasta' => '2026-09-30',
        'modo' => 'subgrupo',
        'categoria' => '18-Romana',
        'agrupar_meses' => 1,
    ]))
        ->assertOk()
        ->assertJsonPath('agrupar_meses', true)
        ->assertJsonCount(1, 'meses')
        ->assertJsonPath('meses.0.clave', 'periodo')
        ->assertJsonPath('meses.0.etiqueta', 'Ventas agrupadas')
        ->assertJsonPath('meses.0.periodo', 'Agosto 2026 a Septiembre 2026')
        ->assertJsonPath('filas.0.meses.periodo.total_venta', 100)
        ->assertJsonPath('filas.0.meses.periodo.promedio', 50);
});

test('grouped months preserve the totals and types of separate months', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '1001', 'id_sub_grupo' => '18-Romana']);
    CentroCosto::factory()->create(['id_viejo' => '1002', 'id_sub_grupo' => '34-Nagua']);

    foreach ([['2026-08-10', 20], ['2026-09-10', 30]] as [$fecha, $monto]) {
        VentaUsuario::factory()->create(['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => $fecha]);
        VentaRaza::factory()->create(['agencia_id' => '1001', 'ventas' => 5, 'fecha' => $fecha]);
        VentaUsuario::factory()->create(['agencia_id' => '1002', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => $fecha]);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => '18-Romana']],
    ])->assertOk();

    $parametros = [
        'desde' => '2026-08-01',
        'hasta' => '2026-09-30',
        'modo' => 'subgrupo',
        'categoria' => '18-Romana',
    ];

    $separado = $this->getJson(route('reporte-periodo.datos', $parametros + ['agrupar_meses' => 0]))->assertOk()->json();
    $agrupado = $this->getJson(route('reporte-periodo.datos', $parametros + ['agrupar_meses' => 1]))->assertOk()->json();

    expect($separado['total_venta'])->toBe(60)
        ->and($agrupado['total_venta'])->toBe(60)
        ->and($agrupado['total_filas'])->toBe(1)
        ->and($agrupado['tipos'])->toBe($separado['tipos'])
        ->and($agrupado['filas'][0]['meses']['periodo']['tipos'])->toBe(['Tradicional' => 50, 'Raza' => 10])
        ->and($agrupado['meses'][0]['totales']['registros'])->toBe(4)
        ->and($agrupado['meses'][0]['cobertura'])->toBe(['dias_con_datos' => 2, 'dias_rango' => 61]);
});

test('period report preserves totals with grouping and terminal detail', function (bool $agruparMeses, bool $porTerminal) {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '008284636', 'id_sub_grupo' => '18-Romana']);
    CentroCosto::factory()->create(['id_viejo' => '1002', 'id_sub_grupo' => '34-Nagua']);

    foreach ([['2026-08-10', 20], ['2026-09-10', 30]] as [$fecha, $monto]) {
        VentaUsuario::factory()->create(['agencia_id' => '8284636', 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => $fecha]);
        VentaRaza::factory()->create(['agencia_id' => '08284636', 'ventas' => 5, 'fecha' => $fecha]);
        VentaUsuario::factory()->create(['agencia_id' => '1002', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => $fecha]);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => '18-Romana']],
    ])->assertOk();

    $resultado = $this->getJson(route('reporte-periodo.datos', [
        'desde' => '2026-08-01',
        'hasta' => '2026-09-30',
        'modo' => 'subgrupo',
        'categoria' => '18-Romana',
        'por_terminal' => (int) $porTerminal,
        'agrupar_meses' => (int) $agruparMeses,
    ]))->assertOk()->json();

    expect($resultado['total_venta'])->toBe(60)
        ->and($resultado['total_filas'])->toBe(1)
        ->and($resultado['tipos'])->toBe(['Tradicional', 'Raza'])
        ->and($resultado['meses'])->toHaveCount($agruparMeses ? 1 : 2);

    if ($agruparMeses) {
        expect($resultado['filas'][0]['meses']['periodo']['tipos'])->toBe(['Tradicional' => 50, 'Raza' => 10]);
    } else {
        expect($resultado['filas'][0]['meses']['2026-08']['tipos'])->toBe(['Tradicional' => 20, 'Raza' => 5])
            ->and($resultado['filas'][0]['meses']['2026-09']['tipos'])->toBe(['Tradicional' => 30, 'Raza' => 5]);
    }
})->with([
    'monthly summary' => [false, false],
    'monthly terminals' => [false, true],
    'combined summary' => [true, false],
    'combined terminals' => [true, true],
]);
