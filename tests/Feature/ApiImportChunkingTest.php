<?php

use App\Models\CentroCosto;
use App\Models\ExternalApiToken;
use App\Models\PagoMismaEmpresa;
use App\Models\PagoOtraEmpresa;
use App\Models\User;
use App\Models\VentaRaza;
use App\Models\VentaUsuario;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.ventas_usuario', [
        'ventas_url' => 'https://api.test/ventas',
        'pagos_url' => 'https://api.test/pagos',
        'pagos_otra_empresa_url' => 'https://api.test/pagos-otra',
        'ventas_raza_url' => 'https://api.test/ventas-raza',
        'codigo' => '08',
        'header_cc' => 'cc-value',
        'header_vb' => 'vb-value',
        'timeout' => 5,
    ]);
    config()->set('services.centro_costo', [
        'url' => 'https://api.test/centros',
        'token' => 'token-test',
        'empresa_id' => 8,
        'timeout' => 5,
    ]);
});

test('saves every row across multiple insert batches', function (string $routeName, string $modelClass, string $apiPath, array $apiRow) {
    $this->actingAs(User::factory()->create());
    ExternalApiToken::factory()->create(['token' => 'tok-1']);
    Http::preventStrayRequests();
    Http::fake([
        "api.test/{$apiPath}/tok-1/2026-10-14/08" => Http::response([
            'code' => 0,
            'Content' => array_fill(0, 1001, $apiRow),
        ]),
    ]);

    $this->postJson(route($routeName), ['fecha' => '2026-10-14'])
        ->assertOk()
        ->assertJsonPath('total', 1001);

    expect($modelClass::query()->count())->toBe(1001)
        ->and($modelClass::query()->whereDate('fecha', '2026-10-14')->count())->toBe(1001);
})->with([
    'ventas usuario' => [
        'ventas-usuario.guardar',
        VentaUsuario::class,
        'ventas',
        ['consorcio_id' => '01', 'agencia_id' => '1001', 'producto_id' => '5', 'cedula' => '001-1234567-8', 'descripcion' => 'Quiniela', 'tipo' => 'VENTA', 'monto' => 150.5],
    ],
    'pagos misma empresa' => [
        'pagos-misma-empresa.guardar',
        PagoMismaEmpresa::class,
        'pagos',
        ['consorcio_id' => 8, 'agencia_id' => '08125009', 'pagado_agencia_id' => '08125009', 'producto_id' => 0, 'descripcion' => 'Quiniela', 'plataforma_pago' => 'LOTOBET', 'monto' => 100.5],
    ],
    'pagos otra empresa' => [
        'pagos-otra-empresa.guardar',
        PagoOtraEmpresa::class,
        'pagos-otra',
        ['consorcio_id' => 8, 'agencia_id' => '08125009', 'pagado_consorcio_id' => 9, 'producto_id' => 0, 'descripcion' => 'Quiniela', 'plataforma_pago' => 'LOTOBET', 'monto' => 100.5],
    ],
    'ventas raza' => [
        'ventas-raza.guardar',
        VentaRaza::class,
        'ventas-raza',
        ['consorcio_id' => 8, 'agencia_id' => '08125009', 'proveedor_id' => 9, 'proveedor_nombre' => 'Proveedor', 'ventas' => 100.5, 'premios_pagados' => 10, 'premios' => 20],
    ],
]);

test('synchronizes cost centers across multiple upsert batches', function () {
    $this->actingAs(User::factory()->create());
    Http::preventStrayRequests();
    Http::fake([
        'api.test/centros?strToken=token-test&intIdEmpresa=8' => Http::response(array_map(
            fn (int $id) => ['IdCentroCosto' => $id, 'Descripcion' => "Centro {$id}", 'FechaGrabado' => '2026-10-14 10:00:00'],
            range(1, 1001),
        )),
    ]);

    $this->postJson(route('centro-costo.sincronizar'))
        ->assertOk()
        ->assertJsonPath('nuevos', 1001)
        ->assertJsonPath('total_guardados', 1001);

    expect(CentroCosto::query()->where('empresa_id', 8)->count())->toBe(1001)
        ->and(CentroCosto::query()->where('empresa_id', 8)->where('id_centro_costo', 1001)->value('descripcion'))->toBe('Centro 1001');
});
