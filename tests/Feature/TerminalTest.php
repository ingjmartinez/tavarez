<?php

use App\Models\CentroCosto;
use App\Models\User;
use App\Models\VentaUsuario;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function centroConTerminal(string $terminal, bool $inactivo = false): CentroCosto
{
    return CentroCosto::factory()->create(['id_viejo' => $terminal, 'inactivo' => $inactivo]);
}

test('guests cannot open the terminal module', function () {
    auth()->logout();

    $this->get(route('terminal.index'))->assertRedirect();
});

test('the terminal page renders', function () {
    $this->get(route('terminal.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('terminal/index'));
});

test('the summary counts active and inactive terminals from the cost center catalog', function () {
    centroConTerminal('08001001');
    centroConTerminal('08001002');
    centroConTerminal('08001003', inactivo: true);
    CentroCosto::factory()->create(['id_viejo' => null]);

    $this->getJson(route('terminal.resumen'))
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('activas', 2)
        ->assertJsonPath('inactivas', 1)
        ->assertJsonCount(3, 'terminales');
});

test('a terminal with several cost centers is active when any of them is active', function () {
    centroConTerminal('08001001', inactivo: true);
    centroConTerminal('8001001');

    $this->getJson(route('terminal.resumen'))
        ->assertJsonPath('total', 1)
        ->assertJsonPath('activas', 1);
});

test('the inactive detail lists only deactivated terminals', function () {
    centroConTerminal('08001001');
    centroConTerminal('08001002', inactivo: true);

    $this->getJson(route('terminal.inactivas'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('terminales.0.terminal', '08001002');
});

test('sales are matched to terminals ignoring leading zeros and a terminal without sales is not inactive', function () {
    centroConTerminal('08001001');
    centroConTerminal('08001002');
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'monto' => 100, 'fecha' => now()->toDateString()]);

    $this->getJson(route('terminal.sin-venta-30-dias'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('terminales.0.terminal', '08001002')
        ->assertJsonPath('terminales.0.activa', true);

    $this->getJson(route('terminal.resumen'))->assertJsonPath('inactivas', 0);
});

test('sales outside the last 30 days or with zero amount do not count', function () {
    centroConTerminal('08001001');
    centroConTerminal('08001002');
    VentaUsuario::factory()->create(['agencia_id' => '08001001', 'monto' => 100, 'fecha' => now()->subDays(45)->toDateString()]);
    VentaUsuario::factory()->create(['agencia_id' => '08001002', 'monto' => 0, 'fecha' => now()->toDateString()]);

    $this->getJson(route('terminal.sin-venta-30-dias'))->assertJsonPath('total', 2);
});

test('inactive terminals with recent sales are reported with their sales', function () {
    centroConTerminal('08001001', inactivo: true);
    centroConTerminal('08001002', inactivo: true);
    VentaUsuario::factory()->create(['agencia_id' => '08001001', 'monto' => 250, 'fecha' => now()->toDateString()]);
    VentaUsuario::factory()->create(['agencia_id' => '8001001', 'monto' => 50, 'fecha' => now()->subDay()->toDateString()]);

    $this->getJson(route('terminal.inactivas-con-venta-30-dias'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('terminales.0.terminal', '08001001')
        ->assertJsonPath('terminales.0.total_venta', 300)
        ->assertJsonPath('terminales.0.dias_con_venta', 2);
});

test('the coverage flag warns when the period has fewer days of sales than expected', function () {
    VentaUsuario::factory()->create(['fecha' => now()->toDateString()]);

    $this->getJson(route('terminal.sin-venta-30-dias'))
        ->assertJsonPath('cobertura.dias_con_datos', 1)
        ->assertJsonPath('cobertura.completa', false);
});
