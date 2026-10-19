<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;

test('the subgroup report shows only marked subgroups even when a corporation is marked', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Subgrupo A', 100],
        ['1002', 'Subgrupo B', 200],
    ] as [$terminal, $subgrupo, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sub_grupo' => $subgrupo, 'id_sociedad' => 'Corporacion X']);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    VentaRaza::factory()->create(['agencia_id' => '1001', 'ventas' => 50, 'fecha' => '2026-10-01']);
    VentaRaza::factory()->create(['agencia_id' => '1002', 'ventas' => 70, 'fecha' => '2026-10-01']);

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo A'],
            ['dimension' => 'sociedad', 'valor' => 'Corporacion X'],
        ],
    ])->assertOk();

    $this->get(route('reporte-subgrupos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reporte-subgrupos/index')->where('categoriasActivas.subgrupo', ['Subgrupo A'])->where('categoriasActivas.sociedad', ['Corporacion X']));

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'subgrupo',
        'categoria' => 'Subgrupo A',
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 1)
        ->assertJsonPath('filas.0.subgrupo', 'Subgrupo A')
        ->assertJsonPath('filas.0.tipos.Raza', 50)
        ->assertJsonPath('totales.total_venta', 150);

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'sociedad',
        'categoria' => 'Corporacion X',
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 1)
        ->assertJsonPath('filas.0.sociedad', 'Corporacion X')
        ->assertJsonPath('filas.0.tipos.Raza', 120)
        ->assertJsonPath('totales.total_venta', 420);
});

test('the report requires a marked subgroup or corporation', function () {
    $this->actingAs(User::factory()->create());
    CentroCosto::factory()->create(['id_viejo' => '1001', 'id_sub_grupo' => 'Subgrupo A']);
    VentaUsuario::factory()->create(['agencia_id' => '1001', 'tipo' => 'Tradicional', 'monto' => 100, 'fecha' => '2026-10-01']);

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['modo', 'categoria']);

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'subgrupo',
        'categoria' => 'Subgrupo A',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('categoria');
});

test('all subgroups includes each marked subgroup and excludes unmarked ones', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Subgrupo A', 100],
        ['1002', 'Subgrupo B', 200],
        ['1003', 'Subgrupo C', 300],
    ] as [$terminal, $subgrupo, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sub_grupo' => $subgrupo]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo A'],
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo B'],
        ],
    ])->assertOk();

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'subgrupo',
        'categoria' => '__todos__',
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('totales.total_venta', 300);
});

test('multiple selected subgroups include only their sales and reject unmarked selections', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Subgrupo A', 100],
        ['1002', 'Subgrupo B', 200],
        ['1003', 'Subgrupo C', 300],
    ] as [$terminal, $subgrupo, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sub_grupo' => $subgrupo]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo A'],
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo B'],
        ],
    ])->assertOk();

    $base = ['desde' => '2026-10-01', 'hasta' => '2026-10-05', 'modo' => 'subgrupo', 'por_terminal' => true];

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categorias' => ['Subgrupo A', 'Subgrupo B']]))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('filas.0.subgrupo', 'Subgrupo A')
        ->assertJsonPath('filas.1.subgrupo', 'Subgrupo B')
        ->assertJsonPath('totales.total_venta', 300);

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categorias' => ['Subgrupo A', 'Subgrupo C']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('categoria');

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categorias' => ['Subgrupo A', 'Subgrupo A']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('categorias.1');
});

test('all corporations includes each marked corporation and excludes unmarked ones', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Corporacion A', 100],
        ['1002', 'Corporacion B', 200],
        ['1003', 'Corporacion C', 300],
    ] as [$terminal, $corporacion, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sociedad' => $corporacion]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'sociedad', 'valor' => 'Corporacion A'],
            ['dimension' => 'sociedad', 'valor' => 'Corporacion B'],
        ],
    ])->assertOk();

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'sociedad',
        'categoria' => '__todos__',
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('totales.total_venta', 300);

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'sociedad',
        'categorias' => ['Corporacion A', 'Corporacion B'],
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('totales.total_venta', 300);
});

test('terminal breakdown keeps subgroup grouping and marked category scope', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['01001', 'Subgrupo A', 100],
        ['1002', 'Subgrupo A', 200],
        ['1003', 'Subgrupo B', 300],
    ] as [$terminal, $subgrupo, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sub_grupo' => $subgrupo]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [['dimension' => 'subgrupo', 'valor' => 'Subgrupo A']],
    ])->assertOk();

    $base = [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'subgrupo',
        'por_terminal' => true,
    ];

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categoria' => 'Subgrupo A']))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('filas.0.subgrupo', 'Subgrupo A')
        ->assertJsonPath('filas.0.terminal', '01001')
        ->assertJsonPath('totales.total_venta', 300);

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categoria' => '__todos__']))
        ->assertOk()
        ->assertJsonPath('total_filas', 2)
        ->assertJsonPath('totales.total_venta', 300);

    $this->getJson(route('reporte-subgrupos.datos', $base + ['categoria' => 'Subgrupo B']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('categoria');
});

test('terminal breakdown keeps corporation grouping', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1001', 'Corporacion A', 100],
        ['1002', 'Corporacion A', 200],
        ['1003', 'Corporacion B', 300],
    ] as [$terminal, $corporacion, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sociedad' => $corporacion]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'sociedad', 'valor' => 'Corporacion A'],
            ['dimension' => 'sociedad', 'valor' => 'Corporacion B'],
        ],
    ])->assertOk();

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'sociedad',
        'categoria' => '__todos__',
        'por_terminal' => true,
    ]))
        ->assertOk()
        ->assertJsonPath('total_filas', 3)
        ->assertJsonPath('filas.0.sociedad', 'Corporacion A')
        ->assertJsonPath('filas.0.terminal', '1001')
        ->assertJsonPath('filas.1.sociedad', 'Corporacion A')
        ->assertJsonPath('filas.2.sociedad', 'Corporacion B')
        ->assertJsonPath('totales.total_venta', 600);
});

test('terminal rows remain together by subgroup name when all are selected', function () {
    $this->actingAs(User::factory()->create());

    foreach ([
        ['1003', 'Subgrupo B', 300],
        ['1002', 'Subgrupo A', 200],
        ['1001', 'Subgrupo A', 100],
    ] as [$terminal, $subgrupo, $monto]) {
        CentroCosto::factory()->create(['id_viejo' => $terminal, 'id_sub_grupo' => $subgrupo]);
        VentaUsuario::factory()->create(['agencia_id' => $terminal, 'tipo' => 'Tradicional', 'monto' => $monto, 'fecha' => '2026-10-01']);
    }

    $this->putJson(route('terminal.guardar-categorias-reporte'), [
        'activas' => [
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo A'],
            ['dimension' => 'subgrupo', 'valor' => 'Subgrupo B'],
        ],
    ])->assertOk();

    $this->getJson(route('reporte-subgrupos.datos', [
        'desde' => '2026-10-01',
        'hasta' => '2026-10-05',
        'modo' => 'subgrupo',
        'categoria' => '__todos__',
        'por_terminal' => true,
    ]))
        ->assertOk()
        ->assertJsonPath('filas.0.subgrupo', 'Subgrupo A')
        ->assertJsonPath('filas.0.terminal', '1001')
        ->assertJsonPath('filas.1.subgrupo', 'Subgrupo A')
        ->assertJsonPath('filas.1.terminal', '1002')
        ->assertJsonPath('filas.2.subgrupo', 'Subgrupo B');
});
